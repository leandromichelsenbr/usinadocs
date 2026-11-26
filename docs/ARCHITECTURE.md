# Arquitetura do Usina Docs alfa

Este documento descreve o que existe hoje no repositório e separa claramente a base implementada da arquitetura de longo prazo. O objetivo é que uma instalação nova seja compreensível, auditável e evolua sem depender de conhecimento implícito.

Para a visão de produto, consulte [vision.md](vision.md). Para a arquitetura de destino, consulte [target-architecture.md](target-architecture.md).

## Escopo atual

O pré-alfa é o núcleo leve em [`core/`](../core/): PHP 8.2+, Slim 4, Twig e PDO, com SQLite como banco padrão, conforme a [ADR-001](ADR-001-LIGHTWEIGHT-PHP-CORE.md). O protótipo Laravel que validou as primeiras regras foi removido da árvore principal e permanece na tag `v0.1.0-laravel-prototype`. O núcleo cobre o fluxo editorial do produto:

- acesso local com papel de administrador; a separação de permissões editoriais será ampliada em etapa posterior;
- páginas identificadas por site e slug;
- revisões numeradas e imutáveis após publicação;
- blocos versionados de texto, código e referência;
- publicação pública por idioma;
- traduções com slug próprio por idioma.

Ele **não** substitui o AdvPL Guia em produção. O guia continua sendo o laboratório de produto e conteúdo; o Usina Docs é o projeto independente, generalizável e aberto.

A arquitetura deve permanecer neutra em relação ao assunto publicado. Programação é o primeiro caso real, mas o mesmo núcleo deve acomodar procedimentos, instruções de trabalho, documentos controlados, manuais, aulas e bases de conhecimento de outras áreas.

## Mapa da aplicação

```text
Navegador
   │
   ├── Rotas públicas ─────── AppFactory / *Routes ─── PublishedPageRepository
   │                                                   └─ revisão publicada + blocos
   │
   └── Área administrativa ─ Presentation/*Routes ──── EditorialService
                                                        ├─ cria e altera rascunhos
                                                        ├─ publica revisões
                                                        ├─ versiona modelos e artefatos
                                                        └─ cria traduções e revisões

Banco SQLite (core/database/database.sqlite)
   ├── users / access_groups / oauth_users
   ├── sites / languages
   ├── pages / page_localizations / page_revisions / blocks
   ├── editorial_models / reusable_artifacts e suas revisões
   └── media / authors / legal_documents / article_comments / article_views
```

## Camadas e responsabilidades

| Camada | Local | Responsabilidade |
| --- | --- | --- |
| Entrada HTTP | `core/public/index.php` | Único ponto de entrada servido pelo servidor web. |
| Rotas e composição | `core/src/AppFactory.php`, `core/src/Presentation/` | Monta a aplicação Slim, registra rotas públicas e administrativas e liga serviços a templates. |
| Autenticação e papéis | `core/src/Authentication/` | Login local ou OAuth (Google/Microsoft) e papéis Leitor, Editor e Administrador. |
| Domínio editorial | `core/src/Content/` | Páginas, revisões, publicação, modelos, artefatos reutilizáveis, traduções e pacotes de conteúdo. |
| Mídia | `core/src/Media/` | Upload validado de imagens e entrega pela rota `/media/{id}`. |
| Persistência | `core/src/Infrastructure/`, `core/database/migrations/` | Conexão PDO e migrações SQL numeradas, aplicadas por `core/bin/migrate.php`. |
| Interface | `core/templates/` | Templates Twig com escape HTML automático para área pública e administração. |
| Configuração | `core/.env` | Caminho do banco, credenciais iniciais e modo de autenticação, fora do Git. |

## Modelo editorial implementado

```text
Site
 ├── Language
 └── Page
      ├── PageLocalization (um slug por idioma)
      │    └── published_revision_id
      └── PageRevision (número, idioma, status, título, resumo)
           └── Block (tipo, posição, dados JSON)
```

### Regras que já são contrato

1. Uma revisão publicada é imutável.
2. Alterar conteúdo publicado exige criar uma nova revisão.
3. Um rascunho não aparece na rota pública.
4. A publicação aponta uma revisão por idioma através de `PageLocalization`.
5. O conteúdo de uma revisão é formado por blocos ordenados; os blocos não são reutilizados por referência nesta etapa.
6. Metadados de controle documental, aprovação, ciência e treinamento serão adicionados sem alterar a regra de imutabilidade das revisões publicadas.

## Rotas principais

| Finalidade | Rota |
| --- | --- |
| Início | `/` |
| Página pública localizada | `/{locale}/p/{slug}` |
| Login local ou por provedor | `/login` |
| Administração | `/admin` |
| Páginas administrativas | `/admin/pages` |

As rotas administrativas exigem autenticação e papel de Editor ou Administrador, verificados no servidor. O primeiro administrador local vem de `USINADOCS_ADMIN_EMAIL` e `USINADOCS_ADMIN_PASSWORD` durante a migração; no modo OAuth, ele é convidado por `core/bin/invite-administrator.php`.

## Dados locais e segurança

- `core/.env` contém segredos e nunca deve ser versionado.
- `core/database/database.sqlite` e `core/storage/media/` contêm dados da instalação e também não devem ser enviados ao Git.
- Sessões usam o mecanismo nativo do PHP; não há fila, cache externo nem envio de e-mail nesta etapa.
- A política de produção, backup, autenticação externa, armazenamento de mídia e observabilidade ainda será definida antes da fase beta.

## Caminho de evolução

O próximo bloco de arquitetura deve manter estas regras editoriais e acrescentar, sem quebrá-las:

1. modelo formal de tipos de bloco e validação por tipo;
2. metadados editoriais, referências e mídia;
3. fluxo de revisão, aprovação, permissões e auditoria;
4. exportação versionada independente do banco, definida em [CONTENT-PACKAGE.md](CONTENT-PACKAGE.md);
5. documentos controlados, ciência formal e validade de revisão;
6. cursos, aulas, atividades e progresso como módulo nativo.

Decisões de banco de produção, API, extensões, temas e editor visual permanecem abertas. Mudanças estruturais devem registrar a decisão, a motivação e o impacto de migração antes de serem incorporadas.
