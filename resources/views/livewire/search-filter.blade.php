<div class="space-y-6">
    {{-- Filters --}}
    <div class="flex flex-col sm:flex-row gap-4">
        <div class="flex-1">
            <input wire:model.live="search"
                   type="text"
                   placeholder="Search by username..."
                   class="w-full rounded-lg border border-gray-200 p-2 focus:border-blue-500 focus:ring-blue-500 placeholder-gray-400">
        </div>
        <div class="sm:w-48">
            <select wire:model.live="language"
                    class="w-full rounded-lg border border-gray-200 p-2 focus:border-blue-500 focus:ring-blue-500">
                <option value="">All languages</option>
                @foreach($languages as $lang)
                    <option value="{{ $lang }}">{{ $lang }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Results --}}
    @if($gists->isEmpty())
        <p class="text-gray-500 text-center py-8">No gists found matching your criteria.</p>
    @else
        <div class="grid gap-6">
            @foreach($gists as $username => $userGists)
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="font-semibold text-lg mb-3">
                        <a href="{{ route('blog.show', $username) }}" class="text-blue-600 hover:text-blue-800">
                            &#64;{{ $username }}
                        </a>
                        <span class="text-sm text-gray-500 font-normal">({{ $userGists->count() }} gists)</span>
                    </h3>
                    <div class="grid sm:grid-cols-2 gap-3">
                        @foreach($userGists->take(4) as $gist)
                            <a href="{{ route('blog.gist', [$username, $gist->github_id]) }}"
                               class="block p-3 bg-white rounded border border-gray-200 hover:shadow-sm transition-shadow">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="font-medium text-sm text-gray-900">{{ Str::limit($gist->title, 30) }}</span>
                                    @if($gist->language)
                                        <span class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded">{{ $gist->language }}</span>
                                    @endif
                                </div>
                                <p class="text-xs text-gray-500">{{ $gist->github_created_at->diffForHumans() }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>