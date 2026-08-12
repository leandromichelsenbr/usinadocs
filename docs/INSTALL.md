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

## Configuração

O arquivo `.env` é local e não deve ser enviado ao Git. Para esta etapa, mantenha `DB_CONNECTION=sqlite` e aponte `DB_DATABASE` para o arquivo `database/database.sqlite` quando sua instalação exigir o caminho explícito.

## Verificação

Após instalar as dependências, execute:

```bash
php artisan test
```

## Limitações desta etapa

Esta é a fundação técnica do pré-alpha. A demonstração agora é criada a partir do núcleo editorial: página, revisão publicada e blocos estruturados. Banco de conteúdo, contas, tradução, editor e cursos entrarão em tarefas posteriores.
