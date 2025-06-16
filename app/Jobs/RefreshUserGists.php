<?php

namespace App\Jobs;

use App\Services\GistService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshUserGists implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $username
    ) {}

    public function handle(GistService $gistService): void
    {
        try {
            $gistService->syncUserGists($this->username);
            cache()->forget("github_sync_job_{$this->username}");
        } catch (\Exception $e) {
            cache()->forget("github_sync_job_{$this->username}");
            throw $e;
        }
    }
}
