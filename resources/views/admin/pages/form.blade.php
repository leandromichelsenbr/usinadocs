@php
    $existingBlocks = $revision ? $revision->blocks->map(fn ($block) => [
        'type' => $block->type,
        'title' => $block->data['title'] ?? '',
        'body' => $block->data['body'] ?? '',
        'code' => $block->data['code'] ?? '',
    ])->all() : [];
    $blocks = old('blocks', $existingBlocks);
@endphp
<form method="post" action="{{ $revision ? route('admin.pages.update', [$page, $revision]) : route('admin.pages.store') }}">
    @csrf
    @if ($revision) @method('PUT') @else
        <p><label for="site_id">Site</label><br><select id="site_id" name="site_id">@foreach ($sites as $site)<option value="{{ $site->id }}" @selected(old('site_id') == $site->id)>{{ $site->name }}</option>@endforeach</select></p>
        <p><label for="slug">Endereço (slug)</label><br><input id="slug" name="slug" value="{{ old('slug') }}" required placeholder="minha-pagina"></p>
    @endif
    <p><label for="language_id">Idioma</label><br><select id="language_id" name="language_id">@foreach ($languages as $language)<option value="{{ $language->id }}" @selected(old('language_id', $revision?->language_id) == $language->id)>{{ $language->native_name }}</option>@endforeach</select></p>
    <p><label for="title">Título</label><br><input id="title" name="title" value="{{ old('title', $revision?->title) }}" required></p>
    <p><label for="summary">Resumo</label><br><textarea id="summary" name="summary" rows="4">{{ old('summary', $revision?->summary) }}</textarea></p>
    <h2>Blocos</h2>
    <p class="meta">Preencha até seis blocos. Para texto e referência, use título e conteúdo; para código, preencha somente o campo de código.</p>
    @foreach (range(0, 5) as $position)
        @php($block = $blocks[$position] ?? [])
        <fieldset class="card" style="margin:16px 0"><legend>Bloco {{ $position + 1 }}</legend>
            <p><label>Tipo</label><br><select name="blocks[{{ $position }}][type]"><option value="">Não usar</option><option value="text" @selected(($block['type'] ?? '') === 'text')>Texto</option><option value="code" @selected(($block['type'] ?? '') === 'code')>Código</option><option value="reference" @selected(($block['type'] ?? '') === 'reference')>Referência</option></select></p>
            <p><label>Título</label><br><input name="blocks[{{ $position }}][title]" value="{{ $block['title'] ?? '' }}"></p>
            <p><label>Conteúdo</label><br><textarea name="blocks[{{ $position }}][body]" rows="4">{{ $block['body'] ?? '' }}</textarea></p>
            <p><label>Código</label><br><textarea name="blocks[{{ $position }}][code]" rows="5">{{ $block['code'] ?? '' }}</textarea></p>
        </fieldset>
    @endforeach
    <p><button class="button" type="submit">{{ $revision ? 'Salvar rascunho' : 'Criar rascunho' }}</button> <a class="button alt" href="{{ route('admin.pages.index') }}">Cancelar</a></p>
</form>
