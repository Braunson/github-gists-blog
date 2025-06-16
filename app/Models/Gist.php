<?php

namespace App\Models;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Gist extends Model
{
    protected $fillable = [
        'github_id',
        'username',
        'title',
        'content',
        'language',
        'description',
        'github_created_at',
        'cached_at',
    ];

    protected $casts = [
        'github_created_at' => 'datetime',
        'cached_at' => 'datetime',
    ];

    public function scopeForUsername($query, $username): Builder
    {
        return $query->where('username', $username);
    }

    public function scopeRecent($query): Builder
    {
        return $query->orderBy('github_created_at', 'desc');
    }

    public function isCacheExpired(): bool
    {
        return $this->cached_at->addHours(4)->isPast();
    }
}
