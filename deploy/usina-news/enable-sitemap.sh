#!/usr/bin/env bash
set -euo pipefail

# EN: Add two read-only routes to the existing HTTPS VirtualHost allowlist. Keep its FastCGI handler and all other restrictions.
# PT: Adiciona duas rotas somente de leitura à lista autorizada do VirtualHost HTTPS. Preserva o manipulador FastCGI e as demais restrições.
if (( EUID != 0 )); then
    printf 'Execute este script com sudo.\n' >&2
    exit 1
fi

config=/etc/apache2/sites-available/news.usinabr.com.br-ssl.conf
old='|favicon\.ico)?$">'
new='|favicon\.ico|sitemap\.xml|robots\.txt)?$">'
if grep -Fq "$new" "$config"; then
    apache2ctl configtest
    systemctl reload apache2
    exit
fi

if [[ $(grep -Fc "$old" "$config") != 1 ]]; then
    printf 'A regra de leitura do VirtualHost mudou. Nenhum arquivo foi editado.\n' >&2
    exit 1
fi

# EN: Keep a root-owned backup and restore it if Apache rejects the change.
# PT: Guarda uma cópia pertencente ao root e a restaura se o Apache rejeitar a alteração.
backup=$(mktemp /etc/apache2/sites-available/news-sitemap-backup.XXXXXX)
cp --preserve=mode,ownership,timestamps "$config" "$backup"
sed 's/|favicon\\\.ico)?/|favicon\\.ico|sitemap\\.xml|robots\\.txt)?/' "$backup" > "$config"
if ! grep -Fq "$new" "$config" || ! apache2ctl configtest || ! systemctl reload apache2; then
    cp --preserve=mode,ownership,timestamps "$backup" "$config"
    apache2ctl configtest && systemctl reload apache2
    exit 1
fi
printf 'Rotas liberadas. Backup do VirtualHost em %s\n' "$backup"
