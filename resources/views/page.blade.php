<x-layouts.app :title="$page['title'].' — Usina Docs'">
    <article class="article">
        <header>
            <p class="eyebrow">{{ $page['status'] }}</p>
            <h1>{{ $page['title'] }}</h1>
            <p class="lead">{{ $page['summary'] }}</p>
            <p class="meta">Atualizado em {{ \Carbon\Carbon::parse($page['updated_at'])->format('d/m/Y') }}</p>
        </header>
        @foreach ($page['blocks'] as $block)
            <section class="block">
                <p class="eyebrow">{{ $block['type'] }}</p>
                <h2>{{ $block['title'] }}</h2>
                @isset($block['body'])<p>{{ $block['body'] }}</p>@endisset
                @isset($block['code'])<pre><code>{{ $block['code'] }}</code></pre>@endisset
            </section>
        @endforeach
    </article>
</x-layouts.app>
