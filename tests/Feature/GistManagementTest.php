<?php

namespace Tests\Feature;

use App\Models\Gist;
use App\Services\GistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Mocks\GitHubApiResponses;
use Tests\TestCase;

class GistManagementTest extends TestCase
{
    use RefreshDatabase;

    private GistService $gistService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gistService = app(GistService::class);
        config(['services.github.token' => 'fake-token']);
    }

    #[Test]
    public function end_to_end_gist_fetching_and_storage_workflow()
    {
        Http::fake([
            'api.github.com/users/fulltest/gists' => Http::response(
                GitHubApiResponses::gistsList(),
                200
            ),
            'api.github.com/gists/abc123' => Http::response(
                GitHubApiResponses::singleGist('abc123'),
                200
            ),
            'api.github.com/gists/def456' => Http::response(
                GitHubApiResponses::singleGist('def456'),
                200
            ),
            'api.github.com/gists/ghi789' => Http::response(
                GitHubApiResponses::singleGist('ghi789'),
                200
            ),
        ]);

        // Start with empty database
        $this->assertDatabaseCount('gists', 0);

        // Visit user blog page
        $response = $this->get('/fulltest');

        $response->assertStatus(200);
        $response->assertSee('fulltest');

        // Verify all gists were fetched and stored
        $this->assertDatabaseCount('gists', 3);

        // Verify specific gist data
        $phpGist = Gist::where('github_id', 'abc123')->first();
        $this->assertEquals('fulltest', $phpGist->username);
        $this->assertEquals('helpers.php', $phpGist->title);
        $this->assertEquals('PHP', $phpGist->language);
        $this->assertStringContainsString('function format_money', $phpGist->content);

        // get all cached gists
        $cachedGists = Cache::get('gists.fulltest');

        // Verify cache was set
        $this->assertTrue(Cache::has('gists.fulltest'));

        // Visit individual gist page
        $gistResponse = $this->get('/fulltest/abc123');
        $gistResponse->assertStatus(200);
        $gistResponse->assertSee('helpers.php');
        $gistResponse->assertSee('function format_money');
    }

    #[Test]
    public function cache_invalidation_and_refresh_workflow()
    {
        // Create initial gist data
        $oldGist = Gist::create([
            'github_id' => 'old123',
            'username' => 'cachetest',
            'title' => 'old-version.php',
            'content' => 'old content',
            'language' => 'PHP',
            'description' => 'Old description',
            'github_created_at' => now()->subDays(1),
            'cached_at' => now()->subHours(5), // Expired cache
        ]);

        // Mock updated API response
        Http::fake([
            'api.github.com/users/cachetest/gists' => Http::response([
                [
                    'id' => 'old123',
                    'description' => 'Updated description',
                    'created_at' => now()->subDays(1)->toISOString(),
                    'files' => [
                        'updated-version.php' => [
                            'filename' => 'updated-version.php',
                            'language' => 'PHP',
                            'content' => null,
                        ],
                    ],
                ],
            ], 200),
            'api.github.com/gists/old123' => Http::response([
                'id' => 'old123',
                'description' => 'Updated description',
                'created_at' => now()->subDays(1)->toISOString(),
                'files' => [
                    'updated-version.php' => [
                        'filename' => 'updated-version.php',
                        'language' => 'PHP',
                        'content' => 'updated content with new features',
                    ],
                ],
            ], 200),
        ]);

        // Visit page - should trigger refresh
        $response = $this->get('/cachetest');

        $response->assertStatus(200);

        // Verify old data was updated
        $updatedGist = Gist::where('github_id', 'old123')->first();
        $this->assertEquals('updated-version.php', $updatedGist->title);
        $this->assertEquals('updated content with new features', $updatedGist->content);
        $this->assertEquals('Updated description', $updatedGist->description);
        $this->assertTrue($updatedGist->cached_at->isAfter(now()->subMinute()));
    }

    #[Test]
    public function concurrent_requests_for_same_user_handle_correctly()
    {
        Http::fake([
            'api.github.com/users/concurrent/gists' => Http::response(
                GitHubApiResponses::gistsList(),
                200,
                ['X-RateLimit-Remaining' => '4999']
            ),
            'api.github.com/gists/*' => Http::response(
                GitHubApiResponses::singleGist(),
                200
            ),
        ]);

        // Simulate concurrent requests
        $responses = [];
        for ($i = 0; $i < 3; $i++) {
            $responses[] = $this->get('/concurrent');
        }

        // All should succeed
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }

        // Should not have duplicate gists
        $gists = Gist::where('username', 'concurrent')->get();
        $uniqueIds = $gists->pluck('github_id')->unique();
        $this->assertEquals($gists->count(), $uniqueIds->count());
    }

    #[Test]
    public function database_transaction_integrity_during_sync()
    {
        // Mock API to return error on second gist
        Http::fake([
            'api.github.com/users/transtest/gists' => Http::response(
                GitHubApiResponses::gistsList(),
                200
            ),
            'api.github.com/gists/abc123' => Http::response(
                GitHubApiResponses::singleGist('abc123'),
                200
            ),
            'api.github.com/gists/def456' => Http::response([], 500), // Error
            'api.github.com/gists/ghi789' => Http::response(
                GitHubApiResponses::singleGist('ghi789'),
                200
            ),
        ]);

        $this->gistService->syncUserGists('transtest');

        // Should have partial success - successful gists saved
        $this->assertDatabaseHas('gists', ['github_id' => 'abc123']);
        $this->assertDatabaseMissing('gists', ['github_id' => 'def456']);
        $this->assertDatabaseHas('gists', ['github_id' => 'ghi789']);
    }

    #[Test]
    public function large_gist_content_is_handled_properly()
    {
        $largeContent = str_repeat("// This is a very long line of code\n", 1000);

        Http::fake([
            'api.github.com/users/largetest/gists' => Http::response([
                [
                    'id' => 'large123',
                    'description' => 'Large file test',
                    'created_at' => now()->toISOString(),
                    'files' => [
                        'large-file.js' => [
                            'filename' => 'large-file.js',
                            'language' => 'JavaScript',
                            'content' => null,
                        ],
                    ],
                ],
            ], 200),
            'api.github.com/gists/large123' => Http::response([
                'id' => 'large123',
                'description' => 'Large file test',
                'created_at' => now()->toISOString(),
                'files' => [
                    'large-file.js' => [
                        'filename' => 'large-file.js',
                        'language' => 'JavaScript',
                        'content' => $largeContent,
                    ],
                ],
            ], 200),
        ]);

        $this->gistService->syncUserGists('largetest');

        $gist = Gist::where('github_id', 'large123')->first();
        $this->assertNotNull($gist);
        $this->assertEquals($largeContent, $gist->content);
        $this->assertEquals('large-file.js', $gist->title);
    }

    #[Test]
    public function multiple_users_gists_are_isolated_correctly()
    {
        Http::fake([
            'api.github.com/users/user1/gists' => Http::response([
                GitHubApiResponses::gistsList()[0], // First gist only
            ], 200),
            'api.github.com/users/user2/gists' => Http::response([
                GitHubApiResponses::gistsList()[1], // Second gist only
            ], 200),
            'api.github.com/gists/abc123' => Http::response(
                GitHubApiResponses::singleGist('abc123'),
                200
            ),
            'api.github.com/gists/def456' => Http::response(
                GitHubApiResponses::singleGist('def456'),
                200
            ),
        ]);

        // Sync both users
        $this->gistService->syncUserGists('user1');
        $this->gistService->syncUserGists('user2');

        // Verify correct isolation
        $user1Gists = Gist::where('username', 'user1')->get();
        $user2Gists = Gist::where('username', 'user2')->get();

        $this->assertCount(1, $user1Gists);
        $this->assertCount(1, $user2Gists);
        $this->assertEquals('abc123', $user1Gists->first()->github_id);
        $this->assertEquals('def456', $user2Gists->first()->github_id);
    }

    #[Test]
    public function api_rate_limiting_is_handled_appropriately()
    {
        Http::fake([
            'api.github.com/users/ratelimit/gists' => Http::response(
                GitHubApiResponses::rateLimitResponse(),
                403,
                ['X-RateLimit-Remaining' => '0']
            ),
        ]);

        // Should not throw exception
        $this->gistService->syncUserGists('ratelimit');

        // No gists should be created
        $this->assertDatabaseCount('gists', 0);
    }

    #[Test]
    public function network_timeouts_are_handled_gracefully()
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
        });

        // Should not throw exception
        $gists = $this->gistService->getUserGists('timeout');

        $this->assertEmpty($gists);
        $this->assertDatabaseCount('gists', 0);
    }

    #[Test]
    public function gist_updates_preserve_database_relationships()
    {
        // Create initial gist
        $originalGist = Gist::create([
            'github_id' => 'update123',
            'username' => 'updatetest',
            'title' => 'original.php',
            'content' => 'original content',
            'language' => 'PHP',
            'description' => 'Original',
            'github_created_at' => now()->subDays(1),
            'cached_at' => now()->subHours(5),
        ]);

        $originalId = $originalGist->id;

        Http::fake([
            'api.github.com/users/updatetest/gists' => Http::response([
                [
                    'id' => 'update123',
                    'description' => 'Updated',
                    'created_at' => now()->subDays(1)->toISOString(),
                    'files' => [
                        'updated.php' => [
                            'filename' => 'updated.php',
                            'language' => 'PHP',
                            'content' => null,
                        ],
                    ],
                ],
            ], 200),
            'api.github.com/gists/update123' => Http::response([
                'id' => 'update123',
                'description' => 'Updated',
                'created_at' => now()->subDays(1)->toISOString(),
                'files' => [
                    'updated.php' => [
                        'filename' => 'updated.php',
                        'language' => 'PHP',
                        'content' => 'updated content',
                    ],
                ],
            ], 200),
        ]);

        $this->gistService->syncUserGists('updatetest');

        // Should still have same database ID
        $updatedGist = Gist::where('github_id', 'update123')->first();
        $this->assertEquals($originalId, $updatedGist->id);
        $this->assertEquals('updated.php', $updatedGist->title);
        $this->assertEquals('updated content', $updatedGist->content);
    }
}
