# Usina Docs Core

Esta é a base de código ativa do Usina Docs, definida na [ADR-001](../docs/ADR-001-LIGHTWEIGHT-PHP-CORE.md). O protótipo Laravel anterior foi removido da árvore principal e permanece apenas na tag `v0.1.0-laravel-prototype`.

## Executar localmente

```bash
composer install
cp .env.example .env
php bin/migrate.php
php -S 127.0.0.1:8080 -t public
```

No Windows PowerShell, substitua a cópia do ambiente por:

```powershell
Copy-Item .env.example .env
```

Abra `http://127.0.0.1:8080/pt/p/bem-vindo`.

## Versão no rodapé público

O rodapé lê a versão de `core/VERSION`. Em uma implantação baseada no Git, execute `git rev-parse HEAD > core/BUILD_REVISION` na raiz do checkout usado para a entrega. Distribua esse arquivo junto ao Core e atualize-o quando implantar uma nova revisão.

## Página inicial de notícias

Instalações que publicam um jornal, como a Usina News, podem trocar a página inicial de documentação por uma capa com as últimas matérias e as editorias. Defina no `.env`:

```dotenv
USINADOCS_NEWS_HOME=1
USINADOCS_SITE_NAME="Usina News"        # opcional; padrão "Usina News"
USINADOCS_TIMEZONE=America/Sao_Paulo    # opcional; fuso usado nas datas
```

A capa responde em `/` e em `/pt/p/inicio`. Os textos fixos ficam em `config/news-reader-copy.php`, e as editorias são cadastradas em `/admin/sections`.

## Alterar acesso administrativo

Defina `USINADOCS_ADMIN_EMAIL` e `USINADOCS_ADMIN_PASSWORD` no arquivo `.env`. Se o administrador já tiver sido criado, sincronize as novas credenciais sem apagar seu banco:

```bash
composer reset-admin
```

## Testes

```bash
composer test
```

A mesma suíte roda no GitHub Actions em toda pull request.

## Estado atual

O núcleo reproduz o fluxo editorial validado no protótipo — páginas, blocos, rascunhos, publicação, revisões imutáveis e rotas localizadas — e acrescenta modelos e artefatos versionados, traduções, mídia, pacotes de conteúdo, autores, comentários moderados e login por provedores. O guia completo de instalação está em [docs/INSTALL.md](../docs/INSTALL.md).

## Login por Google e Microsoft

Para usar autenticação externa, defina `USINADOCS_AUTH_MODE=oauth` e
`USINADOCS_OAUTH_CONFIG` com o caminho de um arquivo JSON privado, legível
pelo processo do PHP. O arquivo fica fora da pasta pública e do repositório.

```json
{
  "origin": "https://docs.example.com",
  "google": {"client_id": "", "client_secret": ""},
  "microsoft": {"client_id": "", "client_secret": "", "tenant": "common"}
}
```

Registre `/auth/google/callback` e `/auth/microsoft/callback`, com a origem
HTTPS completa, como URLs de retorno dos respectivos aplicativos web. Na
Microsoft, habilite a permissão delegada `User.Read` e escolha as contas
compatíveis com o tenant configurado. Execute `php bin/migrate.php` e,
para o primeiro administrador, `php bin/invite-administrator.php EMAIL`.
O convite é aceito no login com o endereço verificado pelo Google.

O primeiro login cria a conta no grupo Leitor. Administradores podem
atribuir os grupos Editor e Administrador no painel de usuários. A aplicação
identifica a conta pelo provedor e pelo identificador fornecido por ele.
No modo OAuth, a aplicação usa apenas o login pelos provedores e dispensa
`USINADOCS_ADMIN_EMAIL` e `USINADOCS_ADMIN_PASSWORD`.
