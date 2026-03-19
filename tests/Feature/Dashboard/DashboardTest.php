<?php

namespace Tests\Feature\Dashboard;

use App\Models\CoverageFile;
use App\Models\CoverageReport;
use App\Models\Repository;
use App\Models\RepositoryFileCache;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->withTeams(1)->create();
        $this->team = $this->user->teams->first();
        $this->actingAs($this->user);
    }

    public function test_index_page_renders(): void
    {
        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Repositories');
    }

    public function test_index_shows_repositories(): void
    {
        $repo = Repository::factory()->forTeam($this->team)->create(['owner' => 'acme', 'name' => 'app', 'default_branch' => 'main']);
        CoverageReport::factory()->create([
            'repository_id' => $repo->id,
            'branch' => 'main',
            'coverage_percentage' => 85.50,
        ]);

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSee('acme/app');
        $response->assertSee('85.50%');
    }

    public function test_index_shows_empty_state(): void
    {
        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSee('No repositories found');
    }

    public function test_repository_page_renders(): void
    {
        $repo = Repository::factory()->forTeam($this->team)->create();

        $response = $this->get("/dashboard/{$repo->id}");

        $response->assertOk();
        $response->assertSee($repo->owner.'/'.$repo->name);
    }

    public function test_repository_page_shows_branches(): void
    {
        $repo = Repository::factory()->forTeam($this->team)->create();
        CoverageReport::factory()->create([
            'repository_id' => $repo->id,
            'branch' => 'main',
            'coverage_percentage' => 80.00,
        ]);
        CoverageReport::factory()->create([
            'repository_id' => $repo->id,
            'branch' => 'feature/login',
            'coverage_percentage' => 75.00,
        ]);

        $response = $this->get("/dashboard/{$repo->id}");

        $response->assertOk();
        $response->assertSee('main');
        $response->assertSee('feature/login');
    }

    public function test_repository_page_shows_comparison_to_default_branch(): void
    {
        $repo = Repository::factory()->forTeam($this->team)->create(['default_branch' => 'main']);
        CoverageReport::factory()->create([
            'repository_id' => $repo->id,
            'branch' => 'main',
            'coverage_percentage' => 80.00,
        ]);
        CoverageReport::factory()->create([
            'repository_id' => $repo->id,
            'branch' => 'feature/test',
            'coverage_percentage' => 85.00,
        ]);

        $response = $this->get("/dashboard/{$repo->id}");

        $response->assertOk();
        $response->assertSee('+5');
    }

    public function test_branch_page_renders_with_file_tree(): void
    {
        $repo = Repository::factory()->forTeam($this->team)->create();
        $report = CoverageReport::factory()->create([
            'repository_id' => $repo->id,
            'branch' => 'main',
            'coverage_percentage' => 75.00,
        ]);
        CoverageFile::factory()->create([
            'coverage_report_id' => $report->id,
            'file_path' => 'src/Foo.php',
            'coverage_percentage' => 90.00,
        ]);
        RepositoryFileCache::factory()->create([
            'repository_id' => $repo->id,
            'branch' => 'main',
            'files' => ['src/Foo.php', 'src/Bar.php'],
        ]);

        $response = $this->get("/dashboard/{$repo->id}/main");

        $response->assertOk();
        $response->assertSee('Foo.php');
        $response->assertDontSee('Bar.php');
    }

    public function test_branch_page_shows_comparison(): void
    {
        $repo = Repository::factory()->forTeam($this->team)->create(['default_branch' => 'main']);
        CoverageReport::factory()->create([
            'repository_id' => $repo->id,
            'branch' => 'main',
            'coverage_percentage' => 70.00,
        ]);
        $report = CoverageReport::factory()->create([
            'repository_id' => $repo->id,
            'branch' => 'develop',
            'coverage_percentage' => 80.00,
        ]);
        RepositoryFileCache::factory()->create([
            'repository_id' => $repo->id,
            'branch' => 'develop',
            'files' => [],
        ]);

        $response = $this->get("/dashboard/{$repo->id}/develop");

        $response->assertOk();
        $response->assertSee('+10');
    }

    public function test_branch_page_returns_404_for_missing_report(): void
    {
        $repo = Repository::factory()->forTeam($this->team)->create();

        $response = $this->get("/dashboard/{$repo->id}/nonexistent");

        $response->assertNotFound();
    }

    public function test_file_page_renders(): void
    {
        $repo = Repository::factory()->forTeam($this->team)->create();
        $report = CoverageReport::factory()->create([
            'repository_id' => $repo->id,
            'branch' => 'main',
        ]);
        $lineCoverage = [
            1 => ['covered' => true, 'count' => 5],
            2 => ['covered' => false, 'count' => 0],
        ];
        CoverageFile::factory()->create([
            'coverage_report_id' => $report->id,
            'file_path' => 'src/Foo.php',
            'coverage_percentage' => 50.00,
            'covered_lines' => 1,
            'total_lines' => 2,
            'line_coverage_data' => base64_encode(gzcompress(json_encode($lineCoverage))),
        ]);

        $response = $this->get("/dashboard/{$repo->id}/main/file?path=src/Foo.php");

        $response->assertOk();
        $response->assertSee('src/Foo.php');
        $response->assertSee('50.00%');
        $response->assertSee('1/2 lines');
    }

    public function test_file_page_returns_404_for_missing_file(): void
    {
        $repo = Repository::factory()->forTeam($this->team)->create();
        CoverageReport::factory()->create([
            'repository_id' => $repo->id,
            'branch' => 'main',
        ]);

        $response = $this->get("/dashboard/{$repo->id}/main/file?path=src/Missing.php");

        $response->assertNotFound();
    }

    public function test_branch_with_slashes_in_name(): void
    {
        $repo = Repository::factory()->forTeam($this->team)->create();
        CoverageReport::factory()->create([
            'repository_id' => $repo->id,
            'branch' => 'feature/auth/login',
            'coverage_percentage' => 65.00,
        ]);
        RepositoryFileCache::factory()->create([
            'repository_id' => $repo->id,
            'branch' => 'feature/auth/login',
            'files' => [],
        ]);

        $response = $this->get("/dashboard/{$repo->id}/feature/auth/login");

        $response->assertOk();
        $response->assertSee('feature/auth/login');
    }
}
