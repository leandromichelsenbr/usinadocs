<x-layouts.app title="Administração — Usina Docs">
    <p class="eyebrow">Administração</p>
    <h1>Base administrativa pronta.</h1>
    <p class="lead">Olá, {{ auth()->user()->name }}. O próximo passo criará a interface para administrar páginas, revisões e blocos.</p>
    <form method="post" action="{{ route('logout') }}">@csrf <button class="button alt" type="submit">Encerrar sessão</button></form>
    <section class="grid" aria-label="Próximos recursos">
        <article class="card"><h2>Páginas</h2><p>Criação e organização entrarão na próxima entrega.</p></article>
        <article class="card"><h2>Revisões</h2><p>O núcleo já preserva conteúdo publicado como histórico imutável.</p></article>
        <article class="card"><h2>Idiomas</h2><p>Os idiomas iniciais já existem na base editorial.</p></article>
    </section>
</x-layouts.app>
