#!/usr/bin/env bash
# EN: Deploys main to the Usina News installation (news.usinabr.com.br). It is
# installed as ~/bin/deploy-usina-news.sh and runs from there, never from the
# checkout, because `git reset` below rewrites the checkout while bash is still
# reading this file. Called by GitHub Actions through an SSH key restricted to
# this command, or by hand.
# PT: Publica a main na instalação da Usina News (news.usinabr.com.br). É
# instalado como ~/bin/deploy-usina-news.sh e roda de lá, nunca do checkout,
# porque o `git reset` abaixo reescreve o checkout enquanto o bash ainda lê este
# arquivo. Chamado pelo GitHub Actions por uma chave SSH restrita a este
# comando, ou manualmente.
#
# EN: The DEPLOY_* variables only exist so the script can be tested on a copy.
# PT: As variáveis DEPLOY_* existem apenas para testar o script numa cópia.
set -euo pipefail

SITE=${DEPLOY_SITE:-/var/www/html/news}
BACKUPS="$HOME/backups/usina-news"
KEEP_BACKUPS=30
URL=${DEPLOY_URL:-https://news.usinabr.com.br/}
# EN: The Usina News FPM service loads pdo_sqlite from this extra directory; the CLI needs the same one.
# PT: O serviço FPM da Usina News carrega o pdo_sqlite desta pasta extra; a CLI precisa da mesma.
export PHP_INI_SCAN_DIR="${DEPLOY_PHP_INI_SCAN_DIR:-/etc/php/8.3/fpm/conf.d:$HOME/.local/share/usina-news-dev/conf.d}"
PHP=${DEPLOY_PHP:-php8.3}

log() { printf '[%s] %s\n' "$(date '+%F %T')" "$*"; }

# EN: One deploy at a time. PT: Um deploy por vez.
exec 9>"${XDG_RUNTIME_DIR:-/tmp}/deploy-usina-news.lock"
flock -n 9 || { log "outro deploy em andamento"; exit 1; }

cd "$SITE"
grep -q '^USINADOCS_NEWS_HOME=1' core/.env || { log "core/.env sem USINADOCS_NEWS_HOME=1; abortado"; exit 1; }
$PHP -r 'exit(in_array("sqlite", PDO::getAvailableDrivers(), true) ? 0 : 1);' || { log "PHP sem pdo_sqlite; abortado"; exit 1; }

git fetch -q origin main
current=$(git rev-parse HEAD)
target=$(git rev-parse origin/main)
if [ "$current" = "$target" ] && [ -z "$(git status --porcelain --untracked-files=no -- . ':!AGENTS.md')" ]; then
    log "já em ${target:0:7}; nada a fazer"
    exit 0
fi

# EN: The server has no Composer, so continue only when dependencies are unchanged.
# PT: O servidor não tem Composer; só segue se as dependências não mudaram.
if ! git diff --quiet "$current" "$target" -- core/composer.json core/composer.lock; then
    log "dependências mudaram entre ${current:0:7} e ${target:0:7}; instale o Composer e atualize manualmente"
    exit 1
fi

# EN: VACUUM INTO writes a consistent copy even while the site keeps writing.
# PT: O VACUUM INTO grava uma cópia consistente mesmo com o site gravando.
stamp="$(date +%F-%H%M%S)-${target:0:7}-$$"
mkdir -p "$BACKUPS"
$PHP -r '$db = new PDO("sqlite:".$argv[1]); $db->exec("VACUUM INTO ".$db->quote($argv[2]));' \
    core/database/database.sqlite "$BACKUPS/database-$stamp.sqlite"
ls -1t "$BACKUPS"/database-*.sqlite | tail -n +$((KEEP_BACKUPS + 1)) | xargs -r rm -f
log "backup: $BACKUPS/database-$stamp.sqlite"

# EN: The Usina News AGENTS.md directive belongs to this installation only; reapply it after every sync.
# PT: A diretriz da Usina News no AGENTS.md pertence só a esta instalação; é reaplicada a cada sincronização.
agents=$(mktemp); cp AGENTS.md "$agents"
sync_to() {
    git reset -q --hard "$1"
    cp "$agents" AGENTS.md
    git rev-parse HEAD > core/BUILD_REVISION
}

sync_to "$target"
(cd core && $PHP bin/migrate.php)
log "código em ${target:0:7}"

# EN: Check the homepage; on failure roll the code back. The database is not restored, so
# writes made after the deploy are kept (the backup above remains available).
# PT: Confere a capa; se falhar, volta o código. O banco não é restaurado, para preservar
# gravações feitas após o deploy (o backup acima continua disponível).
if ! curl -fsS --max-time 20 -o /dev/null "$URL"; then
    log "capa sem resposta após o deploy; revertendo para ${current:0:7}"
    sync_to "$current"
    curl -fsS --max-time 20 -o /dev/null "$URL" && log "revertido" || log "ATENÇÃO: capa continua sem resposta"
    rm -f "$agents"
    exit 1
fi
rm -f "$agents"
log "deploy concluído: $URL (${target:0:7})"
