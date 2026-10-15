<?php

namespace Tests\Feature\Dashboard;

use App\Contracts\GitHubServiceInterface;
use App\Models\CoverageFile;
use App\Models\CoverageReport;
use App\Models\Repository;
use App\Models\RepositoryFileCache;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PatchCoverageTest extends TestCase
{
    use RefreshDatabase;

    private CoverageReport $report;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $user = User::factory()->withTeams(1)->create();
        $repository = Repository::factory()->forTeam($user->teams->first())->create(['github_url' => 'https://github.com/acme/app']);
        $this->actingAs($user);
        $this->report = CoverageReport::factory()->for($repository)->archived()->withPatchCoverage([
            'status' => 'completed', 'percentage' => 50.0, 'covered_lines' => 1, 'total_lines' => 2,
            'head_sha' => str_repeat('c', 40), 'base_sha' => str_repeat('a', 40), 'base_branch' => 'main',
            'scope' => ['include_patterns' => ['*.php'], 'exclude_patterns' => ['tests/*']],
            'files' => [['path' => 'src/Foo.php', 'status' => 'completed', 'covered_lines' => 1, 'total_lines' => 2, 'percentage' => 50.0]],
        ])->create([
            'branch' => 'feature/x', 'commit_sha' => str_repeat('c', 40), 'diff_status' => 'completed',
            'diff_index_path' => 'coverage/index.jsonl',
            'diff_metadata' => ['scope' => ['include_patterns' => ['*.php'], 'exclude_patterns' => []]],
        ]);
        CoverageFile::factory()->for($this->report)->create(['file_path' => 'src/Foo.php', 'line_coverage_data' => base64_encode(gzcompress(json_encode([
            1 => ['count' => 0, 'covered' => false], 2 => ['count' => 0, 'covered' => false], 3 => ['count' => 1, 'covered' => true],
        ])))]);
        Storage::disk('local')->put($this->report->diff_index_path, json_encode(['path' => 'src/Foo.php', 'previous_path' => null, 'kind' => 'modified', 'additions' => 2, 'added_ranges' => [['start' => 2, 'end' => 3]]])."\n");
        $this->mock(GitHubServiceInterface::class, function ($mock): void {
            $mock->shouldReceive('fetchFileContents')->withArgs(fn ($repository, $sha, $path): bool => $sha === str_repeat('c', 40))->andReturn("uncovered old\n    uncovered new\n    covered new\n");
        });
    }

    public function test_saved_report_summary_links_to_same_report_and_ignores_newer_cache(): void
    {
        CoverageReport::factory()->for($this->report->repository)->create(['branch' => $this->report->branch]);
        RepositoryFileCache::factory()->for($this->report->repository)->create(['branch' => $this->report->branch, 'files' => ['src/Newer.php']]);
        $url = route('dashboard.branch', [$this->report->repository_id, $this->report->branch, 'report' => $this->report->id]);
        $response = $this->get($url)->assertOk();
        $response->assertViewHas('report', fn ($report): bool => $report->id === $this->report->id);
        $response->assertSee('Coverage of changed lines')->assertSee('1 of 2 measured changed executable lines covered');
        $response->assertSee('50.0%')->assertDontSee('Newer.php');
        $response->assertSee('report='.$this->report->id);
    }

    public function test_file_view_distinguishes_uncovered_changed_lines_and_preserves_whitespace(): void
    {
        $response = $this->get(route('dashboard.file', [$this->report->repository_id, $this->report->branch, 'report' => $this->report->id, 'path' => 'src/Foo.php']))->assertOk();
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        $this->assertCount(2, $xpath->query('//main/div[contains(@class,"cc-uncovered-navigation") and contains(@class,"sticky")]/*[@aria-label="Jump to uncovered lines" or @aria-label="Uncovered changed lines"]'));
        $this->assertCount(1, $xpath->query('//tr[@id="L2" and @data-changed="true" and contains(@class,"is-uncovered")]'));
        $this->assertCount(1, $xpath->query('//tr[@id="L1" and @data-changed="false"]'));
        $this->assertSame('    uncovered new', $xpath->query('//tr[@id="L2"]/td[contains(@class,"cc-code")]')->item(0)->textContent);
        $response->assertSee('Next uncovered changed line')->assertSee('Changed executable lines: 1 / 2');
        $response->assertSee('report='.$this->report->id);
    }

    public function test_selector_cannot_cross_repositories_branches_or_teams(): void
    {
        $other = CoverageReport::factory()->create();
        $this->get(route('dashboard.branch', [$this->report->repository_id, $this->report->branch, 'report' => $other->id]))->assertNotFound();
        $this->get(route('dashboard.branch', [$this->report->repository_id, 'other-branch', 'report' => $this->report->id]))->assertNotFound();
        $this->get(route('dashboard.branch', [$other->repository_id, $other->branch, 'report' => $other->id]))->assertForbidden();
    }

    #[DataProvider('displayStates')]
    public function test_states_are_visible_without_fabricated_percentages(string $status, string $message): void
    {
        $this->report->update(['patch_coverage' => ['status' => $status, 'covered_lines' => 0, 'total_lines' => 0, 'files' => []]]);
        $this->get(route('dashboard.branch', [$this->report->repository_id, $this->report->branch, 'report' => $this->report->id]))->assertOk()->assertSee($message);
    }

    public static function displayStates(): array
    {
        return [
            ['awaiting_diff', 'Diff not uploaded'],
            ['awaiting_coverage', 'Waiting for the coverage report'],
            ['pending', 'being calculated'],
            ['incomplete', 'overall percentage is unavailable'],
            ['no_executable_changes', 'No executable changes'],
            ['unavailable', 'unavailable for this report'],
        ];
    }
}
