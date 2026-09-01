# Backlog editorial e de confianca

Este documento organiza as conclusoes da analise de conteudo people-first e E-E-A-T aplicada ao Usina Docs e as acoes para transformar experiencia tecnica real em sinais visiveis de autoria, metodo, evidencia e confianca.

Diretriz central: antes de aumentar agressivamente o volume de publicacoes, o produto deve estabelecer uma camada editorial de confianca que passe a fazer parte de cada novo conteudo.

## Principios aprovados

- produzir conteudo para necessidades reais da comunidade, nao para perseguir volume de busca;
- tratar o Usina Docs como base tecnica independente de conhecimento, nao como fabrica de artigos;
- tornar explicitos autor, metodo de producao, fontes e nivel de validacao;
- distinguir publicacao de revisao tecnica, sem alterar datas apenas para transmitir atualidade;
- transformar exemplos de codigo em conhecimento contextualizado, com objetivo, pre-condicoes, resultado esperado e erros comuns;
- privilegiar investigacoes, casos reais, comportamento observado e analise original;
- permitir IA como apoio de estruturacao, com revisao humana, transparencia proporcional e valor tecnico proprio;
- manter publicos os conteudos priorizados por solicitacao ou patrocinio, indicando sua origem comunitaria.

## Prioridades

### P0 - Modelo editorial de confianca

- [ ] definir campos estruturados de autoria por pagina;
- [ ] criar pagina de autor com experiencia, especialidades e conteudos publicados;
- [ ] registrar separadamente data de publicacao e data da ultima revisao tecnica;
- [ ] modelar ambiente e versao em que o conteudo foi validado;
- [ ] modelar fontes e referencias tecnicas estruturadas;
- [ ] modelar declaracao de metodo: como o conteudo foi produzido, testado e revisado;
- [ ] definir quando o uso de IA ou automacao exige divulgacao;
- [ ] incluir os campos nos modelos de funcao/comando, classe, ponto de entrada, artigo, guia e caso real;
- [ ] preservar autoria, metodo e evidencias em revisoes, traducoes, importacao e exportacao.

### P0 - Controle de Qualidade USINA

Criar uma linguagem visual propria, grafica e ludica, derivada da identidade da USINA. Os selos devem comunicar rigor tecnico sem parecer certificacao corporativa generica.

#### Taxonomia em dois eixos

**Evidencia**

- **Documentado**: sustentado por documentacao ou referencias identificadas;
- **Testado**: executado e conferido em ambiente informado;
- **Em Producao**: utilizado em situacao real, com experiencia direta de uso.

**Estado**

- **Experimental**: comportamento, hipotese ou solucao ainda sujeito a validacao ou mudanca;
- **Legado**: aplicavel a versao, tecnologia ou contexto anterior.

Os selos nao sao mutuamente exclusivos. Combinacoes validas incluem Documentado + Testado + Em Producao, Testado + Experimental e Testado + Legado.

- [ ] definir o tratamento de **Nao testado** como indicador explicito de transparencia, sem confundi-lo com evidencia positiva;
- [ ] documentar regras de atribuicao, combinacoes permitidas, conflitos e remocao;
- [ ] definir quem pode atribuir cada selo e quais evidencias devem ser registradas;
- [ ] manter historico das alteracoes de classificacao em cada revisao;
- [ ] impedir combinacoes incoerentes, como Nao testado junto de Testado ou Em Producao.

#### Sistema visual

Metafora aprovada: **estado da peca em uma oficina/laboratorio USINA**.

- [ ] criar conceito visual comum derivado da geometria do logotipo;
- [ ] desenhar **Documentado** como manual ou prancheta conferida;
- [ ] desenhar **Testado** como modulo em bancada de testes;
- [ ] desenhar **Em Producao** como modulo integrado a engrenagem ou maquina em funcionamento;
- [ ] desenhar **Experimental** como prototipo ou frasco de laboratorio;
- [ ] desenhar **Legado** com linguagem retro, como computador CRT ou disquete;
- [ ] desenhar **Nao testado**, caso aprovado, como modulo ainda lacrado;
- [ ] criar versao completa, com icone e rotulo, para o cabecalho da pagina;
- [ ] criar versao compacta para cards, busca, listagens e conteudo relacionado;
- [ ] preparar ativos em SVG, com grade, espessura, paleta e area de respiro consistentes;
- [ ] validar legibilidade em tamanhos pequenos, temas claro/escuro e telas de alta densidade;
- [ ] evitar dependencia exclusiva de cor ou forma para transmitir significado.

#### Interacao e acessibilidade

- [ ] exibir explicacao objetiva ao clicar ou passar sobre cada selo;
- [ ] oferecer alternativa acessivel a interacoes que dependam de hover;
- [ ] incluir nome e significado textual para leitores de tela;
- [ ] definir ordem, contraste, foco por teclado e comportamento em dispositivos moveis;
- [ ] incluir link para a pagina publica **Como classificamos nossos conteudos**;
- [ ] permitir filtragem por evidencia e estado sem prejudicar navegacao ou SEO.

### P1 - Pagina de metodologia editorial

- [ ] criar a rota publica /metodologia;
- [ ] explicar os criterios Who, How e Why adotados pelo projeto;
- [ ] publicar significado e requisitos de cada selo;
- [ ] explicar testes, revisao tecnica, fontes e tratamento de conteudo experimental ou legado;
- [ ] explicar o papel de IA e automacao no fluxo editorial;
- [ ] explicar publicacao, revisao tecnica e politica de atualizacao de datas;
- [ ] explicar como solicitacoes e desenvolvimento patrocinado influenciam prioridades sem restringir o acesso.

### P1 - Estrutura das paginas tecnicas

- [ ] adicionar byline com link para o autor;
- [ ] adicionar bloco **Sobre este conteudo**;
- [ ] exibir selos, versao, ambiente, data de publicacao e revisao tecnica;
- [ ] padronizar objetivo, pre-condicoes, codigo, resultado esperado e erros comuns;
- [ ] adicionar referencias e conteudos relacionados;
- [ ] diferenciar visualmente referencia, guia, artigo, caso real e conteudo experimental;
- [ ] incluir dados estruturados compativeis com autoria e datas reais, quando aplicavel.

### P1 - Conteudo original e experiencia direta

- [ ] priorizar pautas derivadas de problemas reais, investigacoes e comportamentos observados;
- [ ] criar modelo editorial para estudos de caso e diagnosticos;
- [ ] registrar evidencias reproduziveis sem expor dados confidenciais de clientes;
- [ ] revisar paginas de snippets para adicionar contexto, limitacoes e criterios de uso;
- [ ] identificar conteudos antigos que devem receber o estado **Legado**;
- [ ] selecionar conjunto piloto representativo antes da migracao em massa.

### P2 - Solicitacoes e desenvolvimento patrocinado

- [ ] criar fluxo de solicitacao de conteudo pela comunidade;
- [ ] permitir votos ou demonstracao de interesse;
- [ ] modelar patrocinio como mecanismo opcional de priorizacao;
- [ ] informar que patrocinio nao compra conclusao tecnica nem exclusividade;
- [ ] identificar conteudos originados da comunidade ou priorizados por patrocinio;
- [ ] manter o conteudo final publicamente acessivel;
- [ ] medir demanda, conversao, prazo e satisfacao sem orientar a pauta apenas por trafego.

### P2 - Relacionamentos e descoberta

- [ ] modelar relacoes entre funcoes, classes, pontos de entrada, exemplos, artigos, versoes e casos reais;
- [ ] usar relacionamentos em busca, navegacao e recomendacoes internas;
- [ ] permitir descoberta por problema, tecnologia, versao, tipo de conteudo, evidencia e estado;
- [ ] preservar URLs e preparar redirecionamentos durante a migracao do AdvPL Guia.

## Dependencias

1. fechar taxonomia, regras editoriais e modelo de dados;
2. criar identidade visual e ativos acessiveis;
3. implementar componentes de exibicao e interacao;
4. publicar a metodologia;
5. aplicar o padrao a um conjunto piloto;
6. validar consistencia editorial, acessibilidade, SEO e desempenho;
7. migrar gradualmente os demais conteudos.

## Criterios de aceite da frente

- toda pagina do piloto identifica claramente quem criou ou revisou o conteudo;
- os selos exibidos possuem evidencia e regra de atribuicao registradas;
- evidencia e estado sao armazenados separadamente;
- combinacoes incoerentes sao bloqueadas;
- versao, ambiente, fontes e metodo aparecem quando aplicaveis;
- publicacao e revisao tecnica possuem datas independentes e auditaveis;
- o significado dos selos e compreensivel sem depender apenas do grafismo;
- componentes funcionam com teclado, leitor de tela, toque e temas claro/escuro;
- importacao, exportacao, revisoes e traducoes preservam os metadados;
- o conjunto piloto demonstra ganho de clareza e confianca antes da expansao.

## Fora de escopo imediato

- produzir conteudo em massa para aumentar artificialmente a quantidade de paginas;
- alterar datas sem revisao tecnica substancial;
- usar selos como alegacoes de certificacao externa;
- publicar automaticamente conteudo gerado por IA sem revisao e responsabilidade editorial;
- transformar patrocinio em paywall ou compra de resultado favoravel.
