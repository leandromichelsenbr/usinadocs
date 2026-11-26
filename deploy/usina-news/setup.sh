#!/usr/bin/env bash
# EN: One-time setup of automatic deploys for the Usina News installation. Run it
# on the server as the site owner:
#   git -C /var/www/html/news fetch -q origin && git -C /var/www/html/news show origin/main:deploy/usina-news/setup.sh | bash
# It installs ~/bin/deploy-usina-news.sh, creates an SSH key that can only run
# that script, prints the four GitHub secrets and runs a first deploy. Running it
# again replaces the key (the old one stops working) and updates the script.
# PT: Configuração inicial, feita uma vez, do deploy automático da Usina News.
# Rode no servidor com o usuário dono do site:
#   git -C /var/www/html/news fetch -q origin && git -C /var/www/html/news show origin/main:deploy/usina-news/setup.sh | bash
# Instala ~/bin/deploy-usina-news.sh, cria uma chave SSH que só consegue rodar
# esse script, mostra os quatro secrets do GitHub e faz um primeiro deploy.
# Rodar de novo troca a chave (a antiga deixa de funcionar) e atualiza o script.
set -euo pipefail

SITE=/var/www/html/news
HOST=usinabr.com.br
MARKER=usina-news-deploy
SCRIPT="$HOME/bin/deploy-usina-news.sh"
KEY="$HOME/.ssh/$MARKER"

# EN: Install the deploy script from origin/main, outside the checkout it rewrites.
# PT: Instala o script de deploy a partir de origin/main, fora do checkout que ele reescreve.
git -C "$SITE" fetch -q origin main
install -d -m 700 "$HOME/bin" "$HOME/.ssh"
git -C "$SITE" show origin/main:deploy/usina-news/deploy.sh > "$SCRIPT.new"
chmod 700 "$SCRIPT.new" && mv "$SCRIPT.new" "$SCRIPT"

# EN: A fresh key without passphrase: GitHub Actions cannot type one. `restrict`
# disables forwarding, PTY and X11; `command=` makes every login run only the
# deploy script, whatever the client asks for.
# PT: Uma chave nova sem senha, porque o GitHub Actions não consegue digitá-la.
# `restrict` desliga redirecionamentos, PTY e X11; `command=` faz todo login
# executar só o script de deploy, não importa o que o cliente peça.
rm -f "$KEY" "$KEY.pub"
ssh-keygen -q -t ed25519 -N '' -C "$MARKER" -f "$KEY"
touch "$HOME/.ssh/authorized_keys" && chmod 600 "$HOME/.ssh/authorized_keys"
grep -v " $MARKER\$" "$HOME/.ssh/authorized_keys" > "$HOME/.ssh/authorized_keys.new" || true
printf 'restrict,command="%s" %s\n' "$SCRIPT" "$(cat "$KEY.pub")" >> "$HOME/.ssh/authorized_keys.new"
chmod 600 "$HOME/.ssh/authorized_keys.new" && mv "$HOME/.ssh/authorized_keys.new" "$HOME/.ssh/authorized_keys"

echo
echo "=== Crie estes 4 secrets em GitHub > usinadocs > Settings > Secrets and variables > Actions ==="
echo
echo "NEWS_DEPLOY_HOST"
echo "$HOST"
echo
echo "NEWS_DEPLOY_USER"
whoami
echo
echo "NEWS_KNOWN_HOSTS"
for pub in /etc/ssh/ssh_host_*_key.pub; do printf '%s %s\n' "$HOST" "$(cut -d' ' -f1,2 "$pub")"; done
echo
echo "NEWS_DEPLOY_KEY  (copie tudo, das linhas BEGIN até END)"
cat "$KEY"
echo

# EN: The private key only has to live in GitHub; removing it from the server
# means a copy of this machine's files cannot be used to trigger deploys.
# PT: A chave privada só precisa existir no GitHub; removê-la do servidor impede
# que uma cópia dos arquivos desta máquina seja usada para disparar deploys.
rm -f "$KEY"
echo "(a chave privada foi apagada do servidor; se perder a cópia, rode este setup de novo)"
echo

echo "=== Primeiro deploy ==="
"$SCRIPT"
