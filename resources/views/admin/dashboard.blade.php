<x-layouts.app title="Administração — Usina Docs">
    <p class="eyebrow">Administração</p>
    <h1>Base administrativa pronta.</h1>
    <p class="lead">Olá, {{ auth()->user()->name }}. Agora você pode criar páginas, abrir revisões e publicar conteúdo.</p>
    <p><a class="button" href="{{ route('admin.pages.index') }}">Administrar páginas</a></p>
    <form method="post" action="{{ route('logout') }}">@csrf <button class="button alt" type="submit">Encerrar sessão</button></form>
    <section class="grid" aria-label="Próximos recursos">
        <article class="card"><h2>Páginas</h2><p>Crie rascunhos e publique revisões pelo navegador.</p></article>
        <article class="card"><h2>Revisões</h2><p>O núcleo já preserva conteúdo publicado como histórico imutável.</p></article>
        <article class="card"><h2>Idiomas</h2><p>Os idiomas iniciais já existem na base editorial.</p></article>
    </section>
</x-layouts.app>
