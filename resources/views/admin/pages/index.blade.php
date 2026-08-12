<x-layouts.app title="Páginas — Administração — Usina Docs">
    <p class="eyebrow">Administração editorial</p>
    <h1>Páginas</h1>
    <p class="lead">Crie, revise e publique conteúdo. Uma publicação sempre preserva a revisão anterior.</p>
    @if (session('success'))<p class="card">{{ session('success') }}</p>@endif
    <p><a class="button" href="{{ route('admin.pages.create') }}">Criar página</a> <a class="button alt" href="{{ route('admin.dashboard') }}">Voltar ao painel</a></p>
    @forelse ($pages as $page)
        <article class="card" style="margin:16px 0">
            <p class="eyebrow">{{ $page->slug }}</p>
            <h2>{{ $page->publishedRevision?->title ?? $page->latestDraft?->title ?? 'Sem título' }}</h2>
            <p class="meta">Publicada: {{ $page->publishedRevision ? 'revisão '.$page->publishedRevision->number : 'ainda não' }} · Rascunho: {{ $page->latestDraft ? 'revisão '.$page->latestDraft->number : 'nenhum' }}</p>
            @if ($page->latestDraft)
                <a class="button" href="{{ route('admin.pages.edit', [$page, $page->latestDraft]) }}">Editar rascunho</a>
            @elseif ($page->publishedRevision)
                <form style="display:inline" method="post" action="{{ route('admin.pages.revisions.store', $page) }}">@csrf <button class="button alt" type="submit">Abrir nova revisão</button></form>
            @endif
            @if ($page->publishedRevision)<a class="button alt" href="{{ route('pages.show', $page->slug) }}">Ver publicação</a>@endif
        </article>
    @empty
        <section class="card"><p>Nenhuma página foi criada ainda.</p></section>
    @endforelse
</x-layouts.app>
