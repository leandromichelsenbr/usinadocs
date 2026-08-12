<x-layouts.app title="Criar página — Usina Docs">
    <p class="eyebrow">Administração editorial</p>
    <h1>Criar página</h1>
    <p class="lead">A página será criada como rascunho. Só ficará pública quando você publicar uma revisão.</p>
    @include('admin.pages.form', ['page' => null, 'revision' => null])
</x-layouts.app>
