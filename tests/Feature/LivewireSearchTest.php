<?php

namespace Tests\Feature;

use App\Livewire\SearchFilter;
use App\Models\Gist;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LivewireSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->createTestGists();
    }

    private function createTestGists(): void
    {
        Gist::create([
            'github_id' => 'php1',
            'username' => 'taylor',
            'title' => 'Helper Functions',
            'content' => 'PHP helper functions',
            'language' => 'PHP',
            'description' => 'Useful PHP helpers',
            'github_created_at' => Carbon::parse('2024-01-15'),
            'cached_at' => now(),
        ]);

        Gist::create([
            'github_id' => 'js1',
            'username' => 'taylor',
            'title' => 'Vue Component',
            'content' => 'Vue.js component code',
            'language' => 'JavaScript',
            'description' => 'Reusable Vue component',
            'github_created_at' => Carbon::parse('2024-01-20'),
            'cached_at' => now(),
        ]);

        Gist::create([
            'github_id' => 'php2',
            'username' => 'nuno',
            'title' => 'Laravel Helpers',
            'content' => 'Laravel specific helpers',
            'language' => 'PHP',
            'description' => 'Laravel utilities',
            'github_created_at' => Carbon::parse('2024-02-01'),
            'cached_at' => now(),
        ]);

        Gist::create([
            'github_id' => 'py1',
            'username' => 'alice',
            'title' => 'Python Utils',
            'content' => 'Python utility functions',
            'language' => 'Python',
            'description' => 'Python helpers',
            'github_created_at' => Carbon::parse('2024-01-10'),
            'cached_at' => now(),
        ]);

        Gist::create([
            'github_id' => 'txt1',
            'username' => 'bob',
            'title' => 'Notes',
            'content' => 'Just some notes',
            'language' => null,
            'description' => 'Random notes',
            'github_created_at' => Carbon::parse('2024-01-25'),
            'cached_at' => now(),
        ]);
    }

    #[Test]
    public function component_renders_with_all_gists_initially()
    {
        Livewire::test(SearchFilter::class)
            ->assertSee('taylor')
            ->assertSee('nuno')
            ->assertSee('alice')
            ->assertSee('bob')
            ->assertSee('Helper Functions')
            ->assertSee('Laravel Helpers')
            ->assertSee('Python Utils');
    }

    #[Test]
    public function search_filters_by_username()
    {
        Livewire::test(SearchFilter::class)
            ->set('search', 'taylor')
            ->assertSee('taylor')
            ->assertSee('Helper Functions')
            ->assertSee('Vue Component')
            ->assertDontSee('nuno')
            ->assertDontSee('alice')
            ->assertDontSee('Laravel Helpers');
    }

    #[Test]
    public function search_is_case_insensitive()
    {
        Livewire::test(SearchFilter::class)
            ->set('search', 'TAYLOR')
            ->assertSee('taylor')
            ->assertSee('Helper Functions');

        Livewire::test(SearchFilter::class)
            ->set('search', 'NuNo')
            ->assertSee('nuno')
            ->assertSee('Laravel Helpers');
    }

    #[Test]
    public function search_supports_partial_matching()
    {
        Livewire::test(SearchFilter::class)
            ->set('search', 'tay')
            ->assertSee('taylor')
            ->assertDontSee('nuno');

        Livewire::test(SearchFilter::class)
            ->set('search', 'un')
            ->assertSee('nuno')
            ->assertDontSee('taylor');
    }

    #[Test]
    public function language_filter_works_correctly()
    {
        Livewire::test(SearchFilter::class)
            ->set('language', 'PHP')
            ->assertSee('taylor')
            ->assertSee('nuno')
            ->assertSee('Helper Functions')
            ->assertSee('Laravel Helpers')
            ->assertDontSee('Vue Component')
            ->assertDontSee('Python Utils');
    }

    #[Test]
    public function language_filter_handles_null_values()
    {
        Livewire::test(SearchFilter::class)
            ->set('language', '')
            ->assertSee('taylor')
            ->assertSee('nuno')
            ->assertSee('alice')
            ->assertSee('bob');
    }

    #[Test]
    public function combined_search_and_language_filters()
    {
        Livewire::test(SearchFilter::class)
            ->set('search', 'taylor')
            ->set('language', 'PHP')
            ->assertSee('taylor')
            ->assertSee('Helper Functions')
            ->assertDontSee('Vue Component')
            ->assertDontSee('nuno')
            ->assertDontSee('alice');
    }

    #[Test]
    public function search_returns_empty_for_no_matches()
    {
        Livewire::test(SearchFilter::class)
            ->set('search', 'nonexistent')
            ->assertSee('No gists found matching your criteria')
            ->assertDontSee('taylor')
            ->assertDontSee('nuno');
    }

    #[Test]
    public function language_dropdown_shows_available_languages()
    {
        Livewire::test(SearchFilter::class)
            ->assertSee('All languages')
            ->assertSee('PHP')
            ->assertSee('JavaScript')
            ->assertSee('Python');
    }

    #[Test]
    public function gists_are_grouped_by_username()
    {
        $component = Livewire::test(SearchFilter::class);

        // Verify grouping structure in the response
        $component->assertSee('taylor')
            ->assertSee('(2 gists)'); // Taylor has 2 gists

        $component->assertSee('nuno')
            ->assertSee('(1 gists)'); // Nuno has 1 gist
    }

    #[Test]
    public function component_limits_results_to_20_gists()
    {
        // Create additional gists to test limit
        for ($i = 1; $i <= 25; $i++) {
            Gist::create([
                'github_id' => "extra{$i}",
                'username' => "user{$i}",
                'title' => "File {$i}",
                'content' => "Content {$i}",
                'language' => 'PHP',
                'github_created_at' => now()->subDays($i),
                'cached_at' => now(),
            ]);
        }

        $component = Livewire::test(SearchFilter::class);

        // Should show max 20 gists total
        $response = $component->viewData('gists');
        $totalGists = $response->flatten()->count();
        $this->assertLessThanOrEqual(20, $totalGists);
    }

    #[Test]
    public function gists_are_ordered_by_creation_date_desc()
    {
        Livewire::test(SearchFilter::class)
            ->set('search', 'taylor')
            ->assertSeeInOrder(['Vue Component', 'Helper Functions']); // Vue is newer
    }

    #[Test]
    public function component_shows_max_4_gists_per_user()
    {
        // Create 6 gists for one user
        for ($i = 1; $i <= 6; $i++) {
            Gist::create([
                'github_id' => "many{$i}",
                'username' => 'prolific',
                'title' => "File {$i}",
                'content' => "Content {$i}",
                'language' => 'PHP',
                'github_created_at' => now()->subDays($i),
                'cached_at' => now(),
            ]);
        }

        $component = Livewire::test(SearchFilter::class)
            ->set('search', 'prolific');

        // Should only show 4 gists for this user
        $component->assertSee('File 1')
            ->assertSee('File 2')
            ->assertSee('File 3')
            ->assertSee('File 4')
            ->assertDontSee('File 5')
            ->assertDontSee('File 6');
    }

    #[Test]
    public function real_time_search_updates_on_input_change()
    {
        $component = Livewire::test(SearchFilter::class);

        // Initial state shows all users
        $component->assertSee('taylor')
            ->assertSee('nuno');

        // Type 't' - should show taylor
        $component->set('search', 't')
            ->assertSee('taylor')
            ->assertDontSee('nuno');

        // Clear search - should show all again
        $component->set('search', '')
            ->assertSee('taylor')
            ->assertSee('nuno');
    }

    #[Test]
    public function language_filter_updates_on_selection_change()
    {
        $component = Livewire::test(SearchFilter::class);

        // Initial state shows all languages
        $component->assertSee('PHP')
            ->assertSee('JavaScript');

        // Select PHP only
        $component->set('language', 'PHP')
            ->assertSee('Helper Functions')
            ->assertSee('Laravel Helpers')
            ->assertDontSee('Vue Component');

        // Back to all languages
        $component->set('language', '')
            ->assertSee('Helper Functions')
            ->assertSee('Vue Component');
    }

    #[Test]
    public function component_handles_special_characters_in_search()
    {
        // Create gist with special characters
        Gist::create([
            'github_id' => 'special',
            'username' => 'user-name_123',
            'title' => 'Special File',
            'content' => 'Content',
            'language' => 'PHP',
            'github_created_at' => now(),
            'cached_at' => now(),
        ]);

        Livewire::test(SearchFilter::class)
            ->set('search', 'user-name')
            ->assertSee('user-name_123')
            ->assertSee('Special File');
    }

    #[Test]
    public function component_shows_correct_gist_metadata()
    {
        Livewire::test(SearchFilter::class)
            ->set('search', 'taylor')
            ->assertSee('Helper Functions')
            ->assertSee('PHP')
            ->assertSee('ago'); // Just check for relative time format, not specific text
    }

    #[Test]
    public function component_generates_correct_links()
    {
        $component = Livewire::test(SearchFilter::class)
            ->set('search', 'taylor');

        // Check for correct route generation
        $component->assertSee(route('blog.show', 'taylor'))
            ->assertSee(route('blog.gist', ['taylor', 'php1']));
    }

    #[Test]
    public function empty_search_shows_recent_gists()
    {
        Livewire::test(SearchFilter::class)
            ->set('search', '')
            ->assertSee('nuno') // Most recent
            ->assertSee('bob')
            ->assertSee('taylor')
            ->assertSee('alice'); // Oldest
    }

    #[Test]
    public function component_handles_rapid_input_changes()
    {
        $component = Livewire::test(SearchFilter::class);

        // Simulate rapid typing
        $component->set('search', 't')
            ->set('search', 'ta')
            ->set('search', 'tay')
            ->set('search', 'tayl')
            ->set('search', 'taylo')
            ->set('search', 'taylor')
            ->assertSee('taylor')
            ->assertDontSee('nuno');
    }
}
