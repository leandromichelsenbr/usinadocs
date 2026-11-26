# Instalação local — pré-alfa

Esta instalação serve para avaliação e desenvolvimento local do **Usina Docs** com o núcleo leve em [`core/`](../core/) (Slim, Twig, PDO e SQLite, conforme a [ADR-001](ADR-001-LIGHTWEIGHT-PHP-CORE.md)). Ela não é um guia de produção.

> O protótipo Laravel usado antes da ADR-001 foi removido da árvore principal. Ele continua disponível, apenas como referência histórica, na tag [`v0.1.0-laravel-prototype`](https://github.com/leandromichelsenbr/usinadocs/tree/v0.1.0-laravel-prototype).

## Requisitos

- Git;
- PHP 8.2 ou superior;
- Composer 2;
- extensões PHP `pdo_sqlite`, `sqlite3`, `fileinfo`, `mbstring` e `curl` (esta última apenas para login por Google ou Microsoft).

Node.js, npm e etapa de compilação de front-end não são necessários.

Confirme o PHP e suas configurações ativas:

```bash
php --version
php --ini
php -m
```

No Windows, habilite as extensões necessárias no `php.ini` usado pela linha de comando — por exemplo, `extension=fileinfo`, `extension=pdo_sqlite` e `extension=sqlite3`. Depois, abra um novo terminal e execute novamente `php -m`.

## Primeira execução

```bash
git clone https://github.com/leandromichelsenbr/usinadocs.git
cd usinadocs/core
composer install
```

Crie o arquivo de ambiente.

No PowerShell:

```powershell
Copy-Item .env.example .env
```

No macOS ou Linux:

```bash
cp .env.example .env
```

Antes de migrar, edite `.env` e troque `USINADOCS_ADMIN_EMAIL` e `USINADOCS_ADMIN_PASSWORD`. A migração cria o primeiro administrador com esses valores; se eles não forem definidos, é usado o par de exemplo, que nunca deve permanecer em uma instalação acessível por outras pessoas.

```bash
php bin/migrate.php
php -S 127.0.0.1:8080 -t public
```

Abra `http://127.0.0.1:8080`. A demonstração inicial deve responder em `http://127.0.0.1:8080/pt/p/bem-vindo`.

O arquivo `core/composer.lock` é versionado; `composer install` reproduz as mesmas versões de dependências usadas nos testes.

## Acesso administrativo

Entre em `http://127.0.0.1:8080/login` com as credenciais do `.env` e acesse o painel em `/admin`.

Para trocar as credenciais depois que o banco já existe, altere o `.env` e execute:

```bash
composer reset-admin
```

Login por Google e Microsoft, convite do primeiro administrador nesse modo e grupos de acesso estão descritos no [README do core](../core/README.md#login-por-google-e-microsoft).

## O que testar

1. Abra `/admin` e crie uma página em rascunho.
2. Adicione blocos de texto, código ou referência e salve.
3. Publique a revisão e acesse a rota pública no idioma escolhido.
4. Crie uma nova revisão da página já publicada; a versão anterior deve permanecer pública e imutável até a nova publicação.
5. Crie uma tradução e confirme a rota localizada.
6. Exporte uma página publicada como pacote e importe-o em outra instalação.

## Testes automatizados

```bash
cd core
composer test
```

O mesmo conjunto roda no GitHub Actions em toda pull request, com PHP 8.2 e 8.3.

## Problemas frequentes

| Sintoma | Ação |
| --- | --- |
| `composer` não é reconhecido | Instale o Composer e reabra o terminal. |
| `ext-fileinfo` ausente | Habilite `extension=fileinfo` no `php.ini` indicado por `php --ini`. |
| `could not find driver` | Habilite `pdo_sqlite` e `sqlite3` e rode `php bin/migrate.php` novamente. |
| Login recusado após mudar `.env` | Execute `composer reset-admin` para sincronizar as credenciais com o banco. |
| Porta 8080 ocupada | Use outra porta, por exemplo `php -S 127.0.0.1:8081 -t public`. |

## Limites desta etapa

O pré-alfa contém a fundação editorial: autenticação local ou OAuth, administração, páginas, modelos e artefatos versionados, revisões, traduções, mídia, pacotes de conteúdo e publicação. Cursos e o módulo de treinamento fazem parte das próximas etapas. Consulte também [a arquitetura atual](ARCHITECTURE.md).
