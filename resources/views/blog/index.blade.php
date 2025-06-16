<x-layouts.app title="GitHub Gist Blog - Transform GitHub Gists into Beautiful Blogs">
    <div class="space-y-12">
        {{-- Hero Section --}}
        <div class="text-center">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">
                GitHub Gists as Blogs
            </h1>

            <p class="text-xl text-gray-600 mb-8">
                Transform any GitHub user's gists into a blog interface
            </p>

            {{-- Example Users --}}
            <div class="flex flex-wrap justify-center gap-4 mb-8">
                @foreach($exampleUsers as $user)
                    <a href="{{ route('blog.show', $user) }}"
                       class="bg-blue-100 hover:bg-blue-200 text-blue-800 px-4 py-2 rounded-full transition-colors">
                        {{ $user }}
                    </a>
                @endforeach
            </div>

            {{-- URL Input Demo --}}
            <div class="max-w-md mx-auto">
                <div class="flex rounded-lg shadow-sm">
                    <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm p-2">
                        {{ url('') }}/
                    </span>
                    <input type="text"
                           placeholder="username"
                           class="flex-1 block w-full border p-2 rounded-none rounded-r-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 placeholder-gray-400"
                           onkeypress="if(event.key==='Enter') window.location.href='{{ url('') }}/' + this.value">
                </div>
                <p class="text-xs text-gray-500 mt-2">Press <span class="border-b border-gray-300">Enter</span> to view any user's gist blog</p>
            </div>
        </div>

        {{-- Search Component --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Browse Recent Gists</h2>
            @livewire('search-filter')
        </div>
    </div>
</x-layouts.app>