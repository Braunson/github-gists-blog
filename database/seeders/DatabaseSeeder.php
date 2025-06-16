<?php

namespace Database\Seeders;

use App\Services\GistService;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $service = resolve(GistService::class);
        foreach (['taylorotwell', 'nunomaduro', 'jessarcher', 'freekmurze', 'braunson'] as $username) {
            $service->syncUserGists($username);
        }
    }
}
