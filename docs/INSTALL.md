# Instalação local

Esta primeira base usa **PHP 8.2 ou superior**, [Composer](https://getcomposer.org/), SQLite e Laravel 12.

## Requisitos

- PHP 8.2+ com as extensões SQLite, OpenSSL, Mbstring, XML, Ctype, JSON, Tokenizer e Fileinfo;
- Composer 2;
- Node.js 20+ e npm, somente quando for necessário compilar recursos visuais.

## Primeira execução

```bash
git clone https://github.com/leandromichelsenbr/usinadocs.git
cd usinadocs
composer install
cp .env.example .env
```

No Windows, copie manualmente `.env.example` para `.env` se o comando `cp` não estiver disponível. Em seguida, crie o arquivo vazio `database/database.sqlite` e execute:

```bash
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Abra `http://127.0.0.1:8000`. A página inicial e a demonstração em `/p/bem-vindo` devem estar disponíveis.

## Primeiro administrador

O projeto não cria contas ou senhas padrão. Em outro terminal, execute:

```bash
php artisan user:make-admin seu-email@exemplo.com --name="Seu nome"
```

O sistema solicitará a senha de forma oculta. Depois, acesse `/login`; o painel inicial estará em `/admin`.

## Fluxo editorial inicial

No painel, abra **Administrar páginas** para criar uma página em rascunho. Você pode adicionar blocos de texto, código e referência. Salvar mantém o conteúdo como rascunho; a ação **Publicar revisão** torna essa versão pública em `/p/seu-slug`.

Para atualizar uma página publicada, use **Abrir nova revisão**. A publicação anterior permanece preservada e a nova revisão começa como rascunho.

## Configuração

O arquivo `.env` é local e não deve ser enviado ao Git. Para esta etapa, mantenha `DB_CONNECTION=sqlite` e aponte `DB_DATABASE` para o arquivo `database/database.sqlite` quando sua instalação exigir o caminho explícito.

## Verificação

Após instalar as dependências, execute:

```bash
php artisan test
```

## Limitações desta etapa

Esta é uma base pré-alpha. O painel prova a fronteira de acesso administrativo; a interface para criar e editar páginas, as traduções, a mídia e os cursos entrarão em tarefas posteriores.
