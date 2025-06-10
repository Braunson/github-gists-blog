<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gists', function (Blueprint $table) {
            $table->id();
            $table->string('github_id')->unique();
            $table->string('username');
            $table->string('title');
            $table->text('content');
            $table->string('language')->nullable();
            $table->string('description')->nullable();
            $table->timestamp('github_created_at');
            $table->timestamp('cached_at');
            $table->timestamps();

            $table->index(['username', 'cached_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gists');
    }
};
