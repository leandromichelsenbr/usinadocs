# Instalação local — alfa

Esta instalação serve para avaliação e desenvolvimento local do **Usina Docs alfa**. Ela não é um guia de produção.

## Requisitos

- Git;
- PHP 8.2 ou superior;
- Composer 2;
- extensão PHP `fileinfo`, além de `pdo_sqlite`/`sqlite3`, OpenSSL, Mbstring, XML, Ctype, JSON e Tokenizer;
- Node.js 20+ e npm, somente para compilar recursos visuais.

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
cd usinadocs
composer install
```

Crie o arquivo de ambiente e o banco SQLite.

No PowerShell:

```powershell
Copy-Item .env.example .env
New-Item database/database.sqlite -ItemType File -Force
```

No macOS ou Linux:

```bash
cp .env.example .env
touch database/database.sqlite
```

Em seguida:

```bash
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Abra `http://127.0.0.1:8000`. A demonstração inicial deve responder em `http://127.0.0.1:8000/pt/p/bem-vindo`.

> O repositório ainda não possui `composer.lock`; por isso, `composer install` resolve as versões compatíveis disponíveis no momento. A criação de um lockfile será tratada antes de uma distribuição beta reproduzível.

## Primeiro administrador

Não há conta nem senha padrão. Crie a conta administrativa local:

```bash
php artisan user:make-admin seu-email@exemplo.com --name="Seu nome"
```

A senha é solicitada de forma oculta e precisa ter ao menos 12 caracteres. Depois, entre em `http://127.0.0.1:8000/login` e acesse o painel em `/admin`.

## O que testar no alfa

1. Abra `/admin/pages` e crie uma página em rascunho.
2. Adicione blocos de texto, código ou referência e salve.
3. Publique a revisão e acesse a rota pública no idioma escolhido.
4. Crie uma nova revisão da página já publicada; a versão anterior deve permanecer pública e imutável.
5. Crie uma tradução em `/admin/pages/{pagina}/translations/create` e confirme a rota localizada.

## Testes automatizados

```bash
php artisan test
```

O conjunto cobre acesso administrativo, publicação, revisão imutável, rascunhos não públicos e rotas localizadas.

## Problemas frequentes

| Sintoma | Ação |
| --- | --- |
| `composer` não é reconhecido | Instale o Composer e reabra o terminal. |
| `ext-fileinfo` ausente | Habilite `extension=fileinfo` no `php.ini` indicado por `php --ini`. |
| `could not find driver` | Habilite `pdo_sqlite` e `sqlite3`, crie `database/database.sqlite` e rode as migrações. |
| Página retorna erro após mudar `.env` | Execute `php artisan optimize:clear`. |
| Porta 8000 ocupada | Execute `php artisan serve --port=8001`. |

## Limites desta etapa

O alfa contém a fundação editorial: autenticação local, administração, páginas, revisões, blocos, publicações e localização. Cursos, biblioteca de mídia, editor visual avançado, importação/exportação e autenticação externa fazem parte das próximas etapas. Consulte também [a arquitetura do alfa](ARCHITECTURE.md).
