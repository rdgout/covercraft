<?php

namespace Tests\Feature\Dashboard;

use App\Contracts\GitHubServiceInterface;
use App\Models\CoverageFile;
use App\Models\CoverageReport;
use App\Models\Repository;
use App\Models\RepositoryFileCache;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RedesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_and_recent_reports_are_scoped_to_the_selected_team_and_include_all_pages(): void
    {
        $user = User::factory()->withTeams(2)->create();
        $team = $user->teams->first();
        $repository = Repository::factory()->forTeam($team)->create(['default_branch' => 'main']);
        Repository::factory()->forTeam($team)->count(16)->create();
        CoverageReport::factory()->for($repository)->create(['branch' => 'main', 'coverage_percentage' => 90]);
        CoverageReport::factory()->for($repository)->create(['branch' => 'feature', 'coverage_percentage' => 60]);
        CoverageReport::factory()->for($repository)->archived()->create(['branch' => 'main', 'coverage_percentage' => 10]);
        CoverageReport::factory()->for($repository)->failed()->create(['branch' => 'broken']);
        CoverageReport::factory()->for($repository)->failed()->create(['branch' => 'old-failure', 'created_at' => now()->subDays(2)]);
        $other = Repository::factory()->forTeam($user->teams->last())->create(['name' => 'excluded-repository']);
        CoverageReport::factory()->for($other)->create(['branch' => 'main', 'coverage_percentage' => 0]);
        $foreign = Repository::factory()->create(['name' => 'private-repository']);
        CoverageReport::factory()->for($foreign)->create();

        $response = $this->actingAs($user)->get(route('dashboard', ['team' => $team->id]));

        $response->assertOk()->assertDontSee('excluded-repository')->assertDontSee('private-repository');
        $response->assertViewHas('summary', fn (array $summary): bool => $summary['repository_count'] === 17
            && $summary['branch_count'] === 4 && $summary['on_target_count'] === 1
            && (float) $summary['average_coverage'] === 90.0 && $summary['failed_count'] === 1);
        $response->assertViewHas('repositories', fn ($repositories): bool => $repositories->count() === 15);
        $response->assertSee('team='.$team->id, false);
    }

    public function test_empty_and_pending_reports_do_not_display_invented_coverage(): void
    {
        $user = User::factory()->withTeams(1)->create();
        $repository = Repository::factory()->forTeam($user->teams->first())->create(['default_branch' => 'main']);
        CoverageReport::factory()->for($repository)->pending()->create(['branch' => 'main']);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertViewHas('summary', fn (array $summary): bool => $summary['average_coverage'] === null)
            ->assertSee('Pending')->assertSee('No data');
        $this->get(route('dashboard.repository', $repository))->assertOk()->assertSee('README badge');
        $this->get(route('dashboard.branch', [$repository, 'main']))->assertOk()->assertSee('finishes processing');
    }

    public function test_history_is_ordered_and_limited_for_each_repository(): void
    {
        $user = User::factory()->withTeams(1)->create();
        $repositories = Repository::factory()->forTeam($user->teams->first())->count(2)->create(['default_branch' => 'main']);
        foreach ($repositories as $repository) {
            for ($index = 0; $index < 10; $index++) {
                CoverageReport::factory()->for($repository)->archived()->create(['branch' => 'main', 'coverage_percentage' => 50 + $index, 'created_at' => now()->subDays(10 - $index)]);
            }
            CoverageReport::factory()->for($repository)->create(['branch' => 'main', 'coverage_percentage' => 95]);
        }

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()->assertViewHas('repositories', function ($repositories): bool {
            foreach ($repositories as $repository) {
                $history = $repository->defaultBranchHistory;
                if ($history->count() !== 8 || (float) $history->first()->coverage_percentage !== 95.0) {
                    return false;
                }
            }

            return true;
        });
        $response->assertSee('Coverage trend:');
    }

    public function test_recent_branches_paginate_equal_timestamps_regardless_of_coverage(): void
    {
        $user = User::factory()->withTeams(1)->create();
        $repository = Repository::factory()->forTeam($user->teams->first())->create(['default_branch' => 'main']);
        $createdAt = now()->startOfSecond();
        $reports = CoverageReport::factory()->for($repository)->count(21)->create(['branch' => 'feature', 'coverage_percentage' => 85.25, 'created_at' => $createdAt]);
        $pending = CoverageReport::factory()->for($repository)->pending()->create(['branch' => 'pending', 'created_at' => $createdAt]);
        $expectedIds = $reports->pluck('id')->push($pending->id)->reverse()->values();

        $first = $this->actingAs($user)->get(route('dashboard.repository', $repository))->assertOk();
        $first->assertViewHas('branches', fn ($branches): bool => $branches->pluck('id')->all() === $expectedIds->take(20)->all());
        $second = $this->get($first->viewData('branches')->nextPageUrl())->assertOk();
        $second->assertViewHas('branches', fn ($branches): bool => $branches->pluck('id')->all() === $expectedIds->slice(20)->values()->all());
    }

    public function test_branches_are_ordered_by_report_recency_instead_of_coverage(): void
    {
        $user = User::factory()->withTeams(1)->create();
        $repository = Repository::factory()->forTeam($user->teams->first())->create(['default_branch' => 'main']);
        $olderHighCoverage = CoverageReport::factory()->for($repository)->create([
            'branch' => 'older-high-coverage',
            'coverage_percentage' => 100,
            'created_at' => now()->subDays(2),
        ]);
        $recentPending = CoverageReport::factory()->for($repository)->pending()->create([
            'branch' => 'recent-pending',
            'created_at' => now()->subHour(),
        ]);
        $recentLowCoverage = CoverageReport::factory()->for($repository)->create([
            'branch' => 'recent-low-coverage',
            'coverage_percentage' => 30,
            'created_at' => now()->subDay(),
        ]);
        CoverageReport::factory()->for($repository)->archived()->create(['branch' => 'archived']);

        $response = $this->actingAs($user)->get(route('dashboard.repository', $repository));

        $response->assertOk()->assertSee('Recent branches');
        $response->assertViewHas('branches', fn ($branches): bool => $branches->pluck('id')->all() === [
            $recentPending->id,
            $recentLowCoverage->id,
            $olderHighCoverage->id,
        ]);
        $response->assertSeeInOrder(['recent-pending', 'recent-low-coverage', 'older-high-coverage']);
    }

    public function test_needs_attention_lists_only_the_seven_most_recent_qualifying_reports(): void
    {
        $user = User::factory()->withTeams(1)->create();
        $repository = Repository::factory()->forTeam($user->teams->first())->create(['default_branch' => 'main']);
        $reports = collect();
        $createdAt = now()->startOfSecond();

        for ($index = 0; $index < 10; $index++) {
            $factory = $index % 2 === 0 ? CoverageReport::factory()->failed() : CoverageReport::factory();
            $reports->push($factory->for($repository)->create([
                'branch' => 'attention-'.$index,
                'coverage_percentage' => $index % 2 === 0 ? null : 70,
                'created_at' => $createdAt->copy()->subDays($index === 9 ? 1 : $index + 1),
            ]));
        }

        CoverageReport::factory()->for($repository)->create(['branch' => 'main', 'coverage_percentage' => 95]);
        CoverageReport::factory()->for($repository)->archived()->failed()->create(['branch' => 'archived']);
        $expectedBranches = collect([$reports->last()->branch])->concat($reports->take(6)->pluck('branch'))->all();

        $response = $this->actingAs($user)->get(route('dashboard.repository', $repository));

        $response->assertOk();
        $this->assertSame($expectedBranches, $response->viewData('attentionReports')->pluck('branch')->values()->all());
        $response->assertViewHas('currentReports', fn ($currentReports): bool => $currentReports->count() === 11);

        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $links = (new \DOMXPath($document))->query('//section[.//h2[normalize-space()="Needs attention"]]//a');
        $this->assertCount(7, $links);
        $this->assertSame($expectedBranches, array_map(fn (\DOMNode $link): string => trim($link->textContent), iterator_to_array($links)));
    }

    public function test_file_tree_uses_report_files_without_a_cache_and_all_files_link_keeps_untracked_files(): void
    {
        $user = User::factory()->withTeams(1)->create();
        $repository = Repository::factory()->forTeam($user->teams->first())->create();
        $report = CoverageReport::factory()->for($repository)->create(['branch' => 'main']);
        CoverageFile::factory()->for($report)->create(['file_path' => 'src/Tracked.php']);

        $this->actingAs($user)->get(route('dashboard.branch', [$repository, 'main']))->assertOk()->assertSee('Tracked.php')->assertSee('covered_only=0');
        RepositoryFileCache::factory()->create(['repository_id' => $repository->id, 'branch' => 'main', 'files' => ['src/Untracked.php']]);
        $this->get(route('dashboard.branch', [$repository, 'main', 'covered_only' => 0]))->assertOk()->assertSee('Tracked.php')->assertSee('Untracked.php');
        $this->get(route('dashboard.branch', [$repository, 'main']))->assertOk()->assertDontSee('Untracked.php');
    }

    public function test_source_preserves_only_its_own_whitespace_is_escaped_and_has_navigable_uncovered_lines(): void
    {
        $user = User::factory()->withTeams(1)->create();
        $repository = Repository::factory()->forTeam($user->teams->first())->create();
        $report = CoverageReport::factory()->for($repository)->create(['branch' => 'main']);
        $sourceLines = ['<?php', '    $value = "test";', '', "\treturn \$value;  ", '<script>alert("test")</script>', '?>', ''];
        CoverageFile::factory()->for($report)->create(['file_path' => 'src/File.php', 'line_coverage_data' => base64_encode(gzcompress(json_encode([
            2 => ['covered' => true, 'count' => 3],
            5 => ['covered' => false, 'count' => 0],
        ])))]);
        $this->mock(GitHubServiceInterface::class, function ($mock) use ($sourceLines): void {
            $mock->shouldReceive('fetchFileContents')->once()->andReturn(implode("\n", $sourceLines));
        });

        $response = $this->actingAs($user)->get(route('dashboard.file', [$repository, 'main', 'path' => 'src/File.php']))
            ->assertOk()->assertSee('id="L5"', false)->assertSee('is-uncovered', false)
            ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(', false);

        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $cells = $xpath->query('//table[contains(@class, "cc-source")]//td[@class="cc-code"]');

        $this->assertSame($sourceLines, array_map(fn (\DOMNode $cell): string => $cell->textContent, iterator_to_array($cells)));
        $this->assertCount(1, $xpath->query('//tr[@id="L5" and contains(@class, "is-uncovered")]'));
    }

    public function test_token_validation_keeps_the_form_open_and_retains_old_values(): void
    {
        $user = User::factory()->withTeams(1)->create();
        $name = str_repeat('a', 256);
        $this->actingAs($user)->from(route('tokens.index'))->post(route('tokens.store'), ['name' => $name, 'team_id' => $user->teams->first()->id])
            ->assertRedirect(route('tokens.index'))->assertSessionHasErrors('name');
        $this->get(route('tokens.index'))->assertOk()->assertSee($name)->assertSee('creating: true', false);
    }

    public function test_all_account_and_management_pages_use_the_shared_design(): void
    {
        $user = User::factory()->withTeams(1)->create();
        $repository = Repository::factory()->forTeam($user->teams->first())->create();
        $this->mock(GitHubServiceInterface::class, function ($mock): void {
            $mock->shouldReceive('listUserRepositories')->andReturn([]);
        });
        foreach ([route('repositories.index'), route('repositories.create'), route('repositories.edit', $repository), route('tokens.index'), route('profile.edit')] as $url) {
            $this->actingAs($user)->get($url)->assertOk()->assertSee('cc-main', false)->assertSee('data-theme-toggle', false);
        }
        auth()->logout();
        foreach ([route('login'), route('register'), route('password.request')] as $url) {
            $this->get($url)->assertOk()->assertSee('cc-auth-card', false);
        }
    }
}
