@props(['title', 'pages', 'currentSlug' => null])

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} - Shell API Docs</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white text-gray-900">
    <div class="mx-auto flex max-w-6xl px-6 py-10">
        <nav class="w-56 shrink-0 pr-8">
            <a href="{{ route('docs.index') }}" class="mb-4 block text-lg font-bold text-gray-900">API Docs</a>
            <ul class="space-y-1">
                @foreach ($pages as $slug => $pageTitle)
                    <li>
                        <a
                            href="{{ route('docs.show', $slug) }}"
                            @class([
                                'block rounded px-3 py-1.5 text-sm',
                                'bg-blue-50 font-medium text-blue-700' => $currentSlug === $slug,
                                'text-gray-600 hover:text-gray-900' => $currentSlug !== $slug,
                            ])
                        >
                            {{ $pageTitle }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
        <article class="prose prose-blue max-w-none min-w-0 flex-1">
            {{ $slot }}
        </article>
    </div>
</body>
</html>
