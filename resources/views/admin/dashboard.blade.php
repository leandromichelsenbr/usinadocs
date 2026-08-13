<x-layouts.app title="Administração — Usina Docs">
    <p class="eyebrow">Administração editorial</p>
    <h1>Bom trabalho, {{ auth()->user()->name }}.</h1>
    <p class="lead">Crie conteúdo, conduza revisões e publique versões rastreáveis a partir de uma única área editorial.</p>
    <div class="actions"><a class="button" href="{{ route('admin.pages.index') }}">Administrar páginas</a><form method="post" action="{{ route('logout') }}">@csrf <button class="button alt" type="submit">Encerrar sessão</button></form></div>
    <section class="grid" aria-label="Fundação editorial">
        <article class="card"><p class="eyebrow">Conteúdo</p><h2>Páginas</h2><p>Crie rascunhos e publique revisões pelo navegador.</p></article>
        <article class="card"><p class="eyebrow">Histórico</p><h2>Revisões</h2><p>O núcleo preserva o conteúdo publicado como histórico imutável.</p></article>
        <article class="card"><p class="eyebrow">Alcance</p><h2>Idiomas</h2><p>Os idiomas iniciais já existem na base editorial.</p></article>
    </section>
</x-layouts.app>
