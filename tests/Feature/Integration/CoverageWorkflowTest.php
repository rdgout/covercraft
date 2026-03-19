<?php

namespace Tests\Feature\Integration;

use App\Jobs\ProcessCoverageJob;
use App\Models\CoverageFile;
use App\Models\CoverageReport;
use App\Models\Repository;
use App\Models\RepositoryFileCache;
use App\Models\Team;
use App\Models\TeamAccessToken;
use App\Models\User;
use App\Services\CloverParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CoverageWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Team $team;

    protected string $apiToken;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->user = User::factory()->withTeams(1)->create();
        $this->team = $this->user->teams->first();
        $this->actingAs($this->user);

        $plainToken = Str::random(64);
        TeamAccessToken::factory()->forTeam($this->team)->create([
            'token' => hash('sha256', $plainToken),
        ]);
        $this->apiToken = $plainToken;
    }

    public function test_end_to_end_api_to_job_to_dashboard(): void
    {
        Queue::fake();

        Repository::factory()->forTeam($this->team)->create([
            'owner' => 'acme',
            'name' => 'app',
        ]);

        $cloverXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1234567890">
  <project timestamp="1234567890">
    <file name="src/Foo.php">
      <line num="1" type="stmt" count="1"/>
      <line num="2" type="stmt" count="1"/>
      <line num="3" type="stmt" count="0"/>
    </file>
    <file name="src/Bar.php">
      <line num="1" type="stmt" count="1"/>
    </file>
  </project>
</coverage>
XML;

        $file = UploadedFile::fake()->createWithContent('clover.xml', $cloverXml);

        $response = $this->withToken($this->apiToken)->postJson('/api/coverage', [
            'repository' => 'acme/app',
            'branch' => 'main',
            'commit_sha' => str_repeat('a', 40),
            'clover_file' => $file,
        ]);

        $response->assertStatus(202);
        Queue::assertPushed(ProcessCoverageJob::class);

        $report = CoverageReport::first();
        $this->assertEquals('pending', $report->status);

        RepositoryFileCache::create([
            'repository_id' => $report->repository_id,
            'branch' => 'main',
            'commit_sha' => str_repeat('a', 40),
            'files' => ['src/Foo.php', 'src/Bar.php', 'src/Baz.php'],
            'cached_at' => now(),
        ]);

        (new ProcessCoverageJob($report->id))->handle(new CloverParser);

        $report->refresh();
        $this->assertEquals('completed', $report->status);
        $this->assertEquals('75.00', $report->coverage_percentage);
        $this->assertCount(2, CoverageFile::where('coverage_report_id', $report->id)->get());

        $repo = Repository::first();

        $dashboardResponse = $this->get('/dashboard');
        $dashboardResponse->assertOk();
        $dashboardResponse->assertSee('acme/app');

        $repoResponse = $this->get("/dashboard/{$repo->id}");
        $repoResponse->assertOk();
        $repoResponse->assertSee('main');

        $branchResponse = $this->get("/dashboard/{$repo->id}/main");
        $branchResponse->assertOk();
        $branchResponse->assertSee('Foo.php');
        $branchResponse->assertSee('Bar.php');
        $branchResponse->assertDontSee('Baz.php');

        $fileResponse = $this->get("/dashboard/{$repo->id}/main/file?path=src/Foo.php");
        $fileResponse->assertOk();
        $fileResponse->assertSee('src/Foo.php');
    }

    public function test_multiple_submissions_with_archival(): void
    {
        Queue::fake();

        $repository = Repository::factory()->forTeam($this->team)->create(['owner' => 'acme', 'name' => 'app']);

        $firstXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<coverage><project><file name="src/A.php">
  <line num="1" type="stmt" count="1"/>
  <line num="2" type="stmt" count="0"/>
</file></project></coverage>
XML;

        $firstFile = UploadedFile::fake()->createWithContent('clover.xml', $firstXml);
        $this->withToken($this->apiToken)->postJson('/api/coverage', [
            'repository' => 'acme/app',
            'branch' => 'main',
            'commit_sha' => str_repeat('a', 40),
            'clover_file' => $firstFile,
        ]);

        $firstReport = CoverageReport::first();

        RepositoryFileCache::create([
            'repository_id' => $firstReport->repository_id,
            'branch' => $firstReport->branch,
            'commit_sha' => $firstReport->commit_sha,
            'files' => [],
            'cached_at' => now(),
        ]);

        (new ProcessCoverageJob($firstReport->id))->handle(new CloverParser);

        $firstReport->refresh();
        $this->assertEquals('50.00', $firstReport->coverage_percentage);
        $this->assertFalse($firstReport->archived);

        $secondXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<coverage><project><file name="src/A.php">
  <line num="1" type="stmt" count="1"/>
  <line num="2" type="stmt" count="1"/>
</file></project></coverage>
XML;

        $secondFile = UploadedFile::fake()->createWithContent('clover.xml', $secondXml);
        $this->withToken($this->apiToken)->postJson('/api/coverage', [
            'repository' => 'acme/app',
            'branch' => 'main',
            'commit_sha' => str_repeat('b', 40),
            'clover_file' => $secondFile,
        ]);

        $secondReport = CoverageReport::where('commit_sha', str_repeat('b', 40))->first();

        RepositoryFileCache::updateOrCreate(
            ['repository_id' => $secondReport->repository_id, 'branch' => $secondReport->branch],
            ['commit_sha' => $secondReport->commit_sha, 'files' => [], 'cached_at' => now()],
        );

        (new ProcessCoverageJob($secondReport->id))->handle(new CloverParser);

        $this->assertTrue($firstReport->fresh()->archived);
        $this->assertFalse($secondReport->fresh()->archived);
        $this->assertEquals('100.00', $secondReport->fresh()->coverage_percentage);

        $current = CoverageReport::current()
            ->where('repository_id', $repository->id)
            ->where('branch', 'main')
            ->get();

        $this->assertCount(1, $current);
        $this->assertEquals($secondReport->id, $current->first()->id);
    }

    public function test_branch_comparison_workflow(): void
    {
        Queue::fake();

        $repository = Repository::factory()->forTeam($this->team)->create([
            'owner' => 'acme',
            'name' => 'app',
            'default_branch' => 'main',
        ]);

        $mainXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<coverage><project><file name="src/A.php">
  <line num="1" type="stmt" count="1"/>
  <line num="2" type="stmt" count="0"/>
  <line num="3" type="stmt" count="1"/>
  <line num="4" type="stmt" count="0"/>
</file></project></coverage>
XML;
        Storage::disk('local')->put('coverage/main.xml', $mainXml);
        $mainReport = CoverageReport::factory()->pending()->create([
            'repository_id' => $repository->id,
            'branch' => 'main',
            'clover_file_path' => 'coverage/main.xml',
        ]);

        RepositoryFileCache::create([
            'repository_id' => $repository->id,
            'branch' => 'main',
            'commit_sha' => $mainReport->commit_sha,
            'files' => [],
            'cached_at' => now(),
        ]);

        (new ProcessCoverageJob($mainReport->id))->handle(new CloverParser);

        $featureXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<coverage><project><file name="src/A.php">
  <line num="1" type="stmt" count="1"/>
  <line num="2" type="stmt" count="1"/>
  <line num="3" type="stmt" count="1"/>
  <line num="4" type="stmt" count="0"/>
</file></project></coverage>
XML;
        Storage::disk('local')->put('coverage/feature.xml', $featureXml);
        $featureReport = CoverageReport::factory()->pending()->create([
            'repository_id' => $repository->id,
            'branch' => 'feature/improve',
            'clover_file_path' => 'coverage/feature.xml',
        ]);

        RepositoryFileCache::create([
            'repository_id' => $repository->id,
            'branch' => 'feature/improve',
            'commit_sha' => $featureReport->commit_sha,
            'files' => ['src/A.php'],
            'cached_at' => now(),
        ]);

        (new ProcessCoverageJob($featureReport->id))->handle(new CloverParser);

        $mainReport->refresh();
        $featureReport->refresh();

        $this->assertEquals('50.00', $mainReport->coverage_percentage);
        $this->assertEquals('75.00', $featureReport->coverage_percentage);

        $response = $this->get("/dashboard/{$repository->id}/feature/improve");
        $response->assertOk();
        $response->assertSee('+25');
    }
}
