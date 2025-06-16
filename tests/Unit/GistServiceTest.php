<?php

namespace Tests\Unit;

use App\Models\Gist;
use App\Services\GistService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Mocks\GitHubApiResponses;
use Tests\TestCase;

class GistServiceTest extends TestCase
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
    public function it_fetches_user_gists_from_github_api()
    {
        Http::fake([
            'api.github.com/users/testuser/gists' => Http::response(
                GitHubApiResponses::gistsList(),
                200
            ),
        ]);

        $gists = $this->gistService->getUserGists('testuser');

        $this->assertCount(3, $gists);
        $this->assertEquals('abc123', $gists[0]['id']);
        $this->assertEquals('Test Laravel Helper Functions', $gists[0]['description']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.github.com/users/testuser/gists' &&
                   $request->hasHeader('Authorization', 'Bearer fake-token');
        });
    }

    #[Test]
    public function it_handles_github_api_errors_gracefully()
    {
        Http::fake([
            'api.github.com/users/nonexistent/gists' => Http::response(
                GitHubApiResponses::apiErrorResponse(),
                404
            ),
        ]);

        $gists = $this->gistService->getUserGists('nonexistent');

        $this->assertEmpty($gists);
    }

    #[Test]
    public function it_handles_rate_limiting()
    {
        Http::fake([
            'api.github.com/users/testuser/gists' => Http::response(
                GitHubApiResponses::rateLimitResponse(),
                403
            ),
        ]);

        $gists = $this->gistService->getUserGists('testuser');

        $this->assertEmpty($gists);
    }

    #[Test]
    public function it_caches_user_gists()
    {
        Http::fake([
            'api.github.com/users/testuser/gists' => Http::response(
                GitHubApiResponses::gistsList(),
                200
            ),
        ]);

        // First call
        $this->gistService->getUserGists('testuser');

        // Second call should use cache
        $this->gistService->getUserGists('testuser');

        // Should only make one HTTP request
        Http::assertSentCount(1);

        // Verify cache key exists
        $this->assertTrue(Cache::has('gists.testuser'));
    }

    #[Test]
    public function it_syncs_user_gists_to_database()
    {
        Http::fake([
            'api.github.com/users/testuser/gists' => Http::response(
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

        $this->gistService->syncUserGists('testuser');

        $this->assertDatabaseCount('gists', 3);

        $gist = Gist::where('github_id', 'abc123')->first();
        $this->assertEquals('testuser', $gist->username);
        $this->assertEquals('helpers.php', $gist->title);
        $this->assertEquals('PHP', $gist->language);
        $this->assertStringContainsString('function format_money', $gist->content);
    }

    #[Test]
    public function it_updates_existing_gists()
    {
        // Create existing gist
        $existingGist = Gist::create([
            'github_id' => 'abc123',
            'username' => 'testuser',
            'title' => 'old-title.php',
            'content' => 'old content',
            'language' => 'PHP',
            'description' => 'Old description',
            'github_created_at' => Carbon::parse('2024-01-15T10:30:00Z'),
            'cached_at' => Carbon::now(),
        ]);

        Http::fake([
            'api.github.com/users/testuser/gists' => Http::response(
                GitHubApiResponses::gistsList(), // All 3 gists
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

        $this->gistService->syncUserGists('testuser');

        // Should now have 3 total gists (1 updated + 2 new)
        $this->assertDatabaseCount('gists', 3);

        $updatedGist = Gist::where('github_id', 'abc123')->first();
        $this->assertEquals('helpers.php', $updatedGist->title);
        $this->assertStringContainsString('function format_money', $updatedGist->content);
    }

    #[Test]
    public function it_handles_gists_without_description()
    {
        Http::fake([
            'api.github.com/users/testuser/gists' => Http::response(
                [GitHubApiResponses::gistsList()[2]], // Gist without description
                200
            ),
            'api.github.com/gists/ghi789' => Http::response(
                GitHubApiResponses::singleGist('ghi789'),
                200
            ),
        ]);

        $this->gistService->syncUserGists('testuser');

        $gist = Gist::where('github_id', 'ghi789')->first();
        $this->assertEquals('', $gist->description);
        $this->assertEquals('untitled.txt', $gist->title);
        $this->assertNull($gist->language);
    }

    #[Test]
    public function it_handles_api_timeouts()
    {
        Http::fake([
            'api.github.com/users/testuser/gists' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
            },
        ]);

        $gists = $this->gistService->getUserGists('testuser');

        $this->assertEmpty($gists);
    }

    #[Test]
    public function it_respects_cache_duration()
    {
        Http::fake([
            'api.github.com/users/testuser/gists' => Http::response(
                GitHubApiResponses::gistsList(),
                200
            ),
        ]);

        // Mock current time
        Carbon::setTestNow(Carbon::parse('2024-01-01 12:00:00'));

        // First call
        $this->gistService->getUserGists('testuser');

        // Move time forward but within cache duration
        Carbon::setTestNow(Carbon::parse('2024-01-01 15:30:00')); // 3.5 hours later

        // Second call should still use cache
        $this->gistService->getUserGists('testuser');

        Http::assertSentCount(1);

        // Move time past cache duration
        Carbon::setTestNow(Carbon::parse('2024-01-01 16:30:00')); // 4.5 hours later

        // Clear the cache to simulate expiration
        Cache::forget('gists.testuser');

        // Third call should make new request
        $this->gistService->getUserGists('testuser');

        Http::assertSentCount(2);
    }
}
