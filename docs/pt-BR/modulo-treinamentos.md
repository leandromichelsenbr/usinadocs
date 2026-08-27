# Módulo de Treinamentos — Usina Docs

## Visão

O **Módulo de Treinamentos** será um componente nativo e isolável do Usina Docs para criação, publicação e acompanhamento de programas de formação, cursos livres, trilhas, módulos, aulas, exercícios, avaliações, progresso, conquistas e, futuramente, certificados de conclusão.

Ele deve reutilizar os serviços editoriais, multilíngues, de usuários e de mídia do Usina Docs, mantendo porém uma fronteira de domínio clara para que possa evoluir de forma relativamente independente do núcleo documental.

## Piloto de origem

O primeiro piloto funcional existe hoje no projeto `leandromichelsenbr/advpl-usinabr-site`, na área de treinamento do AdvPL Guia. O código foi segregado sob `training/` justamente para facilitar sua futura migração para o Usina Docs.

O piloto já fornece requisitos reais para:

- matrícula;
- progresso por aula;
- pontuação;
- questionários;
- conquistas;
- indicadores administrativos;
- conteúdo multilíngue;
- curso livre;
- programa de formação progressivo.

A migração não deve copiar simplesmente o código atual. O piloto deve servir como fonte de regras de negócio, conteúdo e experiência de uso para uma implementação nativa no Usina Docs.

## Produtos de aprendizagem

### Curso livre

Conteúdo independente que pode ser cursado fora de um programa formal. O primeiro exemplo é o **Treinamento AdvPL** já existente no AdvPL Guia.

### Programa de formação

Formação ampla, progressiva e orientada a competências. O primeiro programa planejado é **ADVPL & Protheus**:

| Nível | Formação |
|---|---|
| L0 | Fundamentos de Programação |
| L1 | Ambiente de Desenvolvimento Protheus |
| L2 | ADVPL Essentials |
| L3 | ADVPL + Dados |
| L4 | Protheus Application Developer |
| L5 | Advanced Protheus Developer |
| L6 | Integration Developer |
| L7 | Modern Protheus Developer |

O currículo permanece em planejamento e não implica datas ou cargas horárias fixas.

## Estrutura conceitual

```text
Training
├── Programs
│   └── Tracks / Levels
│       └── Modules
│           └── Lessons
├── Free Courses
│   └── Modules
│       └── Lessons
├── Exercises
├── Assessments
├── Enrollments
├── Progress
├── Achievements
└── Certificates
```

## Entidades previstas

- **Programa** — formação ampla, versionada e com critérios de conclusão.
- **Trilha/Nível** — progressão, pré-requisitos, competências e projeto final.
- **Curso livre** — curso independente de um programa formal.
- **Módulo** — agrupamento de aulas relacionadas.
- **Aula** — unidade de aprendizagem versionada e multilíngue, reutilizando blocos editoriais quando possível.
- **Exercício** — atividade prática versionada.
- **Avaliação** — questionário, exercício avaliado ou projeto, preservando a versão respondida.
- **Matrícula** — vínculo entre usuário e curso/programa.
- **Progresso** — evolução do aluno por conteúdo e etapa.
- **Conquista** — reconhecimento intermediário por habilidade ou etapa.
- **Certificado** — emissão futura de certificado de conclusão com identificador verificável e versão curricular concluída.

## Integração com documentos controlados

O módulo deverá suportar treinamentos vinculados a documentos controlados:

```text
Documento controlado
       │
       ├── exige ciência
       │
       └── exige treinamento
                │
                ▼
              Curso
                │
                ▼
            Avaliação
                │
                ▼
       Evidência de conclusão
```

Isso permitirá tratar treinamentos obrigatórios, reciclagens por revisão documental e evidências de conformidade.

## Multilíngue

Programas, cursos, módulos e aulas devem usar a infraestrutura multilíngue nativa do Usina Docs. Para o piloto ADVPL, a prioridade é português do Brasil, inglês e espanhol.

## Separação arquitetural

O módulo deve possuir fronteira explícita no código, preferencialmente com estruturas próprias, por exemplo:

```text
app/Training/
resources/views/training/
routes/training.php
```

A direção de dependência esperada é:

```text
Training ──► Core editorial
Training ──► Users/Auth
Training ──► Translation
Training ──► Media
Training ──► Controlled Documents (opcional)
```

O Core editorial não deve depender de Training.

## Migração do AdvPL Guia

A migração deverá ser incremental:

1. inventariar cursos, aulas, matrículas, progresso, avaliações e conquistas existentes;
2. mapear a estrutura atual para os modelos nativos do Usina Docs;
3. importar um curso e algumas aulas representativas;
4. validar URLs, redirecionamentos, usuários e progresso;
5. executar piloto em homologação;
6. manter rollback;
7. migrar gradualmente o restante do conteúdo.

URLs públicas atuais do AdvPL Guia não devem ser quebradas.

## Fases sugeridas

### Training 0 — Modelo e fronteira
- namespace/módulo;
- programa, curso, trilha, módulo e aula;
- integração com blocos editoriais e traduções.

### Training 1 — Matrícula e progresso
- matrícula;
- progresso;
- retomada;
- indicadores básicos.

### Training 2 — Exercícios e avaliações
- exercícios versionados;
- questionários;
- tentativas;
- revisão orientada por erros.

### Training 3 — Programas e competências
- pré-requisitos;
- progressão;
- projetos de conclusão;
- competências adquiridas.

### Training 4 — Certificados
- critérios de elegibilidade;
- certificado de conclusão;
- identificador verificável;
- versão curricular concluída.

### Training 5 — Treinamento controlado
- vínculo com documentos controlados;
- treinamento obrigatório;
- renovação por revisão/validade;
- evidências e relatórios de conformidade.

## Diretriz de produto

Treinamento não deve ser tratado apenas como uma coleção de páginas especiais. Ele é um **módulo de domínio próprio**, construído sobre os serviços do Usina Docs e preparado para cursos livres, programas de formação e treinamentos corporativos controlados.