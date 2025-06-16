<?php

namespace Tests\Feature;

use Tests\TestCase;

use PHPUnit\Framework\Attributes\Test;
use Tests\Mocks\GitHubApiResponses;
use App\Models\Gist;
use App\Jobs\RefreshUserGists;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Carbon\Carbon;

class BlogControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.github.token' => 'fake-token']);
    }

    #[Test]
    public function homepage_displays_recent_gists_and_example_users()
    {
        // Create some test gists
        Gist::create([
            'github_id' => 'gist1',
            'username' => 'user1',
            'title' => 'test1.php',
            'content' => 'content1',
            'github_created_at' => now()->subDays(1),
            'cached_at' => now(),
        ]);

        Gist::create([
            'github_id' => 'gist2',
            'username' => 'user2',
            'title' => 'test2.js',
            'content' => 'content2',
            'github_created_at' => now()->subDays(2),
            'cached_at' => now(),
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('GitHub Gists as Blogs');
        $response->assertSee('taylorotwell');
        $response->assertSee('nunomaduro');
        $response->assertSee('jessarcher');
        $response->assertSee('braunson');
        $response->assertSee('user1');
        $response->assertSee('user2');
    }

    #[Test]
    public function user_blog_page_displays_cached_gists()
    {
        // Create cached gists for user
        Gist::create([
            'github_id' => 'gist1',
            'username' => 'testuser',
            'title' => 'helpers.php',
            'content' => 'function test() { return true; }',
            'language' => 'PHP',
            'description' => 'Test helpers',
            'github_created_at' => now()->subHours(2),
            'cached_at' => now()->subHours(1), // Fresh cache
        ]);

        Gist::create([
            'github_id' => 'gist2',
            'username' => 'testuser',
            'title' => 'utils.js',
            'content' => 'const utils = { test: true };',
            'language' => 'JavaScript',
            'description' => 'Utility functions',
            'github_created_at' => now()->subHours(3),
            'cached_at' => now()->subHours(1),
        ]);

        $response = $this->get('/testuser');

        $response->assertStatus(200);
        $response->assertSee('testuser');
        $response->assertSee('2 gists');
        $response->assertSee('helpers.php');
        $response->assertSee('utils.js');
        $response->assertSee('PHP');
        $response->assertSee('JavaScript');
        $response->assertSee('Test helpers');
        $response->assertSee('function test()');
    }

    #[Test]
    public function user_blog_page_queues_refresh_for_expired_cache()
    {
        Queue::fake();

        // Create gist with expired cache
        Gist::create([
            'github_id' => 'gist1',
            'username' => 'testuser',
            'title' => 'old.php',
            'content' => 'old content',
            'github_created_at' => now()->subDays(1),
            'cached_at' => now()->subHours(5), // Expired cache
        ]);

        $response = $this->get('/testuser');

        $response->assertStatus(200);
        $response->assertSee('old.php'); // Shows existing data

        // Verify refresh job was queued
        Queue::assertPushed(RefreshUserGists::class, function ($job) {
            return $job->username === 'testuser';
        });
    }

    #[Test]
    public function user_blog_page_shows_loading_state_for_new_user()
    {
        Queue::fake();

        $response = $this->get('/newuser');

        $response->assertStatus(200);
        $response->assertSee('Loading');
        $response->assertSee('Refresh Page');

        Queue::assertPushed(RefreshUserGists::class, function ($job) {
            return $job->username === 'newuser';
        });
    }

    #[Test]
    public function user_blog_page_shows_no_posts_message_for_empty_results()
    {
        $this->markTestSkipped('Empty user case handling has not been implemented yet');

        Http::fake([
            'api.github.com/users/emptyuser/gists' => Http::response(
                GitHubApiResponses::emptyGistsList(),
                200
            ),
        ]);

        $response = $this->get('/emptyuser');

        $response->assertStatus(200);
        $response->assertSee('No posts yet');
        $response->assertSee('This user hasn\'t created any public gists');
    }

    #[Test]
    public function individual_gist_page_displays_full_content()
    {
        $gist = Gist::create([
            'github_id' => 'test123',
            'username' => 'testuser',
            'title' => 'example.php',
            'content' => "<?php\n\nfunction example() {\n    return 'Hello World';\n}",
            'language' => 'PHP',
            'description' => 'Example function',
            'github_created_at' => Carbon::parse('2024-01-15 10:30:00'),
            'cached_at' => now(),
        ]);

        $response = $this->get('/testuser/test123');

        $response->assertStatus(200);
        $response->assertSee('example.php');
        $response->assertSee('Example function');
        $response->assertSee('PHP');
        $response->assertSee('By');
        $response->assertSee('Jan 15, 2024');
        $response->assertSee('function example()');
        $response->assertSee('Hello World');
        $response->assertSee('Back to');
    }

    #[Test]
    public function individual_gist_page_returns_404_for_nonexistent_gist()
    {
        $response = $this->get('/testuser/nonexistent');

        $response->assertStatus(404);
    }

    #[Test]
    public function individual_gist_page_handles_gist_without_description()
    {
        $gist = Gist::create([
            'github_id' => 'nodesc123',
            'username' => 'testuser',
            'title' => 'untitled.txt',
            'content' => 'Some content without description',
            'language' => null,
            'description' => null,
            'github_created_at' => now(),
            'cached_at' => now(),
        ]);

        $response = $this->get('/testuser/nodesc123');

        $response->assertStatus(200);
        $response->assertSee('untitled.txt');
        $response->assertDontSee('Description:');
        $response->assertSee('Some content without description');
    }

    #[Test]
    public function gists_are_ordered_by_creation_date_desc()
    {
        $oldGist = Gist::create([
            'github_id' => 'old',
            'username' => 'testuser',
            'title' => 'old.php',
            'content' => 'old',
            'github_created_at' => Carbon::parse('2024-01-01'),
            'cached_at' => now(),
        ]);

        $newGist = Gist::create([
            'github_id' => 'new',
            'username' => 'testuser',
            'title' => 'new.php',
            'content' => 'new',
            'github_created_at' => Carbon::parse('2024-02-01'),
            'cached_at' => now(),
        ]);

        $response = $this->get('/testuser');

        $response->assertStatus(200);

        // Get the response content and check order
        $content = $response->getContent();
        $newPosition = strpos($content, 'new.php');
        $oldPosition = strpos($content, 'old.php');

        $this->assertLessThan($oldPosition, $newPosition);
    }

    #[Test]
    public function routes_handle_usernames_with_special_characters()
    {
        $response = $this->get('/user-name');
        $response->assertStatus(200);

        $response = $this->get('/user_name');
        $response->assertStatus(200);

        $response = $this->get('/user123');
        $response->assertStatus(200);
    }

    #[Test]
    public function page_includes_meta_information()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('<title>', false);
        $response->assertSee('Gist Blog');
    }

    #[Test]
    public function user_blog_includes_github_link()
    {
        Gist::create([
            'github_id' => 'test',
            'username' => 'testuser',
            'title' => 'test.php',
            'content' => 'content',
            'github_created_at' => now(),
            'cached_at' => now(),
        ]);

        $response = $this->get('/testuser');

        $response->assertStatus(200);
        $response->assertSee('https://github.com/testuser');
        $response->assertSee('View on GitHub');
    }
}