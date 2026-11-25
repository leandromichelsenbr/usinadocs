# Usina Docs Core

Esta é a fundação leve ativa do Usina Docs. O protótipo Laravel permanece no diretório raiz como referência histórica até que esta base reproduza o núcleo editorial definido na [ADR-001](../docs/ADR-001-LIGHTWEIGHT-PHP-CORE.md).

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

## Alterar acesso administrativo

Defina `USINADOCS_ADMIN_EMAIL` e `USINADOCS_ADMIN_PASSWORD` no arquivo `.env`. Se o administrador já tiver sido criado, sincronize as novas credenciais sem apagar seu banco:

```bash
composer reset-admin
```

## Estado atual

Esta primeira fatia demonstra o fluxo público mínimo: SQLite, migração, conteúdo de demonstração, rota localizada, página publicada e blocos de texto/código. Autenticação, administração, revisões editáveis, mídia e importação serão adicionados em fatias posteriores.

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
