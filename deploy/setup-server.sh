#!/usr/bin/env bash
# Sekali jalan di VM Ubuntu 24.04 baru (Oracle Cloud / Google Cloud).
# Menyiapkan dua situs terpisah: demo (branch demo) dan qa (branch qa).
#   sudo bash setup-server.sh
set -euo pipefail

REPO="${REPO:-git@github.com:SemestaaaaA/PROJECT-KABELOTA.git}"
APP_USER="${SUDO_USER:-ubuntu}"
IP="$(curl -4 -s https://ifconfig.me)"
HOST_SUFFIX="${HOST_SUFFIX:-$(echo "$IP" | tr . -).sslip.io}"

echo "==> Paket sistem (PHP 8.4, MySQL, Caddy, Node 22, Supervisor)"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y software-properties-common curl git unzip supervisor mysql-server debian-keyring debian-archive-keyring apt-transport-https gnupg
add-apt-repository -y ppa:ondrej/php
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor --yes -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' > /etc/apt/sources.list.d/caddy-stable.list
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt-get update -y
apt-get install -y caddy nodejs \
    php8.4-fpm php8.4-cli php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip php8.4-gd \
    php8.4-intl php8.4-sqlite3 php8.4-mysql php8.4-bcmath
if ! command -v composer >/dev/null; then
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

echo "==> Batas unggah (foto 5MB, PDF 3MB, beberapa file sekaligus)"
for sapi in fpm cli; do
    printf 'upload_max_filesize=8M\npost_max_size=20M\nmemory_limit=256M\n' > /etc/php/8.4/$sapi/conf.d/99-kabelota.ini
done
systemctl restart php8.4-fpm

echo "==> Firewall bawaan Oracle: buka port 80/443"
if iptables -L INPUT -n | grep -q 'REJECT'; then
    iptables -C INPUT -p tcp --dport 80 -j ACCEPT 2>/dev/null || iptables -I INPUT 5 -p tcp --dport 80 -j ACCEPT
    iptables -C INPUT -p tcp --dport 443 -j ACCEPT 2>/dev/null || iptables -I INPUT 5 -p tcp --dport 443 -j ACCEPT
    apt-get install -y iptables-persistent && netfilter-persistent save
fi

echo "==> Database MySQL untuk QA"
QA_DB_PASS="$(openssl rand -hex 16)"
mysql -e "CREATE DATABASE IF NOT EXISTS kabelota_qa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'kabelota_qa'@'localhost' IDENTIFIED BY '${QA_DB_PASS}';
ALTER USER 'kabelota_qa'@'localhost' IDENTIFIED BY '${QA_DB_PASS}';
GRANT ALL ON kabelota_qa.* TO 'kabelota_qa'@'localhost'; FLUSH PRIVILEGES;"

echo "==> Folder situs"
mkdir -p /var/www
chown "$APP_USER":www-data /var/www
for site in demo qa; do
    dir="/var/www/kabelota-$site"
    if [ ! -d "$dir/.git" ]; then
        sudo -u "$APP_USER" git clone --branch "$site" "$REPO" "$dir"
    fi
    if [ ! -f "$dir/.env" ]; then
        sudo -u "$APP_USER" cp "$dir/deploy/env.$site.example" "$dir/.env"
        sed -i "s#^APP_URL=.*#APP_URL=https://$site.$HOST_SUFFIX#" "$dir/.env"
        if [ "$site" = qa ]; then sed -i "s#^DB_PASSWORD=.*#DB_PASSWORD=$QA_DB_PASS#" "$dir/.env"; fi
        # Random passwords for the seeded admin and HRD accounts.
        sed -i "s#^KABELOTA_ADMIN_PASSWORD=.*#KABELOTA_ADMIN_PASSWORD=$(openssl rand -base64 12 | tr -d '/+=')#" "$dir/.env"
        sed -i "s#^KABELOTA_DEMO_PASSWORD=.*#KABELOTA_DEMO_PASSWORD=$(openssl rand -base64 12 | tr -d '/+=')#" "$dir/.env"
    fi
done

echo "==> Caddy (HTTPS otomatis)"
sed "s#{HOST_SUFFIX}#$HOST_SUFFIX#g" /var/www/kabelota-demo/deploy/Caddyfile > /etc/caddy/Caddyfile
systemctl reload caddy

echo "==> Queue worker + scheduler"
for site in demo qa; do
    cat > /etc/supervisor/conf.d/kabelota-$site.conf <<CONF
[program:kabelota-$site-queue]
command=php /var/www/kabelota-$site/artisan queue:work --sleep=3 --tries=3 --max-time=3600
user=www-data
autostart=true
autorestart=true
stopwaitsecs=60
redirect_stderr=true
stdout_logfile=/var/www/kabelota-$site/storage/logs/queue.log
CONF
    echo "* * * * * www-data cd /var/www/kabelota-$site && php artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/kabelota-$site
done

echo
echo "Selesai. Langkah berikutnya (sebagai $APP_USER, bukan root):"
echo "  bash /var/www/kabelota-demo/deploy/deploy.sh demo --fresh"
echo "  bash /var/www/kabelota-qa/deploy/deploy.sh qa --fresh"
echo
echo "Demo: https://demo.$HOST_SUFFIX"
echo "QA:   https://qa.$HOST_SUFFIX"
echo "Sandi admin, HRD contoh, dan DB QA ada di file .env masing-masing situs:"
echo "  grep PASSWORD /var/www/kabelota-demo/.env /var/www/kabelota-qa/.env"
