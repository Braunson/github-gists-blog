<?php

namespace App\Services;

use App\Models\Gist;
use Carbon\Carbon;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class GistService
{
    private const CACHE_HOURS = 4;

    private const BATCH_SIZE = 20;

    private const MAX_CONCURRENT_REQUESTS = 5;

    public function getUserGists(string $username): array
    {
        $cacheKey = "gists.{$username}";

        return cache()->remember(
            $cacheKey,
            now()->addHours(self::CACHE_HOURS),
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
                ->timeout(30)
                ->get("https://api.github.com/users/{$username}/gists");

            if ($response->failed()) {
                return [];
            }

            return $response->json();
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            logger()->warning("GitHub API connection error for user {$username}: ".$e->getMessage());

            return [];
        } catch (\Exception $e) {
            logger()->error("GitHub API error for user {$username}: ".$e->getMessage());

            return [];
        }
    }

    public function syncUserGists(string $username): void
    {
        $gists = $this->getUserGists($username);

        if (empty($gists)) {
            return;
        }

        $fullGists = $this->fetchMultipleGists($gists);
        $this->batchUpdateGists($username, $fullGists);
    }

    private function getExistingGists(string $username): Collection
    {
        return Gist::forUsername($username)
            ->select('github_id', 'cached_at')
            ->get()
            ->keyBy('github_id');
    }

    private function filterGistsToUpdate(array $gists, Collection $existingGists): array
    {
        return collect($gists)->filter(function ($gist) use ($existingGists) {
            $existingGist = $existingGists->get($gist['id']);

            if (! $existingGist) {
                return true;
            }

            return $existingGist->isCacheExpired();
        })->toArray();
    }

    private function fetchMultipleGists(array $gists): array
    {
        $gistIds = collect($gists)->pluck('id')->toArray();
        $gistChunks = array_chunk($gistIds, self::MAX_CONCURRENT_REQUESTS);
        $allGists = [];

        foreach ($gistChunks as $chunk) {
            $responses = Http::pool(fn (Pool $pool) => collect($chunk)->map(fn ($gistId) => $pool->withToken(config('services.github.token'))
                ->timeout(30)
                ->get("https://api.github.com/gists/{$gistId}")
            )
            );

            foreach ($responses as $index => $response) {
                try {
                    if ($response->successful()) {
                        $allGists[] = $response->json();
                    } else {
                        logger()->warning("Failed to fetch gist {$chunk[$index]}: HTTP {$response->status()}");
                    }
                } catch (\Exception $e) {
                    logger()->error("Error processing gist {$chunk[$index]}: ".$e->getMessage());
                }
            }
        }

        return $allGists;
    }

    private function batchUpdateGists(string $username, array $gists): void
    {
        $gistBatches = array_chunk($gists, self::BATCH_SIZE);

        foreach ($gistBatches as $batch) {
            DB::transaction(function () use ($username, $batch) {
                foreach ($batch as $gistData) {
                    $this->createOrUpdateGist($username, $gistData);
                }
            });
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
