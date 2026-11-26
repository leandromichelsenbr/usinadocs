# Deploy automático da Usina News / Usina News automatic deploy

**PT** — A instalação em `news.usinabr.com.br` (`/var/www/html/news`) é atualizada a partir da `main` sempre que o workflow **Core tests** passa num push para a `main`. O workflow [`deploy-news.yml`](../../.github/workflows/deploy-news.yml) só abre uma conexão SSH; a chave instalada no servidor está restrita ao comando `~/bin/deploy-usina-news.sh`, cópia de [`deploy.sh`](deploy.sh).

O script:

1. recusa seguir se o `.env` não tiver `USINADOCS_NEWS_HOME=1`, se o PHP não tiver `pdo_sqlite` ou se `composer.json`/`composer.lock` mudarem (o servidor não tem Composer);
2. faz backup consistente do banco em `~/backups/usina-news/` (`VACUUM INTO`, últimos 30);
3. sincroniza o checkout com `origin/main` e reaplica o `AGENTS.md` próprio da instalação;
4. roda as migrações com o mesmo `PHP_INI_SCAN_DIR` do serviço `usina-news-fpm`;
5. grava `core/BUILD_REVISION` e confere a capa; se ela não responder, volta o código para a revisão anterior.

**Configuração inicial (uma vez, no servidor):**

```bash
git -C /var/www/html/news fetch -q origin && git -C /var/www/html/news show origin/main:deploy/usina-news/setup.sh | bash
```

Depois, crie em *Settings → Secrets and variables → Actions* os quatro secrets que o comando mostra: `NEWS_DEPLOY_HOST`, `NEWS_DEPLOY_USER`, `NEWS_KNOWN_HOSTS` e `NEWS_DEPLOY_KEY`. Sem eles o workflow é pulado. Para um deploy manual: `~/bin/deploy-usina-news.sh` no servidor ou *Actions → Deploy Usina News → Run workflow*.

**EN** — `news.usinabr.com.br` is updated from `main` after **Core tests** passes on a push. The workflow only opens SSH; the server key is restricted to `~/bin/deploy-usina-news.sh` (a copy of `deploy.sh`), which backs up the database, syncs the checkout, keeps the installation's `AGENTS.md`, runs migrations with the FPM service's PHP configuration and rolls the code back if the homepage stops answering. Run `setup.sh` once on the server (command above) and add the four secrets it prints.
