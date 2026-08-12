<x-layouts.app :title="'Editar '.$revision->title.' — Usina Docs'">
    <p class="eyebrow">Rascunho {{ $revision->number }} · {{ $page->slug }}</p>
    <h1>Editar página</h1>
    @if (session('success'))<p class="card">{{ session('success') }}</p>@endif
    @include('admin.pages.form', ['page' => $page, 'revision' => $revision])
    <form method="post" action="{{ route('admin.pages.publish', [$page, $revision]) }}" style="margin-top:24px">@csrf <button class="button" type="submit">Publicar revisão {{ $revision->number }}</button> <a class="button alt" href="{{ route('admin.pages.index') }}">Voltar à lista</a></form>
</x-layouts.app>
