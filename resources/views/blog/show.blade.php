<x-layouts.app :title="'@' . $username . ' - GitHub Gist Blog'">
    <div class="space-y-8">
        <div class="flex justify-between items-center">
            {{-- Back to Home Link --}}
            <a href="{{ route('home') }}"
               class="font-medium text-gray-500 hover:text-blue-600 transition-colors">
                ← Go back home
            </a>
            <div class="text-gray-500 text-sm">
                <span>Powered by</span>
                <a href="{{ route('home') }}"
                   class="text-gray-500 hover:text-blue-800 font-medium border-b border-gray-300 hover:border-blue-800 transition-colors">
                    GitHub Gist Blog
                </a>
            </div>
        </div>
        {{-- Header --}}
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">&#64;{{ $username }}</h1>
                    <p class="text-gray-600">{{ $gists->count() }} gists</p>
                </div>
                <a href="https://github.com/{{ $username }}"
                   target="_blank"
                   class="bg-gray-900 text-white px-4 py-2 rounded-lg hover:bg-gray-800 transition-colors">
                    View on GitHub
                </a>
            </div>
        </div>

        {{-- Gists --}}
        @if($isFirstVisit && $gists->isEmpty())
            <div class="bg-white rounded-lg shadow p-12 text-center">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Loading &#64;{{ $username }}'s gists...</h2>
                <p class="text-gray-600 mb-4">Fetching public gists from GitHub API</p>
                <button onclick="window.location.reload()"
                        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                    Refresh Page
                </button>
                <script>
                    // Auto-refresh every 3 seconds until content loads
                    setTimeout(() => window.location.reload(), 3000);
                </script>
            </div>
        @elseif($gists->isEmpty())
            <div class="bg-white rounded-lg shadow p-12 text-center">
                <div class="text-gray-400 text-6xl mb-4">📝</div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">No posts yet</h2>
                <p class="text-gray-600">This user hasn't created any public gists.</p>
            </div>
        @else
            <div class="grid gap-6">
                @foreach($gists as $gist)
                    <article class="bg-white rounded-lg shadow hover:shadow-md transition-shadow overflow-hidden">
                        <div class="p-6">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex-1">
                                    <h2 class="text-xl font-semibold text-gray-900 mb-2">
                                        <a href="{{ route('blog.gist', [$username, $gist->github_id]) }}"
                                           class="hover:text-blue-600 transition-colors">
                                            {{ $gist->title }}
                                        </a>
                                    </h2>
                                    @if($gist->description)
                                        <p class="text-gray-600 mb-3">{{ $gist->description }}</p>
                                    @endif
                                </div>
                                @if($gist->language)
                                    <span class="bg-gray-100 text-gray-800 text-sm px-3 py-1 rounded-full">
                                        {{ $gist->language }}
                                    </span>
                                @endif
                            </div>

                            <div class="bg-gray-50 rounded-lg p-4 mb-4">
                                <pre class="text-sm text-gray-700 overflow-x-auto"><code>{{ Str::limit($gist->content, 200) }}</code></pre>
                            </div>

                            <div class="flex items-center justify-between text-sm text-gray-500">
                                <span>{{ $gist->github_created_at->diffForHumans() }}</span>
                                <a href="{{ route('blog.gist', [$username, $gist->github_id]) }}"
                                   class="text-blue-600 hover:text-blue-800 font-medium border-b border-blue-600 hover:border-blue-800 transition-colors">
                                    Read more →
                                </a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.app>