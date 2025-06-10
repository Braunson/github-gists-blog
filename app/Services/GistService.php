<?php

namespace App\Services;

use App\Models\Gist;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class GistService
{
    public function getUserGists(string $username): array
    {
        $cacheKey = "gists.{$username}";

        return cache()->remember(
            $cacheKey,
            now()->addHours(4),
            fn() => $this->fetchFromGitHub($username)
        );
    }

    private function fetchFromGitHub(string $username): array
    {
        $response = Http::withToken(config('services.github.token'))
            ->get("https://api.github.com/users/{$username}/gists");

        if ($response->failed()) {
            return [];
        }

        return $response->json();
    }

    public function syncUserGists(string $username): void
    {
        $gists = $this->fetchFromGitHub($username);

        foreach ($gists as $gistData) {
            $this->createOrUpdateGist($username, $gistData);
        }
    }

    private function createOrUpdateGist(string $username, array $gistData): void
    {
        $firstFile = collect($gistData['files'])->first();

        Gist::updateOrCreate(
            ['github_id' => $gistData['id']],
            [
                'username' => $username,
                'title' => $firstFile['filename'] ?? 'Untitled',
                'content' => $firstFile['content'] ?? '',
                'language' => $firstFile['language'] ?? null,
                'description' => $gistData['description'] ?? '',
                'github_created_at' => Carbon::parse($gistData['created_at']),
                'cached_at' => now(),
            ]
        );
    }
}