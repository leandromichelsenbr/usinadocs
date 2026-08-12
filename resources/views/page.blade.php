<x-layouts.app :title="$revision->title.' — Usina Docs'">
    <article class="article">
        <header>
            <p class="eyebrow">Conteúdo publicado · {{ $revision->language->native_name }}</p>
            <h1>{{ $revision->title }}</h1>
            @if ($revision->summary)<p class="lead">{{ $revision->summary }}</p>@endif
            <p class="meta">Revisão {{ $revision->number }} · Publicada em {{ $revision->published_at->format('d/m/Y') }}</p>
        </header>
        @foreach ($revision->blocks as $block)
            <section class="block">
                <p class="eyebrow">{{ $block->type }}</p>
                @if ($block->type === 'code')
                    <pre><code>{{ $block->data['code'] }}</code></pre>
                @else
                    <h2>{{ $block->data['title'] }}</h2>
                    <p>{{ $block->data['body'] }}</p>
                @endif
            </section>
        @endforeach
    </article>
</x-layouts.app>
