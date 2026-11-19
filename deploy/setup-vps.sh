#!/usr/bin/env bash
#
# Persiapan server untuk Tejas Coffee POS (Oracle Cloud Always Free, Ubuntu 24.04 amd64/arm64).
# Jalankan SEKALI sebagai root dari dalam folder proyek:
#
#   sudo DOMAIN=pos.contoh.com DB_PASS='rahasia-kuat' bash deploy/setup-vps.sh
#
# DOMAIN   : nama domain/IP yang dipakai. Default "_" (akses lewat IP publik).
# DB_PASS  : password user MySQL aplikasi. Bila dikosongkan, dibuat otomatis
#            (hex acak) dan ditulis ke /etc/tejas-pos/deploy.env.
# APP_USER : pemilik folder proyek. Default user yang menjalankan sudo.
#
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/tejas-pos}"
APP_USER="${APP_USER:-${SUDO_USER:-ubuntu}}"
DOMAIN="${DOMAIN:-_}"
DB_NAME="${DB_NAME:-tejas_pos}"
DB_USER="${DB_USER:-tejas}"
DB_PASS="${DB_PASS:-}"

if [ "$(id -u)" -ne 0 ]; then
    echo "Jalankan dengan sudo: sudo DB_PASS=... bash deploy/setup-vps.sh" >&2
    exit 1
fi

if [ -z "$DB_PASS" ]; then
    DB_PASS="$(openssl rand -hex 16 2>/dev/null || php -r 'echo bin2hex(random_bytes(16));')"
    echo "    DB_PASS dibuat otomatis dan disimpan di /etc/tejas-pos/deploy.env" >&2
fi

export DEBIAN_FRONTEND=noninteractive

echo "==> [1/8] Memperbarui paket sistem"
apt-get update -y
apt-get upgrade -y

echo "==> [2/8] Memasang Nginx, MySQL, PHP 8.3, Git"
apt-get install -y nginx mysql-server git unzip curl ca-certificates \
    php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml \
    php8.3-curl php8.3-zip php8.3-bcmath php8.3-gd php8.3-intl

PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
echo "    PHP aktif: ${PHP_VER}"

echo "==> [3/8] Memasang Composer"
if ! command -v composer >/dev/null 2>&1; then
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
fi

echo "==> [4/8] Memasang Node.js 22 (hanya untuk build aset Vite)"
if ! command -v node >/dev/null 2>&1; then
    curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
    apt-get install -y nodejs
fi

echo "==> [5/8] Menyiapkan database MySQL"
mysql --protocol=socket -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
systemctl enable --now mysql

echo "==> [6/8] Menyamakan folder proyek ke ${APP_DIR}"
mkdir -p "$APP_DIR"
if [ "$(pwd -P)" != "$(cd "$APP_DIR" && pwd -P)" ]; then
    cp -a "$(pwd -P)/." "$APP_DIR/"
fi
chown -R "${APP_USER}":"${APP_USER}" "$APP_DIR"

echo "==> [7/8] Konfigurasi Nginx + PHP-FPM"
cat > "/etc/php/${PHP_VER}/fpm/conf.d/99-tejas-pos.ini" <<'INI'
upload_max_filesize = 8M
post_max_size = 8M
memory_limit = 256M
INI

sed -e "s|__APP_DIR__|${APP_DIR}|g" \
    -e "s|__PHP_VER__|${PHP_VER}|g" \
    -e "s|__DOMAIN__|${DOMAIN}|g" \
    "${APP_DIR}/deploy/nginx-tejas-pos.conf" > /etc/nginx/sites-available/tejas-pos.conf

ln -sf /etc/nginx/sites-available/tejas-pos.conf /etc/nginx/sites-enabled/tejas-pos.conf
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl enable --now "php${PHP_VER}-fpm"
systemctl reload nginx

echo "==> [8/8] Scheduler Laravel (cron tiap menit)"
mkdir -p /etc/tejas-pos
cat > /etc/tejas-pos/deploy.env <<ENV
APP_DIR=${APP_DIR}
APP_USER=${APP_USER}
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASS=${DB_PASS}
DOMAIN=${DOMAIN}
ENV
chmod 600 /etc/tejas-pos/deploy.env

cat > /etc/cron.d/tejas-pos <<CRON
SHELL=/bin/bash
PATH=/usr/local/bin:/usr/bin:/bin
* * * * * ${APP_USER} cd ${APP_DIR} && php artisan schedule:run >> /var/log/tejas-pos-cron.log 2>&1
CRON
chmod 644 /etc/cron.d/tejas-pos

# Image Ubuntu resmi Oracle menyisipkan aturan iptables yang memblokir 80/443.
# Tambahkan aturan bila belum ada, lalu simpan agar tetap berlaku setelah reboot.
for PORT in 80 443; do
    if ! iptables -C INPUT -p tcp --dport "$PORT" -j ACCEPT 2>/dev/null; then
        iptables -I INPUT 6 -m state --state NEW -p tcp --dport "$PORT" -j ACCEPT || \
            iptables -I INPUT -p tcp --dport "$PORT" -j ACCEPT
    fi
done
if command -v netfilter-persistent >/dev/null 2>&1; then
    netfilter-persistent save >/dev/null
fi
if command -v ufw >/dev/null 2>&1 && ufw status | grep -q "Status: active"; then
    ufw allow OpenSSH >/dev/null
    ufw allow "Nginx Full" >/dev/null
fi

cat <<DONE

Selesai. Langkah berikutnya:

  1) Pastikan porta 80 (dan 443 bila pakai domain + Certbot) terbuka di
     Oracle Console -> VCN -> Security List (Ingress Rules).
  2) Jalankan deploy aplikasi:
       cd ${APP_DIR} && sudo -u ${APP_USER} bash deploy/deploy-app.sh --seed
     (sekali lagi tanpa --seed pada deploy berikutnya)

DONE
