<?php

namespace App\Services;

use App\Models\Gist;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class GistService
{
    public function getUserGists(string $username): array
    {
        $cacheKey = "gists.{$username}";

        return cache()->remember(
            $cacheKey,
            now()->addHours(4),
            fn () => $this->fetchFromGitHub($username)
        );
    }

    private function fetchFromGitHub(string $username): array
    {
        try {
            $gitHubToken = config('services.github.token');

            throw_if(
                ! $gitHubToken,
                new \InvalidArgumentException('GitHub token is not configured.')
            );

            $response = Http::withToken($gitHubToken)
                ->get("https://api.github.com/users/{$username}/gists");

            if ($response->failed()) {
                return [];
            }

            return $response->json();
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // Handle network timeouts and connection issues
            logger()->warning("GitHub API connection error for user {$username}: ".$e->getMessage());

            return [];
        } catch (\Exception $e) {
            // Handle any other exceptions
            logger()->error("GitHub API error for user {$username}: ".$e->getMessage());

            return [];
        }
    }

    public function syncUserGists(string $username): void
    {
        $gists = $this->getUserGists($username);

        foreach ($gists as $gistData) {
            // Fetch individual gist to get full content
            $fullGist = $this->fetchSingleGist($gistData['id']);
            if ($fullGist) {
                $this->createOrUpdateGist($username, $fullGist);
            }
        }
    }

    private function fetchSingleGist(string $gistId): ?array
    {
        try {
            $response = Http::withToken(config('services.github.token'))
                ->timeout(30)
                ->get("https://api.github.com/gists/{$gistId}");

            return $response->successful() ? $response->json() : null;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            logger()->warning("GitHub API connection error for gist {$gistId}: ".$e->getMessage());

            return null;
        } catch (\Exception $e) {
            logger()->error("GitHub API error for gist {$gistId}: ".$e->getMessage());

            return null;
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
