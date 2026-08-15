# Usina Docs

> [!WARNING]
> O Usina Docs esta em **pre-alpha**. A primeira base instalavel esta disponivel apenas para avaliacao local e ainda nao esta pronta para uso em producao.

**Usina Docs** e uma plataforma open source para gestao de conhecimento, documentacao controlada e treinamento. Ela ajuda equipes a criar, estruturar, traduzir, revisar, publicar, ensinar e acompanhar a compreensao de conteudos tecnicos, operacionais e educacionais. O projeto e mantido pela **Usina.BR Tecnologia e Informacao Ltda. ME**.

O principio central e simples: documentacao e treinamento compartilham a mesma base de conhecimento estruturada. Um conceito, procedimento, instrucao, exemplo, imagem ou referencia pode ser publicado para consulta e reutilizado em aulas, revisoes, avaliacoes e evidencias de treinamento sem duplicacao editorial.

## Estado do projeto

O repositorio contem a licenca, documentos de governanca, visao de produto, planejamento de arquitetura e uma prova de conceito Laravel arquivada. A direcao ativa e um nucleo PHP leve; consulte a [ADR-001](docs/ADR-001-LIGHTWEIGHT-PHP-CORE.md), a [especificacao do pacote de conteudo](docs/CONTENT-PACKAGE.md) e o [roadmap](docs/pt-BR/roadmap.md).

O AdvPL Guia e o primeiro caso real de uso e o laboratorio onde os requisitos do produto estao sendo validados. O Usina Docs permanecera independente de conceitos especificos de AdvPL, Protheus e TOTVS.

O objetivo de longo prazo e mais amplo que documentacao de programacao. O Usina Docs esta sendo projetado para apoiar bases de conhecimento, procedimentos operacionais padrao, instrucoes de trabalho, documentos controlados, manuais tecnicos, treinamento interno, integracao de novos colaboradores, educacao continuada e assuntos como programacao, eletronica, quimica, fisica, manutencao, qualidade e seguranca.

Consulte a [visao do projeto](docs/pt-BR/vision.md), os [casos de uso e modelos de conteudo](docs/pt-BR/use-cases-and-models.md), a [arquitetura alvo](docs/target-architecture.md) e o [roadmap](docs/pt-BR/roadmap.md).

## Previa do nucleo leve

O nucleo leve instalavel esta sendo construido em [`core/`](core/). Ele fica propositalmente isolado do prototipo Laravel arquivado enquanto o projeto valida a nova arquitetura.

Atualmente ele renderiza uma pagina de exemplo publicada, em portugues, a partir do SQLite. Consulte [`core/README.md`](core/README.md) para os passos de instalacao local e validacao.

## Capacidades planejadas

- modelos de pagina estruturados e blocos de conteudo reutilizaveis;
- metadados de controle documental, como codigo, responsavel, aprovador, versao, vigencia e data de revisao;
- historico completo de revisoes, comparacao e restauracao;
- status de traducao e revisao por bloco;
- biblioteca de midia com autoria, origem e licenciamento;
- fluxos editoriais e permissoes;
- ciencia, consentimento e evidencia de treinamento para documentos que exigem conhecimento formal;
- catalogos pesquisaveis, taxonomias e relacionamentos;
- cursos, trilhas, aulas e avaliacoes nativas;
- progresso, conquistas e revisao orientada por erro;
- formatos abertos de importacao, exportacao e backup;
- temas e pontos de extensao.

## Open source

O codigo-fonte oficial do Usina Docs permanecera publicamente disponivel.

O projeto pode ser usado para fins pessoais ou comerciais, estudado, modificado, redistribuido e integrado a outras solucoes, inclusive software proprietario, conforme os termos da **Mozilla Public License 2.0 (MPL-2.0)**.

A Usina.BR esta comprometida em manter o projeto oficial e sua base principal de codigo como open source. Consulte [OPEN_SOURCE_PLEDGE.md](OPEN_SOURCE_PLEDGE.md).

## Licenca

O Usina Docs e licenciado sob a **Mozilla Public License 2.0 (MPL-2.0)**. Consulte [LICENSE](LICENSE) para o texto completo.

Copyright © 2026 Usina.BR Tecnologia e Informacao Ltda. ME.

A licenca cobre o codigo-fonte, nao os nomes Usina.BR ou Usina Docs, logotipos e identidade visual oficial. Consulte [TRADEMARKS.md](TRADEMARKS.md).

## Contribuicao

Contribuicoes da comunidade sao bem-vindas. Pull Requests aceitos no projeto sao incorporados sob a mesma licenca **MPL-2.0**. Consulte [CONTRIBUTING.md](CONTRIBUTING.md) e [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md).

## Suporte e seguranca

O uso do Usina Docs nao exige contrato de suporte. A Usina.BR podera oferecer, opcionalmente, suporte comercial, implantacao, treinamento, consultoria, integracoes, hospedagem e desenvolvimento.

Para expectativas de suporte comunitario, consulte [SUPPORT.md](SUPPORT.md). Vulnerabilidades de seguranca devem ser relatadas de forma privada conforme descrito em [SECURITY.md](SECURITY.md).
