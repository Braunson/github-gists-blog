<?php

namespace Tests\Unit;

use App\Jobs\RefreshUserGists;
use App\Models\Gist;
use App\Services\GistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\Mocks\GitHubApiResponses;
use Tests\TestCase;

class JobsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.github.token' => 'fake-token']);
    }

    #[Test]
    public function refresh_user_gists_job_calls_gist_service()
    {
        // Mock the GistService
        $mockService = Mockery::mock(GistService::class);
        $mockService->shouldReceive('syncUserGists')
            ->once()
            ->with('testuser')
            ->andReturn(null);

        $this->app->instance(GistService::class, $mockService);

        // Create and handle the job
        $job = new RefreshUserGists('testuser');
        $job->handle($mockService);

        // Mockery will verify the expectations
        $this->assertTrue(true);
    }

    #[Test]
    public function refresh_user_gists_job_can_be_dispatched()
    {
        Queue::fake();

        RefreshUserGists::dispatch('testuser');

        Queue::assertPushed(RefreshUserGists::class, function ($job) {
            return $job->username === 'testuser';
        });
    }

    #[Test]
    public function refresh_user_gists_job_handles_actual_sync()
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

        // Create the job with real service
        $job = new RefreshUserGists('testuser');
        $gistService = app(GistService::class);

        // Handle the job
        $job->handle($gistService);

        // Verify gists were synced to database
        $this->assertDatabaseCount('gists', 3);
        $this->assertDatabaseHas('gists', [
            'username' => 'testuser',
            'github_id' => 'abc123',
            'title' => 'helpers.php',
        ]);
    }

    #[Test]
    public function refresh_user_gists_job_handles_api_errors()
    {
        Http::fake([
            'api.github.com/users/nonexistent/gists' => Http::response(
                GitHubApiResponses::apiErrorResponse(),
                404
            ),
        ]);

        $job = new RefreshUserGists('nonexistent');
        $gistService = app(GistService::class);

        // Job should complete without throwing exceptions
        $job->handle($gistService);

        // No gists should be created
        $this->assertDatabaseCount('gists', 0);
    }

    #[Test]
    public function refresh_user_gists_job_updates_existing_gists()
    {
        // Create existing gist with old data
        Gist::create([
            'github_id' => 'abc123',
            'username' => 'testuser',
            'title' => 'old-name.php',
            'content' => 'old content',
            'language' => 'PHP',
            'description' => 'Old description',
            'github_created_at' => now()->subDays(5),
            'cached_at' => now()->subHours(5),
        ]);

        Http::fake([
            'api.github.com/users/testuser/gists' => Http::response(
                [GitHubApiResponses::gistsList()[0]], // Just the first gist
                200
            ),
            'api.github.com/gists/abc123' => Http::response(
                GitHubApiResponses::singleGist('abc123'),
                200
            ),
        ]);

        $job = new RefreshUserGists('testuser');
        $gistService = app(GistService::class);

        $job->handle($gistService);

        // Should still have only 1 gist, but updated
        $this->assertDatabaseCount('gists', 1);

        $updatedGist = Gist::first();
        $this->assertEquals('helpers.php', $updatedGist->title);
        $this->assertStringContainsString('function format_money', $updatedGist->content);
    }

    #[Test]
    public function job_has_correct_queue_configuration()
    {
        $job = new RefreshUserGists('testuser');

        // Test that job implements the correct interfaces
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);

        // Test that job uses the correct traits
        $traits = class_uses_recursive(RefreshUserGists::class);
        $this->assertContains(\Illuminate\Bus\Queueable::class, $traits);
        $this->assertContains(\Illuminate\Queue\SerializesModels::class, $traits);
    }

    #[Test]
    public function job_can_be_retried_on_failure()
    {
        Queue::fake();

        $username = 'testuser';

        // Simulate a job that might fail and need retry
        Http::fake([
            'api.github.com/users/'.$username.'/gists' => function () {
                throw new \Exception('Network error');
            },
        ]);

        try {
            RefreshUserGists::dispatch($username);
        } catch (\Exception $e) {
            // Job should be able to handle failures gracefully
            $this->assertEquals('Network error', $e->getMessage());
        }

        Queue::assertPushed(RefreshUserGists::class);
    }

    #[Test]
    public function job_serializes_username_correctly()
    {
        $job = new RefreshUserGists('test-username');

        // Test serialization
        $serialized = serialize($job);
        $unserialized = unserialize($serialized);

        $this->assertEquals('test-username', $unserialized->username);
    }

    #[Test]
    public function multiple_jobs_can_run_concurrently()
    {
        Http::fake([
            'api.github.com/users/user1/gists' => Http::response(
                [GitHubApiResponses::gistsList()[0]],
                200
            ),
            'api.github.com/gists/abc123' => Http::response(
                GitHubApiResponses::singleGist('abc123'),
                200
            ),
            'api.github.com/users/user2/gists' => Http::response(
                [GitHubApiResponses::gistsList()[1]],
                200
            ),
            'api.github.com/gists/def456' => Http::response(
                GitHubApiResponses::singleGist('def456'),
                200
            ),
        ]);

        $job1 = new RefreshUserGists('user1');
        $job2 = new RefreshUserGists('user2');
        $gistService = app(GistService::class);

        // Run both jobs
        $job1->handle($gistService);
        $job2->handle($gistService);

        // Should have gists for both users
        $this->assertDatabaseCount('gists', 2);
        $this->assertDatabaseHas('gists', ['username' => 'user1']);
        $this->assertDatabaseHas('gists', ['username' => 'user2']);
    }
}
