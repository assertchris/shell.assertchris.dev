@props(['title', 'pages', 'currentSlug' => null])

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} - Shell API Docs</title>
    <script>
        (function () {
            var scheme = localStorage.getItem('color-scheme');
            if (scheme === 'dark' || (scheme !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-background type-content">
    <header class="flex items-center gap-3 border-b border-background-secondary px-6 py-4">
        <a href="{{ route('docs.index') }}" class="flex items-center no-underline">
            <x-logo />
        </a>
        <a href="{{ route('docs.index') }}" class="type-heading-secondary no-underline">Shell</a>
        <div id="scheme-switcher" class="ml-auto flex items-center gap-1">
            <button data-scheme="light" class="type-label rounded px-2 py-1 hover:bg-foreground/10">Light</button>
            <button data-scheme="system" class="type-label rounded px-2 py-1 hover:bg-foreground/10">System</button>
            <button data-scheme="dark" class="type-label rounded px-2 py-1 hover:bg-foreground/10">Dark</button>
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
                                'bg-accent/10 text-accent' => $currentSlug === $slug,
                                'hover:text-foreground' => $currentSlug !== $slug,
                            ])
                        >
                            {{ $pageTitle }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
        <article class="article flex flex-col gap-4 min-w-0 flex-1">
            {{ $slot }}
        </article>
    </div>
</body>
</html>
