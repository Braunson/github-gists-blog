<x-layouts.app :title="$gist->title . ' - @' . $gist->username">
    <div class="space-y-6">
        {{-- Back Button --}}
        <a href="{{ route('blog.show', $gist->username) }}"
           class="inline-flex items-center text-blue-600 hover:text-blue-800">
            ← Back to &#64;{{ $gist->username }}
        </a>

        {{-- Gist Content --}}
        <article class="bg-white rounded-lg shadow">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ $gist->title }}</h1>
                        @if($gist->description)
                            <p class="text-lg text-gray-600">{{ $gist->description }}</p>
                        @endif
                    </div>
                    @if($gist->language)
                        <span class="bg-blue-100 text-blue-800 px-3 py-2 rounded-full font-medium">
                            {{ $gist->language }}
                        </span>
                    @endif
                </div>

                <div class="flex items-center space-x-4 text-sm text-gray-500">
                    <span>By &#64;{{ $gist->username }}</span>
                    <span>•</span>
                    <span>{{ $gist->github_created_at->format('M j, Y') }}</span>
                    <span>•</span>
                    <span>{{ $gist->github_created_at->diffForHumans() }}</span>
                </div>
            </div>

            <div class="p-6">
                <div class="bg-gray-900 rounded-lg p-6 overflow-x-auto">
                    <pre class="text-green-400 text-sm leading-relaxed"><code>{{ $gist->content }}</code></pre>
                </div>
            </div>
        </article>
    </div>
</x-layouts.app>