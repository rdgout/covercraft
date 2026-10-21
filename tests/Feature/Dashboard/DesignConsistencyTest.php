<?php

namespace Tests\Feature\Dashboard;

use App\Contracts\GitHubServiceInterface;
use App\Models\CoverageFile;
use App\Models\CoverageReport;
use App\Models\Repository;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DesignConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Repository $repository;

    private CoverageReport $report;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->withTeams(1)->create(['name' => 'Rick Gout']);
        $this->repository = Repository::factory()->forTeam($this->user->teams->first())->create([
            'owner' => 'acme',
            'name' => 'design',
            'github_url' => 'https://github.com/acme/design',
            'default_branch' => 'main',
        ]);
        $this->report = CoverageReport::factory()->for($this->repository)->create(['branch' => 'feature/design']);
        CoverageFile::factory()->for($this->report)->create([
            'file_path' => 'app/Services/File Name.php',
            'coverage_percentage' => 60,
            'line_coverage_data' => base64_encode(gzcompress(json_encode([
                6 => ['covered' => false, 'count' => 0],
                3 => ['covered' => true, 'count' => 1],
                2 => ['covered' => false, 'count' => 0],
                1 => ['covered' => false, 'count' => 0],
                4 => ['covered' => false, 'count' => 0],
                7 => ['covered' => false, 'count' => 0],
            ]))),
        ]);
        $this->mock(GitHubServiceInterface::class, function ($mock): void {
            $mock->shouldReceive('fetchFileContents')->andReturn("<?php\n\n    return true;\n\n\n\n\n");
        });
    }

    public function test_repository_detail_pages_select_the_repositories_navigation_item(): void
    {
        $this->actingAs($this->user);

        foreach ([
            [route('dashboard'), 'Dashboard'],
            [route('dashboard.repository', $this->repository), 'Repositories'],
            [route('dashboard.branch', [$this->repository, $this->report->branch]), 'Repositories'],
            [route('dashboard.file', [$this->repository, $this->report->branch, 'path' => 'app/Services/File Name.php']), 'Repositories'],
            [route('tokens.index'), 'API tokens'],
        ] as [$url, $label]) {
            $response = $this->get($url)->assertOk();
            $xpath = $this->xpath($response);
            $active = $xpath->query('//nav[@aria-label="Main navigation"]//a[@aria-current="page"]');

            $this->assertCount(1, $active);
            $this->assertSame($label, trim($active->item(0)->textContent));
            $this->assertSame('RG', trim($xpath->query('//span[@class="cc-avatar"]')->item(0)->textContent));
        }
    }

    public function test_file_navigation_uses_real_uncovered_ranges_and_links_to_the_report_commit(): void
    {
        $default = CoverageReport::factory()->for($this->repository)->create(['branch' => 'main']);
        CoverageFile::factory()->for($default)->create(['file_path' => 'app/Services/File Name.php', 'coverage_percentage' => 80]);

        $response = $this->actingAs($this->user)->get(route('dashboard.file', [
            $this->repository, $this->report->branch, 'path' => 'app/Services/File Name.php',
        ]))->assertOk();

        $response->assertViewHas('coverageDelta', -20.0);
        $response->assertViewHas('uncoveredRanges', [
            ['start' => 1, 'end' => 2],
            ['start' => 4, 'end' => 4],
            ['start' => 6, 'end' => 7],
        ]);
        $xpath = $this->xpath($response);
        $this->assertSame('app/Services/File Name.php', $xpath->query('//h1')->item(0)->textContent);
        $this->assertCount(1, $xpath->query('//main/div[contains(@class, "sticky") and contains(@class, "top-0") and contains(@class, "z-20")]/*[@aria-label="Jump to uncovered lines"]'));
        $jumps = $xpath->query('//*[@aria-label="Jump to uncovered lines"]//a');
        $this->assertCount(3, $jumps);

        foreach (['#L1', '#L4', '#L6'] as $index => $href) {
            $this->assertSame($href, $jumps->item($index)->getAttribute('href'));
            $this->assertCount(1, $xpath->query('//tr[@id="'.substr($href, 1).'" and contains(@class, "is-uncovered")]'));
        }

        $this->assertSame('1–2', $jumps->item(0)->textContent);
        $github = $xpath->query('//a[contains(@href, "github.com/acme/design/blob/")]')->item(0);
        $this->assertSame('https://github.com/acme/design/blob/'.$this->report->commit_sha.'/app/Services/File%20Name.php', $github->getAttribute('href'));
    }

    public function test_many_uncovered_ranges_keep_navigation_compact_without_hiding_source_rows(): void
    {
        $coverage = [];
        foreach (range(1, 21, 2) as $number) {
            $coverage[$number] = ['covered' => false, 'count' => 0];
        }
        $this->report->files()->first()->update(['line_coverage_data' => base64_encode(gzcompress(json_encode($coverage)))]);

        $response = $this->actingAs($this->user)->get(route('dashboard.file', [
            $this->repository, $this->report->branch, 'path' => 'app/Services/File Name.php',
        ]))->assertOk();

        $response->assertViewHas('coverageDelta', null);
        $xpath = $this->xpath($response);
        $this->assertCount(7, $xpath->query('//*[@aria-label="Jump to uncovered lines"]//a'));
        $this->assertCount(1, $xpath->query('//tr[@id="L21" and contains(@class, "is-uncovered")]'));
    }

    public function test_branch_trends_are_bounded_per_branch_and_scoped_to_the_repository(): void
    {
        foreach (['main', $this->report->branch] as $branch) {
            for ($index = 0; $index < 12; $index++) {
                CoverageReport::factory()->for($this->repository)->archived()->create([
                    'branch' => $branch,
                    'coverage_percentage' => 50 + $index,
                    'created_at' => now()->subDays(12 - $index),
                ]);
            }
        }
        $this->report->update(['coverage_percentage' => 90]);
        CoverageReport::factory()->for($this->repository)->create(['branch' => 'main', 'coverage_percentage' => 90]);
        CoverageReport::factory()->for($this->repository)->archived()->failed()->create(['branch' => 'main']);
        CoverageReport::factory()->create(['branch' => 'main', 'coverage_percentage' => 0]);

        $response = $this->actingAs($this->user)->get(route('dashboard.repository', $this->repository))->assertOk();
        $histories = $response->viewData('branchHistories');

        foreach (['main', $this->report->branch] as $branch) {
            $this->assertSame([55.0, 56.0, 57.0, 58.0, 59.0, 60.0, 61.0, 90.0], $histories->get($branch)->pluck('coverage_percentage')->map(fn ($value): float => (float) $value)->all());
        }
        $response->assertSee('Trend');
    }

    public function test_changed_files_keep_their_links_and_coverage_in_the_branch_sidebar(): void
    {
        $default = CoverageReport::factory()->for($this->repository)->create(['branch' => 'main']);
        CoverageFile::factory()->for($default)->create(['file_path' => 'app/Services/File Name.php', 'coverage_percentage' => 80]);

        $response = $this->actingAs($this->user)->get(route('dashboard.branch', [$this->repository, $this->report->branch]))->assertOk();
        $xpath = $this->xpath($response);
        $links = $xpath->query('//section[.//h2[normalize-space()="Changed on this branch"]]//a');

        $this->assertCount(1, $links);
        $this->assertSame('app/Services/File Name.php', trim($links->item(0)->textContent));
        $this->assertSame(route('dashboard.file', [$this->repository, $this->report->branch, 'path' => 'app/Services/File Name.php']), $links->item(0)->getAttribute('href'));
        $this->assertCount(1, $xpath->query('//section[.//h2[normalize-space()="Changed on this branch"]]//*[@role="meter" and @aria-valuenow="60.00"]'));
    }

    /**
     * @param  TestResponse<Response>  $response
     */
    private function xpath(TestResponse $response): DOMXPath
    {
        $document = new DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);

        return new DOMXPath($document);
    }
}
