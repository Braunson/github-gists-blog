<?php

namespace Tests\Unit;

use App\Models\Gist;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GistModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_has_correct_fillable_attributes()
    {
        $fillable = [
            'github_id',
            'username',
            'title',
            'content',
            'language',
            'description',
            'github_created_at',
            'cached_at',
        ];

        $gist = new Gist;
        $this->assertEquals($fillable, $gist->getFillable());
    }

    #[Test]
    public function it_casts_dates_correctly()
    {
        $gist = Gist::create([
            'github_id' => 'test123',
            'username' => 'testuser',
            'title' => 'test.php',
            'content' => 'test content',
            'github_created_at' => '2024-01-15 10:30:00',
            'cached_at' => '2024-01-15 12:00:00',
        ]);

        $this->assertInstanceOf(Carbon::class, $gist->github_created_at);
        $this->assertInstanceOf(Carbon::class, $gist->cached_at);
    }

    #[Test]
    public function for_username_scope_filters_by_username()
    {
        Gist::create([
            'github_id' => 'gist1',
            'username' => 'user1',
            'title' => 'test1.php',
            'content' => 'content1',
            'github_created_at' => now(),
            'cached_at' => now(),
        ]);

        Gist::create([
            'github_id' => 'gist2',
            'username' => 'user2',
            'title' => 'test2.php',
            'content' => 'content2',
            'github_created_at' => now(),
            'cached_at' => now(),
        ]);

        Gist::create([
            'github_id' => 'gist3',
            'username' => 'user1',
            'title' => 'test3.php',
            'content' => 'content3',
            'github_created_at' => now(),
            'cached_at' => now(),
        ]);

        $user1Gists = Gist::forUsername('user1')->get();
        $user2Gists = Gist::forUsername('user2')->get();

        $this->assertCount(2, $user1Gists);
        $this->assertCount(1, $user2Gists);
        $this->assertEquals('user1', $user1Gists->first()->username);
    }

    #[Test]
    public function recent_scope_orders_by_github_created_at_desc()
    {
        $oldGist = Gist::create([
            'github_id' => 'old',
            'username' => 'testuser',
            'title' => 'old.php',
            'content' => 'old content',
            'github_created_at' => Carbon::parse('2024-01-01'),
            'cached_at' => now(),
        ]);

        $newGist = Gist::create([
            'github_id' => 'new',
            'username' => 'testuser',
            'title' => 'new.php',
            'content' => 'new content',
            'github_created_at' => Carbon::parse('2024-02-01'),
            'cached_at' => now(),
        ]);

        $middleGist = Gist::create([
            'github_id' => 'middle',
            'username' => 'testuser',
            'title' => 'middle.php',
            'content' => 'middle content',
            'github_created_at' => Carbon::parse('2024-01-15'),
            'cached_at' => now(),
        ]);

        $orderedGists = Gist::recent()->get();

        $this->assertEquals('new', $orderedGists->first()->github_id);
        $this->assertEquals('middle', $orderedGists->get(1)->github_id);
        $this->assertEquals('old', $orderedGists->last()->github_id);
    }

    #[Test]
    public function is_cache_expired_returns_true_when_cache_is_old()
    {
        Carbon::setTestNow(Carbon::parse('2024-01-01 12:00:00'));

        $expiredGist = Gist::create([
            'github_id' => 'expired',
            'username' => 'testuser',
            'title' => 'expired.php',
            'content' => 'content',
            'github_created_at' => now(),
            'cached_at' => Carbon::parse('2024-01-01 07:00:00'), // 5 hours ago
        ]);

        $freshGist = Gist::create([
            'github_id' => 'fresh',
            'username' => 'testuser',
            'title' => 'fresh.php',
            'content' => 'content',
            'github_created_at' => now(),
            'cached_at' => Carbon::parse('2024-01-01 10:00:00'), // 2 hours ago
        ]);

        $this->assertTrue($expiredGist->isCacheExpired());
        $this->assertFalse($freshGist->isCacheExpired());
    }

    #[Test]
    public function is_cache_expired_returns_false_when_cache_is_fresh()
    {
        Carbon::setTestNow(Carbon::parse('2024-01-01 12:00:00'));

        $gist = Gist::create([
            'github_id' => 'fresh',
            'username' => 'testuser',
            'title' => 'fresh.php',
            'content' => 'content',
            'github_created_at' => now(),
            'cached_at' => Carbon::parse('2024-01-01 09:00:00'), // 3 hours ago
        ]);

        $this->assertFalse($gist->isCacheExpired());
    }

    #[Test]
    public function it_can_combine_scopes()
    {
        Gist::create([
            'github_id' => 'user1_old',
            'username' => 'user1',
            'title' => 'old.php',
            'content' => 'content',
            'github_created_at' => Carbon::parse('2024-01-01'),
            'cached_at' => now(),
        ]);

        Gist::create([
            'github_id' => 'user1_new',
            'username' => 'user1',
            'title' => 'new.php',
            'content' => 'content',
            'github_created_at' => Carbon::parse('2024-02-01'),
            'cached_at' => now(),
        ]);

        Gist::create([
            'github_id' => 'user2_new',
            'username' => 'user2',
            'title' => 'newest.php',
            'content' => 'content',
            'github_created_at' => Carbon::parse('2024-03-01'),
            'cached_at' => now(),
        ]);

        $user1RecentGists = Gist::forUsername('user1')->recent()->get();

        $this->assertCount(2, $user1RecentGists);
        $this->assertEquals('user1_new', $user1RecentGists->first()->github_id);
        $this->assertEquals('user1_old', $user1RecentGists->last()->github_id);
    }

    #[Test]
    public function it_handles_null_values_gracefully()
    {
        $gist = Gist::create([
            'github_id' => 'test',
            'username' => 'testuser',
            'title' => 'test.txt',
            'content' => 'content',
            'language' => null,
            'description' => null,
            'github_created_at' => now(),
            'cached_at' => now(),
        ]);

        $this->assertNull($gist->language);
        $this->assertNull($gist->description);
    }

    #[Test]
    public function it_has_correct_database_indexes()
    {
        if (\DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('Index testing not supported on SQLite in this test environment');

            return;
        }

        // This tests that the migration includes the expected indexes
        $indexes = \DB::select("
            SELECT indexname, indexdef
            FROM pg_indexes
            WHERE tablename = 'gists'
        ");

        $indexNames = collect($indexes)->pluck('indexname')->toArray();

        // Should have primary key and our custom index
        $this->assertContains('gists_pkey', $indexNames);
        $this->assertTrue(
            collect($indexes)->contains(function ($index) {
                return str_contains($index->indexdef, 'username') &&
                       str_contains($index->indexdef, 'cached_at');
            })
        );
    }
}
