<x-layouts.docs title="Documentation" :pages="$pages">
    <h1>API Documentation</h1>
    <p>Welcome to the Shell API documentation. Choose a topic to get started:</p>
    <ul>
        @foreach ($pages as $slug => $title)
            <li><a href="{{ route('docs.show', $slug) }}">{{ $title }}</a></li>
        @endforeach
    </ul>
</x-layouts.docs>
