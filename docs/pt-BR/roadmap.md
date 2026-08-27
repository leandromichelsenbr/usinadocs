# Roadmap do Usina Docs

Este roadmap comunica direcao, nao datas fixas de entrega. As prioridades podem mudar conforme o piloto do AdvPL Guia, os casos de uso de documentos controlados e o retorno da comunidade revelem novos requisitos.

## Proximo ciclo de desenvolvimento

1. historico visual de revisoes de paginas, modelos e artefatos, com comparacao e restauracao;
2. deteccao de alteracoes na fonte e traducoes desatualizadas para paginas e artefatos;
3. formato inicial de importacao e exportacao, validado com um conjunto representativo do AdvPL Guia;
4. campos e modelos editoriais necessarios para referencias de funcoes, classes, pontos de entrada e artigos;
5. inventario de URLs, redirecionamentos, metadados SEO e midia do site `advpl.usinabr.com.br`;
6. instalacao de homologacao isolada para o primeiro ensaio de migracao;
7. testes de atualizacao de banco, backup e restauracao antes da primeira Alpha.

## Fase 0 - Fundacao do projeto

- [x] estabelecer o repositorio publico;
- [x] adotar MPL-2.0;
- [x] publicar documentos iniciais de governanca;
- [x] documentar a visao de produto e a arquitetura alvo;
- [x] definir a pilha tecnica inicial e o ambiente suportado (ADR-001);
- [ ] adicionar verificacoes automaticas de segredos, licencas e documentacao;
- [ ] publicar fluxos de contribuicao e release.

## Fase 1 - Nucleo leve e paginas

- [x] bootstrap PHP leve, roteamento e migracoes de banco;
- [x] acesso administrativo inicial, sites e idiomas;
- [x] paginas estruturadas, blocos, rascunhos, publicacao e revisoes imutaveis;
- [x] editor versionado de modelos editoriais;
- [x] editor versionado de artefatos reutilizaveis;
- [x] referencias de artefatos com revisao fixada e mapa de impacto;
- [x] traducoes versionadas de paginas e artefatos em portugues, ingles e espanhol;
- [x] historico visual, comparacao e restauracao de revisoes;
- [x] formato inicial de importacao/exportacao;
- [ ] concluir papeis e permissoes editoriais alem do administrador;
- [ ] concluir modelos representativos de funcao/comando, procedimento controlado e aula.

## Fase 2 - Traducao por bloco

- estados de traducao por bloco e idioma;
- acompanhamento de mudancas na fonte;
- deteccao de traducoes desatualizadas;
- relatorios de revisao e cobertura de traducao;
- rotas localizadas e preferencias de idioma.

## Fase 3 - Midia e referencias

- uploads seguros e revisoes de arquivo;
- metadados de autoria, origem, licenca e acessibilidade;
- miniaturas e carregamento de midia sob demanda;
- citacoes e referencias estruturadas.

## Fase 4 - Documentos controlados

- codigos de documento, responsaveis, revisores, aprovadores e areas;
- validade de versao, ciclos de revisao e alertas de vencimento;
- fluxo de aprovacao e trilha de auditoria;
- ciencia formal para documentos que exigem conhecimento;
- vinculos entre documentos controlados e treinamentos obrigatorios.

## Fase 5 - Modulo de Treinamentos / Aprendizagem nativa

O treinamento passa a ser tratado como modulo de dominio proprio, com fronteira arquitetural explicita e capacidade futura de evoluir de forma relativamente independente do nucleo documental. Ver [Modulo de Treinamentos](modulo-treinamentos.md).

- cursos livres, programas, trilhas, niveis, modulos e aulas;
- reutilizacao de blocos de documentacao em aulas;
- matriculas e retomada de aprendizagem;
- progresso, pontuacao e conquistas;
- exercicios e avaliacoes versionados;
- revisao orientada por erro;
- indicadores agregados de aprendizagem;
- programas progressivos orientados a competencias;
- certificados de conclusao com identificador verificavel em fase posterior;
- integracao com documentos controlados e treinamentos obrigatorios;
- migracao incremental do piloto de treinamento existente no AdvPL Guia.

## Fase 6 - Piloto AdvPL Guia

- importar paginas e aulas representativas;
- importar e validar um curso livre representativo do modulo de treinamentos;
- executar uma instalacao separada de homologacao;
- comparar acessibilidade, SEO, desempenho e traducoes;
- validar matriculas, progresso e redirecionamentos sem quebrar URLs existentes;
- publicar gradualmente com capacidade de retorno imediato.

## Fase 7 - Pilotos de conhecimento geral

- preparar conteudo demonstrativo fora de programacao;
- validar procedimentos e instrucoes de trabalho;
- validar conteudo educacional para ciencias e assuntos tecnicos;
- testar ciencia de documentos, evidencia de treinamento e relatorios.

## Marco Alpha

As versoes Alpha validarao a arquitetura e poderao conter mudancas incompativeis. A primeira Alpha publica deve oferecer uma instalacao ponta a ponta documentada e um fluxo completo de criacao, traducao, publicacao e aprendizagem.

## Marco Beta

A fase Beta comeca quando um testador independente consegue instalar o Usina Docs em um servidor limpo, criar um administrador, publicar conteudo multilingue, criar uma aula, atualizar a instalacao e restaurar um backup seguindo apenas a documentacao publica.

## Marco Stable 1.0

A versao 1.0 exige atualizacoes testadas, backup e restauracao, revisao de seguranca, validacao de acessibilidade, APIs e formatos de exportacao versionados, documentacao completa para administradores e desenvolvedores e instalacoes externas bem-sucedidas.
