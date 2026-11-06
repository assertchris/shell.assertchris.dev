@props(['title', 'pages', 'currentSlug' => null])

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} - Shell API Docs</title>
    <script>
        (function () {
            try {
                var scheme = localStorage.getItem('color-scheme') || 'system';
                if (scheme === 'dark' || (scheme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark');
                }
            } catch (e) {}
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-background type-content">
    <header class="py-4">
        <div class="mx-auto flex max-w-6xl px-6 items-center gap-3">
            <a href="{{ route('docs.index') }}" class="flex items-center gap-2 no-underline">
                <x-logo />
                <span class="type-heading-secondary">Shell</span>
            </a>
            <div id="scheme-switcher" class="ml-auto inline-flex gap-1 rounded-sm border border-foreground/15 px-1.5 py-1 text-xs">
                <button type="button" data-scheme="light"  class="rounded-sm px-1.5 py-0.5 transition-colors hover:bg-foreground/10">Light</button>
                <button type="button" data-scheme="system" class="rounded-sm px-1.5 py-0.5 transition-colors hover:bg-foreground/10">System</button>
                <button type="button" data-scheme="dark"   class="rounded-sm px-1.5 py-0.5 transition-colors hover:bg-foreground/10">Dark</button>
            </div>
        </div>
    </header>
    <div class="mx-auto flex max-w-6xl px-6 py-10">
        <nav class="w-56 shrink-0 pr-8">
            <ul class="space-y-1">
                @foreach ($pages as $slug => $pageTitle)
                    <li>
                        <a
                            href="{{ route('docs.show', $slug) }}"
                            @class([
                                'type-label no-underline block rounded px-3 py-1.5',
                                'bg-background-secondary text-accent' => $currentSlug === $slug,
                                'hover:text-foreground' => $currentSlug !== $slug,
                            ])
                        >
                            {{ $pageTitle }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
        <article class="article min-w-0 flex-1">
            {{ $slot }}
        </article>
    </div>
</body>
</html>
