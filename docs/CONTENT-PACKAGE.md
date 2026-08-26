# Pacote de conteúdo Usina Docs — especificação alfa 0.1

O pacote de conteúdo é o formato aberto de troca, backup editorial e migração do Usina Docs. Ele representa conhecimento, documentos controlados, treinamentos e mídia sem depender do banco de dados, da tecnologia de servidor ou do tema visual de uma instalação.

Esta é uma especificação **alfa**. Ela estabelece contratos estáveis de identificação e integridade, mas permite evolução incompatível entre versões alfa. O leitor deve sempre verificar `format_version` antes de importar.

## Objetivos

- permitir backup completo do conteúdo editorial sem usuários, sessões ou segredos;
- transportar páginas, documentos controlados, treinamentos, revisões, blocos, idiomas, referências e arquivos de mídia entre instalações;
- manter a origem, autoria, licença e acessibilidade do material;
- tornar a importação repetível e verificável por checksums;
- permitir que documentos, procedimentos, cursos e aulas reutilizem o mesmo conteúdo.

## O que não entra no pacote

Por padrão, o pacote não inclui senhas, sessões, tokens, dados de autenticação externa, logs, preferências privadas, ciência nominal de documentos, dados de progresso de alunos, telemetria ou configurações de infraestrutura.

Esses itens podem ter mecanismos de backup próprios no futuro, mas nunca devem ser incluídos no pacote editorial sem consentimento explícito e documentação de segurança.

## Estrutura de diretórios

Um pacote é um diretório ou arquivo `.zip` que preserva esta estrutura:

```text
meu-pacote/
├── manifest.json
├── pages/
│   └── introducao/
│       ├── page.json
│       └── revisions/
│           ├── 0001.pt-BR.json
│           ├── 0002.pt-BR.json
│           └── 0003.en.json
├── media/
│   ├── 6f/6f2d...png
│   └── media.json
└── checksums.sha256
```

| Local | Obrigatório | Finalidade |
| --- | --- | --- |
| `manifest.json` | Sim | Identifica o pacote, formato, site de origem, idiomas e arquivos incluídos. |
| `pages/` | Sim, se houver páginas | Uma pasta por identificador estável de página. |
| `pages/<id>/page.json` | Sim | Metadados da página e seus slugs localizados. |
| `pages/<id>/revisions/` | Sim | Uma revisão por arquivo, incluindo seus blocos e referências. |
| `media/` | Não | Arquivos binários e catálogo de metadados de mídia. |
| `checksums.sha256` | Sim | Integridade de todos os arquivos do pacote, exceto ele próprio. |

Os arquivos JSON usam UTF-8, finais de linha LF e chaves em `snake_case`. Datas usam ISO 8601 em UTC. Identificadores são UUIDs canônicos em minúsculas.

## Manifesto

`manifest.json` contém o mínimo necessário para decidir se o pacote pode ser lido antes de importar qualquer conteúdo:

```json
{
  "format": "usinadocs-content-package",
  "format_version": "0.1",
  "package_id": "25a3e618-782e-423f-9c44-2c9d8f1d26d8",
  "created_at": "2026-08-13T00:00:00Z",
  "created_by": {
    "name": "Example publisher",
    "uri": "https://example.org"
  },
  "site": {
    "id": "bf5ed039-0e89-43a8-a56c-08f3c5060f57",
    "name": "Example Docs",
    "slug": "example-docs"
  },
  "default_language": "pt-BR",
  "languages": ["pt-BR", "en", "es"],
  "contents": {
    "pages": 1,
    "controlled_documents": 0,
    "courses": 0,
    "revisions": 3,
    "media": 1
  },
  "files": [
    {"path": "pages/introducao/page.json", "sha256": "..."},
    {"path": "pages/introducao/revisions/0001.pt-BR.json", "sha256": "..."}
  ]
}
```

`files` é o inventário autoritativo. O importador deve rejeitar caminhos absolutos, `..`, duplicados, arquivos não listados quando em modo estrito e checksums diferentes dos declarados.

## Página e localização

Cada página possui um identificador permanente que nunca é derivado do título ou do slug. O diretório pode usar um nome legível, mas o campo `id` é a identidade para atualização e deduplicação.

```json
{
  "id": "a345d18c-f027-48da-b4d0-9a3d36cedc3d",
  "site_id": "bf5ed039-0e89-43a8-a56c-08f3c5060f57",
  "created_at": "2026-08-12T20:00:00Z",
  "updated_at": "2026-08-12T20:10:00Z",
  "localizations": [
    {
      "language": "pt-BR",
      "slug": "introducao",
      "published_revision_id": "2f684b68-b733-440b-9569-493064b25dc2"
    },
    {
      "language": "en",
      "slug": "introduction",
      "published_revision_id": "9d27c958-7fa5-40ec-a4ea-51754207f4a6"
    }
  ]
}
```

O slug é único por idioma dentro do mesmo site. Uma página pode não ter publicação em todos os idiomas. Essa ausência é um estado válido e não autoriza o importador a copiar automaticamente o texto de outro idioma.

## Revisão

Uma revisão contém o conteúdo completo de uma página em um idioma. Ela não contém diferenças parciais. Dessa forma, qualquer revisão publicada pode ser restaurada sem consultar o banco de origem.

```json
{
  "id": "2f684b68-b733-440b-9569-493064b25dc2",
  "page_id": "a345d18c-f027-48da-b4d0-9a3d36cedc3d",
  "language": "pt-BR",
  "number": 1,
  "status": "published",
  "title": "Introdução",
  "summary": "O ponto de partida para conhecer o projeto.",
  "created_at": "2026-08-12T20:00:00Z",
  "published_at": "2026-08-12T20:10:00Z",
  "editorial": {
    "created_by": {"name": "Example publisher"},
    "reviewed_by": {"name": "Example reviewer"},
    "reviewed_at": "2026-08-12T20:08:00Z"
  },
  "blocks": [],
  "references": []
}
```

Status permitidos no alfa: `draft`, `in_review` e `published`. Uma importação preserva rascunhos somente quando o operador habilitar explicitamente `include_drafts`; o padrão é importar apenas versões publicadas e suas referências.

## Documento controlado

Um documento controlado é uma página com metadados adicionais de governança. O pacote deve preservar esses metadados sem exigir que todo site trate todas as páginas como documentos controlados.

Campos previstos:

| Campo | Finalidade |
| --- | --- |
| `document_code` | Código interno, como `POP-QUAL-001` ou `IT-MAN-003`. |
| `version_label` | Revisão ou versão exibida ao usuário. |
| `owner` | Área ou pessoa responsável pelo conteúdo. |
| `reviewer` | Responsável técnico pela revisão. |
| `approver` | Responsável pela aprovação formal. |
| `effective_at` | Início de vigência. |
| `review_due_at` | Próxima revisão prevista. |
| `requires_acknowledgement` | Indica se o usuário precisa registrar ciência. |
| `requires_training` | Indica se existe treinamento obrigatório vinculado. |

Registros nominais de ciência, assinaturas, progresso e avaliações são dados pessoais ou operacionais da instalação. Eles não entram no pacote editorial por padrão.

## Blocos

Todo bloco possui `id`, `type`, `position` e `data`. A ordem é crescente, começa em 1 e não pode se repetir em uma revisão. Tipos desconhecidos não devem ser descartados silenciosamente: o importador deve interromper ou manter o bloco como não renderizável, de acordo com o modo escolhido pelo operador.

```json
{
  "id": "b7d2c15e-3f74-4694-80fd-2be0a8312397",
  "type": "code",
  "position": 2,
  "data": {
    "language": "advpl",
    "code": "User Function Exemplo()\nReturn",
    "caption": "Exemplo mínimo"
  }
}
```

Tipos iniciais:

| Tipo | Dados mínimos | Uso |
| --- | --- | --- |
| `text` | `title` opcional, `body` | Texto editorial em Markdown. |
| `code` | `code`, `language` opcional | Código-fonte ou trecho técnico. |
| `reference` | `citation_key` ou `text` | Chamada editorial para uma referência estruturada. |
| `media` | `media_id`, `alt` | Imagem, áudio, vídeo ou arquivo sob demanda. |
| `callout` | `tone`, `body` | Aviso, dica, cuidado ou informação complementar. |
| `procedure_step` | `title`, `body` | Etapa de procedimento ou instrução de trabalho. |
| `checklist` | `items` | Lista de verificação para operação, revisão ou aula prática. |
| `assessment` | `questions` | Avaliação vinculada a aula, procedimento ou documento. |

O formato não obriga um tema a renderizar todos os tipos. Porém, o tema deve informar claramente quando um bloco não for suportado.

## Referências e citações

Referências ficam na revisão, não em um bloco isolado. Isso permite citar uma mesma fonte em vários blocos sem duplicar seus dados bibliográficos.

```json
{
  "id": "d3d05c15-e4da-4fcb-bcc3-897dbbb771d0",
  "citation_key": "totvs-soma1-2026",
  "type": "web_page",
  "title": "Soma1",
  "authors": [{"name": "TOTVS"}],
  "publisher": "TDN",
  "url": "https://tdn.totvs.com/",
  "accessed_at": "2026-08-12T00:00:00Z",
  "license": "All rights reserved",
  "notes": "Fonte técnica consultada para fins de referência."
}
```

Um bloco `reference` aponta para `citation_key`. Citações parciais, páginas e transcrições devem ser registradas no bloco que as usa, para preservar contexto e limites de uso.

## Mídia

`media/media.json` é o catálogo de arquivos. Cada item possui identificador estável, checksum, tipo MIME, origem, licença, autoria e texto alternativo recomendado. O arquivo binário usa caminho relativo e hash no nome para impedir colisões.

```json
{
  "id": "d733b88a-7e73-4fd6-a9bc-fbc25f7d0f07",
  "path": "media/6f/6f2d1b.png",
  "sha256": "6f2d1b...",
  "mime_type": "image/png",
  "size_bytes": 42181,
  "source_url": "https://example.org/original.png",
  "creator": "Example creator",
  "license": "CC BY 4.0",
  "alt": "Descrição objetiva da imagem"
}
```

O importador deve verificar MIME, tamanho, checksum e extensão segura antes de gravar qualquer mídia. Arquivos executáveis, caminhos especiais e metadados de licença ausentes devem ser recusados ou marcados para revisão, conforme a política da instalação.

## Integridade, assinatura e importação

1. O exportador cria arquivos JSON determinísticos e calcula SHA-256 para cada arquivo.
2. Gera `checksums.sha256` e repete esses hashes no manifesto.
3. O importador valida formato, versão, caminhos e checksums antes de escrever no banco ou armazenamento.
4. O importador apresenta uma prévia: criação, atualização, conflito de slug, revisão ausente, mídia pendente e conteúdo desconhecido.
5. Só após confirmação ele executa uma transação de importação e grava um relatório de resultado.

Assinaturas criptográficas são opcionais no alfa. Quando adotadas, devem assinar o checksum do manifesto e declarar algoritmo, chave pública e identidade do assinante em campo próprio, sem substituir a conferência de hashes.

## Estratégia de conflitos

| Situação | Comportamento padrão |
| --- | --- |
| Mesmo `id` e mesmo checksum | Ignora como já importado. |
| Mesmo `id` e checksum diferente | Cria conflito; exige escolha entre manter local, substituir ou importar como nova revisão. |
| Mesmo slug, `id` diferente | Cria conflito de rota; não sobrescreve. |
| Referência ausente | Importa a página com aviso e bloqueia publicação até revisão, se a política local exigir. |
| Mídia ausente ou checksum inválido | Não publica o bloco de mídia; registra o erro. |
| Tipo de bloco desconhecido | Preserva como bloco não renderizável ou interrompe a importação em modo estrito. |

## Compatibilidade

- Alterações retrocompatíveis aumentam a versão secundária: `0.1` → `0.2`.
- Alterações incompatíveis aumentam a versão principal: `0.x` → `1.0`.
- Um importador deve aceitar somente versões explicitamente declaradas como compatíveis.
- Campos adicionais devem ser preservados quando possível; campos obrigatórios desconhecidos exigem aviso ou rejeição.

## Próxima implementação

O núcleo já oferece exportação de página publicada, validação de manifesto, prévia sem escrita e importação transacional conservadora de páginas novas. A próxima evolução deve tratar conflitos e atualizações de conteúdo existente e ampliar o pacote de demonstração para três idiomas, referência estruturada e mídia com licença explícita.
