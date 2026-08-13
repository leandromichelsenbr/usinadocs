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

## Estado atual

Esta primeira fatia demonstra o fluxo público mínimo: SQLite, migração, conteúdo de demonstração, rota localizada, página publicada e blocos de texto/código. Autenticação, administração, revisões editáveis, mídia e importação serão adicionados em fatias posteriores.
