<x-layouts.app title="Usina Docs — pré-alfa">
    <p class="eyebrow">Fundação 0.1.0-alpha</p>
    <h1>Uma fonte de conhecimento.<br>Documentação e aprendizado.</h1>
    <p class="lead">Usina Docs é uma plataforma aberta para estruturar, revisar, traduzir, publicar e ensinar conteúdo sem duplicar o trabalho editorial.</p>
    <div class="actions"><a class="button" href="{{ route('pages.show', ['locale' => 'pt', 'slug' => 'bem-vindo']) }}">Conhecer a demonstração</a><a class="button alt" href="https://github.com/leandromichelsenbr/usinadocs">Ver o repositório</a></div>
    <section class="grid" aria-label="Princípios do Usina Docs">
        <article class="card"><p class="eyebrow">01</p><h2>Estruturado</h2><p>Páginas organizadas em blocos com intenção editorial clara.</p></article>
        <article class="card"><p class="eyebrow">02</p><h2>Rastreável</h2><p>Revisões, fontes, licenças e traduções fazem parte do conteúdo.</p></article>
        <article class="card"><p class="eyebrow">03</p><h2>Reutilizável</h2><p>O mesmo conhecimento poderá apoiar leitura, aulas e atividades.</p></article>
    </section>
</x-layouts.app>
