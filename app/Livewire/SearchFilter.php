<?php

namespace App\Livewire;

use App\Models\Gist;
use Livewire\Component;

class SearchFilter extends Component
{
    public $search = '';
    public $language = '';

    public function render()
    {
        $gists = Gist::query()
            ->when($this->search, fn($q) => $q->where('username', 'like', "%{$this->search}%"))
            ->when($this->language, fn($q) => $q->where('language', $this->language))
            ->recent()
            ->take(20)
            ->get()
            ->groupBy('username');

        $languages = Gist::query()
            ->distinct()
            ->pluck('language')
            ->filter();

        return view('livewire.search-filter', compact('gists', 'languages'));
    }
}