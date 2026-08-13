# Arquitetura do Usina Docs alfa

Este documento descreve o que existe hoje no repositório e separa claramente a base implementada da arquitetura de longo prazo. O objetivo é que uma instalação nova seja compreensível, auditável e evolua sem depender de conhecimento implícito.

Para a visão de produto, consulte [vision.md](vision.md). Para a arquitetura de destino, consulte [target-architecture.md](target-architecture.md).

## Escopo atual

O alfa é uma aplicação Laravel 12 com banco SQLite para desenvolvimento local. Ele prova o núcleo editorial do produto:

- acesso local com papel de administrador; a separação de permissões editoriais será ampliada em etapa posterior;
- páginas identificadas por site e slug;
- revisões numeradas e imutáveis após publicação;
- blocos versionados de texto, código e referência;
- publicação pública por idioma;
- traduções com slug próprio por idioma.

Ele **não** substitui o AdvPL Guia em produção. O guia continua sendo o laboratório de produto e conteúdo; o Usina Docs é o projeto independente, generalizável e aberto.

## Mapa da aplicação

```text
Navegador
   │
   ├── Rotas públicas ─────── PublishedPageController ── PageLocalization
   │                                                   └─ revisão publicada + blocos
   │
   └── Área administrativa ─ PageController ─────────── PageRevisionService
                                                        ├─ cria rascunho
                                                        ├─ altera rascunho
                                                        ├─ publica revisão
                                                        └─ cria tradução/revisão

Banco SQLite
   ├── users / sessions / cache / jobs
   ├── sites / languages
   ├── pages / page_localizations
   └── page_revisions / blocks
```

## Camadas e responsabilidades

| Camada | Local | Responsabilidade |
| --- | --- | --- |
| Rotas | `routes/web.php` | Define URLs públicas, login, logout e área administrativa. |
| Controladores | `app/Http/Controllers` | Recebe requisições, valida entrada e devolve respostas ou telas. |
| Domínio editorial | `app/Models` e `app/Services/PageRevisionService.php` | Mantém páginas, idiomas, revisões, publicação e blocos. |
| Persistência | `database/migrations` | Define as tabelas e as relações do alfa. |
| Interface | `resources/views` | Renderiza a área pública, autenticação e administração. |
| Configuração | `.env`, `config/` | Mantém ambiente, banco, sessão, cache, fila e e-mail fora do código versionado. |

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

## Rotas principais

| Finalidade | Rota |
| --- | --- |
| Início | `/` |
| Página pública localizada | `/{locale}/p/{slug}` |
| Compatibilidade em português | `/p/{slug}` → `/pt/p/{slug}` |
| Login local | `/login` |
| Administração | `/admin` |
| Páginas administrativas | `/admin/pages` |

As rotas administrativas exigem autenticação e papel de administrador. O comando `user:make-admin` cria ou promove uma conta local para esse papel.

## Dados locais e segurança

- `.env` contém segredos e nunca deve ser versionado.
- `database/database.sqlite` é uma base local de desenvolvimento e também não deve ser enviada ao Git.
- Sessões, cache e filas usam o banco na configuração alfa; por isso, as migrações são obrigatórias.
- E-mail usa o driver `log` por padrão: nenhuma mensagem é entregue externamente durante a instalação local.
- A política de produção, backup, autenticação externa, armazenamento de mídia e observabilidade ainda será definida antes da fase beta.

## Caminho de evolução

O próximo bloco de arquitetura deve manter estas regras editoriais e acrescentar, sem quebrá-las:

1. modelo formal de tipos de bloco e validação por tipo;
2. metadados editoriais, referências e mídia;
3. fluxo de revisão, permissões e auditoria;
4. exportação versionada independente do banco;
5. cursos, aulas, atividades e progresso como módulo nativo.

Decisões de banco de produção, API, extensões, temas e editor visual permanecem abertas. Mudanças estruturais devem registrar a decisão, a motivação e o impacto de migração antes de serem incorporadas.
