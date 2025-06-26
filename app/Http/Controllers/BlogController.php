<?php

namespace App\Http\Controllers;

use App\Jobs\RefreshUserGists;
use App\Models\Gist;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(): View
    {
        $recentGists = Gist::query()
            ->recent()
            ->take(20)
            ->get()
            ->groupBy('username');

        $exampleUsers = ['taylorotwell', 'nunomaduro', 'jessarcher', 'braunson'];

        return view('blog.index', compact('recentGists', 'exampleUsers'));
    }

    public function show(string $username): View
    {
        $userGists = Gist::query()->forUsername($username)->get();

        $firstGist = $userGists->first();
        $isFirstVisit = is_null($firstGist);
        $needsRefresh = $isFirstVisit || $firstGist?->isCacheExpired();

        if ($needsRefresh && ! $this->isJobAlreadyQueued($username)) {
            logger()->debug('Dispatching job to refresh gists for user: '.$username);

            RefreshUserGists::dispatch($username);
            $this->markJobAsQueued($username);
        }

        $gists = collect(); // Empty collection for loading state
        if (! $isFirstVisit) {
            $gists = $userGists->sortByDesc('github_created_at');
        }

        return view('blog.show', compact('gists', 'username', 'isFirstVisit'));
    }

    public function showGist(string $username, string $gistId): View
    {
        $gist = Gist::query()
            ->where('github_id', $gistId)
            ->where('username', $username)
            ->firstOrFail();

        return view('blog.gist', compact('gist'));
    }

    protected function isJobAlreadyQueued(string $username): bool
    {
        return cache()->has("github_sync_job_{$username}");
    }

    protected function markJobAsQueued(string $username): void
    {
        cache()->put("github_sync_job_{$username}", now(), 300);
    }
}
