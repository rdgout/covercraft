<?php

namespace Tests\Feature\Repositories;

use App\Models\Repository;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RepositoryManagementTest extends TestCase
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
        $response = $this->get('/repositories');

        $response->assertOk();
        $response->assertSee('Repositories');
    }

    public function test_index_shows_repositories(): void
    {
        Repository::factory()->forTeam($this->team)->create(['owner' => 'acme', 'name' => 'app']);

        $response = $this->get('/repositories');

        $response->assertOk();
        $response->assertSee('acme/app');
    }

    public function test_create_page_renders(): void
    {
        Http::fake([
            '*/user/repos*' => Http::response([]),
        ]);

        $response = $this->get('/repositories/create');

        $response->assertOk();
        $response->assertSee('Add Repository');
    }

    public function test_create_page_shows_github_repos(): void
    {
        Http::fake([
            '*/user/repos*' => Http::response([
                [
                    'full_name' => 'acme/app',
                    'name' => 'app',
                    'owner' => ['login' => 'acme'],
                    'default_branch' => 'main',
                ],
            ]),
        ]);

        $response = $this->get('/repositories/create');

        $response->assertOk();
        $response->assertSee('acme/app');
    }

    public function test_store_creates_repository(): void
    {
        $response = $this->post('/repositories', [
            'team_id' => $this->team->id,
            'owner' => 'acme',
            'name' => 'app',
            'default_branch' => 'main',
        ]);

        $response->assertRedirect(route('repositories.index'));
        $this->assertDatabaseHas('repositories', [
            'owner' => 'acme',
            'name' => 'app',
            'default_branch' => 'main',
        ]);
    }

    public function test_store_generates_webhook_secret(): void
    {
        $this->post('/repositories', [
            'team_id' => $this->team->id,
            'owner' => 'acme',
            'name' => 'app',
            'default_branch' => 'main',
        ]);

        $repo = Repository::first();
        $this->assertNotNull($repo->webhook_secret);
        $this->assertEquals(40, strlen($repo->webhook_secret));
    }

    public function test_store_stores_correct_default_branch(): void
    {
        $this->post('/repositories', [
            'team_id' => $this->team->id,
            'owner' => 'acme',
            'name' => 'app',
            'default_branch' => 'develop',
        ]);

        $this->assertDatabaseHas('repositories', [
            'owner' => 'acme',
            'name' => 'app',
            'default_branch' => 'develop',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->post('/repositories', []);

        $response->assertSessionHasErrors(['owner', 'name', 'default_branch']);
    }

    public function test_edit_page_renders(): void
    {
        $repo = Repository::factory()->forTeam($this->team)->create();

        $response = $this->get("/repositories/{$repo->id}/edit");

        $response->assertOk();
        $response->assertSee($repo->owner.'/'.$repo->name);
    }

    public function test_update_changes_default_branch(): void
    {
        $repo = Repository::factory()->forTeam($this->team)->create(['default_branch' => 'main']);

        $response = $this->put("/repositories/{$repo->id}", [
            'default_branch' => 'develop',
        ]);

        $response->assertRedirect(route('repositories.index'));
        $this->assertEquals('develop', $repo->fresh()->default_branch);
    }

    public function test_destroy_deletes_repository(): void
    {
        $repo = Repository::factory()->forTeam($this->team)->create();

        $response = $this->delete("/repositories/{$repo->id}");

        $response->assertRedirect(route('repositories.index'));
        $this->assertDatabaseMissing('repositories', ['id' => $repo->id]);
    }

    public function test_fetch_branches_returns_branches(): void
    {
        Http::fake([
            '*/branches*' => Http::response([
                ['name' => 'main'],
                ['name' => 'develop'],
            ]),
        ]);

        $response = $this->postJson('/repositories/branches', [
            'owner' => 'acme',
            'name' => 'app',
        ]);

        $response->assertOk();
        $response->assertJson(['branches' => ['main', 'develop']]);
    }

    public function test_fetch_branches_validates_input(): void
    {
        $response = $this->postJson('/repositories/branches', []);

        $response->assertStatus(422);
    }

    public function test_create_page_handles_missing_github_token(): void
    {
        Http::fake([
            '*/user/repos*' => Http::response('Unauthorized', 401),
        ]);

        $response = $this->get('/repositories/create');

        $response->assertOk();
        $response->assertSee('No GitHub token configured');
    }
}
