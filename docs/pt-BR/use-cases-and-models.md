# Casos de uso e modelos de conteudo

O Usina Docs foi projetado para lidar com varios dominios de conhecimento usando a mesma fundacao: conteudo estruturado, revisoes, traducoes, referencias, treinamento e evidencias.

Este documento descreve os principais casos de uso de longo prazo. Ele nao e um esquema final; e um guia de planejamento para futuros modelos de dados, desenho de interface e criterios de aceitacao.

## Base de conhecimento

Para materiais de referencia pesquisaveis, explicacoes tecnicas e documentacao geral.

Campos tipicos:

- titulo, resumo e status;
- assunto, tags e relacionamentos;
- datas de criacao e revisao;
- referencias e citacoes;
- versoes traduzidas;
- blocos de conteudo reutilizaveis.

Exemplos:

- conceitos de programacao;
- artigos tecnicos;
- perguntas frequentes;
- verbetes de glossario;
- anotacoes de eletronica, quimica ou fisica;
- artigos internos de conhecimento.

## Documento controlado

Para conteudo que exige governanca, revisao, aprovacao ou ciencia formal.

Campos tipicos:

- codigo do documento;
- versao ou revisao exibida;
- area responsavel;
- revisor;
- aprovador;
- data de vigencia;
- proxima data de revisao;
- status;
- historico de alteracoes;
- ciencia obrigatoria;
- treinamento obrigatorio.

Exemplos:

- procedimentos operacionais padrao;
- instrucoes de trabalho;
- politicas internas;
- documentos de qualidade;
- procedimentos de seguranca;
- manuais de equipamentos.

## Procedimento ou instrucao de trabalho

Para conteudo operacional passo a passo.

Blocos tipicos:

- objetivo;
- escopo;
- materiais ou ferramentas necessarias;
- pre-requisitos;
- avisos de seguranca;
- etapas ordenadas;
- resultado esperado;
- checklist;
- registros gerados;
- documentos relacionados.

Exemplos:

- operacao de maquina;
- procedimento de laboratorio;
- rotina de suporte de software;
- rotina de aprovacao documental;
- instrucao de manutencao.

## Conteudo de treinamento

Para aulas, trilhas de aprendizagem, avaliacoes e acompanhamento de progresso.

Campos tipicos:

- objetivos de aprendizagem;
- pre-requisitos;
- etapas da aula;
- atividades praticas;
- perguntas de avaliacao;
- pontuacao minima;
- revisao orientada por erro;
- conquista ou certificado;
- documentos ou referencias vinculadas.

Exemplos:

- aulas de AdvPL;
- trilha de integracao;
- treinamento de seguranca;
- treinamento de equipamento;
- certificacao de processo interno.

## Referencia tecnica

Para paginas de referencia estruturadas cujo formato deve ser previsivel.

Campos tipicos:

- nome;
- sintaxe ou identificacao;
- finalidade;
- parametros ou entradas;
- retorno ou saida esperada;
- exemplos;
- cuidados;
- paginas relacionadas;
- referencias.

Exemplos:

- funcoes de programacao;
- comandos;
- classes;
- APIs;
- formulas;
- componentes.

## Assunto educacional

Para assuntos fora de programacao que ainda se beneficiam de explicacao estruturada e aprendizagem.

Blocos tipicos:

- explicacao conceitual;
- formula ou principio;
- exemplo pratico;
- diagrama ou midia;
- experimento;
- perguntas de revisao;
- referencias.

Exemplos:

- eletronica;
- quimica;
- fisica;
- matematica;
- mecanica.

## Regra de produto

O nucleo nao deve fixar nenhum dominio especifico. Programacao, procedimentos, aulas de ciencias e documentos controlados devem ser representados como modelos de conteudo, pacotes ou extensoes sobre a mesma plataforma generica.
