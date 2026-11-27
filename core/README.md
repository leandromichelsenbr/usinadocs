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

## Publicação e compartilhamento em redes sociais

Os leitores podem compartilhar artigos públicos no WhatsApp, LinkedIn e Facebook, ou copiar o link da edição que estão lendo. A publicação automática funciona com páginas empresariais do LinkedIn e do Facebook.

1. Defina `USINADOCS_PUBLIC_ORIGIN=https://seu-dominio.example` no `core/.env`. Execute as migrações da instalação.
2. Mantenha um arquivo JSON de credenciais fora do repositório e da raiz pública, com leitura restrita ao usuário que executa o agendador. Inclua a entrada de cada rede que deseja usar.

   ```json
   {
     "linkedin": {"version": "202609", "token": "TOKEN"},
     "facebook": {"version": "v27.0", "token": "TOKEN"}
   }
   ```

   Use versões vigentes para o aplicativo. No LinkedIn, a credencial deve ter permissão `w_organization_social` e o usuário deve ter papel de publicação na página. No Facebook, use um token **da Página** com as permissões de publicação exigidas pela API. [API Posts do LinkedIn](https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/posts-api?view=li-lms-2026-09) · [API Page Feed do Facebook](https://developers.facebook.com/docs/graph-api/reference/page/feed/)

3. Agende `php /caminho/core/bin/publish-social.php /caminho/privado/social.json` para rodar a cada minuto, com PDO SQLite e cURL. Em **Painel editorial → Redes sociais**, informe o ID numérico da organização no LinkedIn ou da Página no Facebook, confira o destino pelo link e habilite o canal no idioma desejado. Instalações que já usavam o campo `id` no arquivo privado podem cadastrar o mesmo valor no painel. O destino salvo no painel tem prioridade.
4. A primeira publicação da edição escolhida entra na fila. Revisões seguintes e artigos que já tinham essa edição publicada não entram na fila. Ao desativar um canal, a fila existente fica pausada. O envio também espera enquanto o artigo está oculto. Consulte o painel para ver os envios recentes e reenfileirar recusas após corrigir a causa. Para um envio a confirmar, verifique se a notícia apareceu na própria rede, pois o servidor pode ter recebido a publicação antes de a conexão cair.

## Sitemap

Com `USINADOCS_PUBLIC_ORIGIN` configurada, o núcleo disponibiliza `/sitemap.xml` e anuncia o endereço em `/robots.txt`. Encaminhe requisições GET e HEAD desses dois caminhos ao front controller no servidor web.

Em **Painel editorial → Sitemap**, o modo **Automática** consulta as versões públicas e inclui novas publicações na próxima leitura do XML. **Manual** mantém a cópia criada ao salvar. Salve novamente para atualizar essa cópia.
