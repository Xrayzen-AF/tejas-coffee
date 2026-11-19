#!/usr/bin/env bash
#
# Mengemas Tejas Coffee POS untuk InfinityFree (shared hosting, tanpa SSH/Composer/artisan).
#
#   bash deploy/infinityfree/build-package.sh
#
# Hasil:
#   dist/infinityfree-htdocs/       -> isi folder ini yang diunggah ke htdocs
#   dist/tejas-infinityfree.zip     -> versi zip dari folder di atas
#   dist/tejas-database.sql         -> impor lewat phpMyAdmin (kunci Midtrans sudah dikosongkan)
#
set -euo pipefail

cd "$(dirname "$0")/../.."
ROOT="$(pwd)"
DIST="${ROOT}/dist"
STAGE="${DIST}/infinityfree-htdocs"
ZIP="${DIST}/tejas-infinityfree.zip"
SQL="${DIST}/tejas-database.sql"

COMPOSER_PHAR="${COMPOSER_PHAR:-/c/Users/ASAKI/.config/herd-lite/bin/composer.phar}"
MYSQLDUMP="${MYSQLDUMP:-/c/xampp/mysql/bin/mysqldump.exe}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3307}"
DB_NAME="${DB_NAME:-coffee_shop_order}"
DB_USER="${DB_USER:-root}"
APP_URL_TARGET="${APP_URL_TARGET:-https://ganti-subdomain.free.nf}"

echo "==> [1/6] Menyiapkan folder dist"
rm -rf "$STAGE" "$ZIP" "$SQL"
mkdir -p "$STAGE"

echo "==> [2/6] Menyalin berkas aplikasi (tanpa tests/docs/node_modules)"
for dir in app bootstrap config database public resources routes storage; do
    cp -a "$ROOT/$dir" "$STAGE/$dir"
done
for file in artisan composer.json composer.lock; do
    cp -a "$ROOT/$file" "$STAGE/$file"
done

# Cache lokal tidak boleh ikut: path dan isinya milik mesin ini.
rm -rf "$STAGE"/bootstrap/cache/*.php
rm -rf "$STAGE"/storage/framework/cache/data/* "$STAGE"/storage/framework/sessions/* \
       "$STAGE"/storage/framework/views/* "$STAGE"/storage/framework/*.sqlite
rm -rf "$STAGE"/storage/logs/*.log "$STAGE"/public/storage
find "$STAGE" -name '.DS_Store' -delete 2>/dev/null || true

echo "==> [3/6] composer install --no-dev (di dalam paket)"
php "$COMPOSER_PHAR" install --no-dev --no-interaction --prefer-dist \
    --working-dir="$STAGE"

# Autoloader optimized bisa >1 MB dan ditolak diam-diam oleh InfinityFree.
php -r '
$f = $argv[1];
$j = json_decode(file_get_contents($f), true);
$j["config"]["optimize-autoloader"] = false;
file_put_contents($f, json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
' "$STAGE/composer.json"
php "$COMPOSER_PHAR" dump-autoload --no-dev --working-dir="$STAGE" --no-interaction >/dev/null

echo "    Memeriksa batas 1 MB per berkas PHP..."
BESAR="$(find "$STAGE" -name '*.php' -size +1000k)"
if [ -n "$BESAR" ]; then
    echo "    PERINGATAN: berkas PHP berikut >1 MB dan akan gagal diunggah:" >&2
    echo "$BESAR" >&2
else
    echo "    OK: tidak ada berkas PHP di atas 1 MB."
fi

echo "==> [4/6] Build aset Vite lalu salin ke paket"
npm run build >/dev/null
rm -rf "$STAGE/public/build"
cp -a "$ROOT/public/build" "$STAGE/public/build"

echo "==> [5/6] Membuat .env produksi + .htaccess htdocs"
APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
cat > "$STAGE/.env" <<ENV
APP_NAME="Tejas Coffee POS"
APP_ENV=production
APP_KEY=${APP_KEY}
APP_DEBUG=false
# GANTI dengan alamat InfinityFree Anda (pakai https)
APP_URL=${APP_URL_TARGET}
APP_PUBLIC_URL=${APP_URL_TARGET}

APP_LOCALE=id
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=id_ID

APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=error

# GANTI dengan kredensial dari VistaPanel -> MySQL Databases
DB_CONNECTION=mysql
DB_HOST=ganti-host.infinityfree.com
DB_PORT=3306
DB_DATABASE=ganti_nama_database
DB_USERNAME=ganti_username
DB_PASSWORD=ganti_password

# InfinityFree tidak punya Redis/queue worker dan tidak bisa menulis di luar htdocs.
SESSION_DRIVER=file
SESSION_LIFETIME=120
CACHE_STORE=file
QUEUE_CONNECTION=sync

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local

MAIL_MAILER=log

VITE_APP_NAME="\${APP_NAME}"

# Isi lewat Admin -> Store Settings (jangan simpan kunci asli di repo)
MIDTRANS_SERVER_KEY=
MIDTRANS_CLIENT_KEY=
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_QRIS_ACQUIRER=
ENV

cat > "$STAGE/.htaccess" <<'HTACCESS'
# InfinityFree: web root selalu htdocs, jadi semua permintaan diarahkan ke public/.
# Hanya file ini yang perlu di htdocs; public/.htaccess milik Laravel jangan diubah.
RewriteEngine On
RewriteRule ^storage/(.*)$ /storage/app/public/$1 [END]
RewriteRule (.*) /public/$1 [L]
HTACCESS

echo "==> [6/6] Dump database untuk phpMyAdmin (kunci Midtrans dikosongkan)"
"$MYSQLDUMP" -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" \
    --default-character-set=utf8mb4 --skip-comments --no-tablespaces \
    --single-transaction "$DB_NAME" > "$SQL"

# Jangan pernah mengunggah kunci Midtrans yang pernah bocor.
php -r '
$f = $argv[1];
$s = file_get_contents($f);
$s = preg_replace("/(Mid-server|Mid-client)-[A-Za-z0-9_\-]+/", "", $s);
file_put_contents($f, $s);
' "$SQL"
if grep -qE 'Mid-(server|client)-' "$SQL"; then
    echo "    PERINGATAN: masih ada kunci Midtrans di dump!" >&2
    exit 1
fi
echo "    OK: dump bersih dari kunci Midtrans."

echo
echo "Ringkasan paket:"
echo "  folder : $STAGE"
echo "  berkas : $(find "$STAGE" -type f | wc -l) file"
echo "  ukuran : $(du -sh "$STAGE" | cut -f1)"
echo "  dump   : $SQL ($(du -h "$SQL" | cut -f1))"
