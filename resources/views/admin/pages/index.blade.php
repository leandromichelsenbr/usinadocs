<x-layouts.app title="Páginas — Usina Docs">
    <section class="section-heading"><div><p class="eyebrow">Administração editorial</p><h1>Páginas</h1><p class="lead">Acompanhe o estado de publicação por idioma e organize as próximas traduções.</p></div><a class="button" href="{{ route('admin.pages.create') }}">Criar página</a></section>
    <section class="page-list" aria-label="Páginas editoriais">
        @forelse($pages as $page)
            <article class="page-row"><div><h2>{{ $page->slug }}</h2><div class="locale-list">@foreach($page->localizations as $localization)<span>{{ $localization->language->native_name }}</span><span class="status {{ $localization->publishedRevision ? 'live' : 'pending' }}">{{ $localization->publishedRevision ? 'publicada' : 'pendente' }}</span>@if($localization->publishedRevision)<a href="{{ route('pages.show',['locale'=>$localization->language->route_key,'slug'=>$localization->slug]) }}">ver</a>@endif @endforeach</div></div><a class="button alt" href="{{ route('admin.pages.translations.create',$page) }}">Adicionar tradução</a></article>
        @empty
            <div class="panel"><h2>Nenhuma página criada</h2><p class="meta">Comece criando o primeiro rascunho editorial.</p></div>
        @endforelse
    </section>
</x-layouts.app>
