<x-layouts.app title="Nova tradução — Usina Docs">
    <p class="eyebrow">Localização</p><h1>Nova tradução</h1><p class="lead">Defina o endereço localizado e comece o rascunho no idioma escolhido.</p>
    <form class="panel" method="post" action="{{ route('admin.pages.translations.store',$page) }}">@csrf
        <p><label for="language_id">Idioma</label><select id="language_id" name="language_id">@foreach($languages as $language)<option value="{{ $language->id }}">{{ $language->native_name }}</option>@endforeach</select></p>
        <p><label for="slug">Endereço (slug)</label><input id="slug" name="slug" required></p><p><label for="title">Título</label><input id="title" name="title" required></p><p><label for="summary">Resumo</label><textarea id="summary" name="summary" rows="4"></textarea></p>
        <p><button class="button">Criar tradução</button></p>
    </form>
</x-layouts.app>
