#!/usr/bin/env bash
#
# Deploy / perbarui Tejas Coffee POS di server produksi.
#
#   bash deploy/deploy-app.sh [--seed] [--url=https://domain-atau-ip]
#
# --seed  : jalankan seeder demo (akun admin/kasir/barista/pelanggan + menu awal).
#           Pakai pada deploy pertama saja, karena seeder tidak menghapus data lama
#           tetapi menambah ulang baris yang sudah ada.
# --url   : isi APP_URL & APP_PUBLIC_URL pada .env bila berkas .env baru dibuat.
#
set -euo pipefail

cd "$(dirname "$0")/.."
APP_DIR="$(pwd)"

# Skrip ini biasanya dijalankan dengan sudo; beri tahu Composer agar tidak menolak.
export COMPOSER_ALLOW_SUPERUSER=1

RUN_SEED=0
APP_URL_ARG=""
for arg in "$@"; do
    case "$arg" in
        --seed) RUN_SEED=1 ;;
        --url=*) APP_URL_ARG="${arg#--url=}" ;;
        *) echo "Opsi tidak dikenal: $arg" >&2; exit 1 ;;
    esac
done

if [ -f /etc/tejas-pos/deploy.env ]; then
    # shellcheck disable=SC1091
    . /etc/tejas-pos/deploy.env
fi
DB_NAME="${DB_NAME:-tejas_pos}"
DB_USER="${DB_USER:-tejas}"
DB_PASS="${DB_PASS:-}"
export DB_NAME DB_USER DB_PASS

# Folder yang harus bisa ditulis web server.
tulis_storage() {
    if [ "$(id -u)" -eq 0 ]; then
        chown -R www-data:www-data storage bootstrap/cache
        chmod -R ug+rwX storage bootstrap/cache
    else
        sudo chown -R www-data:www-data storage bootstrap/cache
        sudo chmod -R ug+rwX storage bootstrap/cache
    fi
}

echo "==> [1/8] Mode maintenance"
php artisan down --retry=15 >/dev/null 2>&1 || true

echo "==> [2/8] Dependensi PHP (tanpa paket dev)"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "==> [3/8] Berkas .env"
if [ ! -f .env ]; then
    cp deploy/.env.production.example .env
    # Ganti kredensial database dari /etc/tejas-pos/deploy.env
    php -r '
        $f = ".env";
        $s = file_get_contents($f);
        $map = [
            "DB_DATABASE" => getenv("DB_NAME") ?: "tejas_pos",
            "DB_USERNAME" => getenv("DB_USER") ?: "tejas",
            "DB_PASSWORD" => getenv("DB_PASS") ?: "",
        ];
        foreach ($map as $k => $v) {
            $v = str_replace(["\\", "\""], ["\\\\", "\\\""], (string) $v);
            $s = preg_replace("/^".$k."=.*$/m", $k."=\"".$v."\"", $s);
        }
        file_put_contents($f, $s);
    '
    if [ -n "$APP_URL_ARG" ]; then
        sed -i "s|^APP_URL=.*|APP_URL=${APP_URL_ARG}|" .env
        sed -i "s|^APP_PUBLIC_URL=.*|APP_PUBLIC_URL=${APP_URL_ARG}|" .env
    fi
    php artisan key:generate --force
    echo "    .env dibuat. Lengkapi MIDTRANS_* bila pembayaran online dipakai."
else
    echo "    .env sudah ada, dibiarkan apa adanya."
fi

echo "==> [4/8] Aset frontend (Vite)"
npm ci --no-audit --no-fund
npm run build

echo "==> [5/8] Migrasi database"
php artisan migrate --force
if [ "$RUN_SEED" -eq 1 ]; then
    echo "    Menjalankan seeder demo..."
    php artisan db:seed --force
fi

echo "==> [6/8] Tautan storage & izin folder"
php artisan storage:link >/dev/null 2>&1 || true
tulis_storage

echo "==> [7/8] Cache produksi"
php artisan optimize:clear >/dev/null
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:optimize >/dev/null 2>&1 || true

echo "==> [8/8] Selesai, keluar dari mode maintenance"
php artisan up

echo
echo "Deploy selesai. Cek aplikasi di: $(grep -m1 '^APP_URL=' .env | cut -d= -f2-)"
