# Deployment & Kontinjensi Offline

Dua jalur deployment wajib siap sebelum hari-H: **staging cloud** (utama) dan
**server lokal LAN** (cadangan bila internet ruang ujian putus).

## 1. Prasyarat Server

| Komponen | Versi minimum |
| :--- | :--- |
| PHP | 8.2 (ekstensi `pdo_mysql`, `mbstring`, `openssl`, `bcmath`, `fileinfo`) |
| Composer | 2.x |
| MySQL / MariaDB | MySQL 8 atau MariaDB 10.6 |
| Node.js | 20.x (hanya untuk build aset; tidak diperlukan di server produksi) |
| Web server | Nginx/Apache dengan document root mengarah ke `public/` |

`.env` produksi wajib berisi `APP_ENV=production` dan `APP_DEBUG=false`. Jangan
pernah menaikkan folder proyek utuh ke `public_html`; hanya isi `public/` yang
boleh diakses langsung.

## 2. Langkah Deploy Staging (Utama)

```bash
# 1. Ambil kode versi rilis
git clone <url-repo> tejas-pos && cd tejas-pos
git checkout v1.0.0-rc

# 2. Dependensi tanpa paket pengembangan
composer install --no-dev --optimize-autoloader

# 3. Environment
cp .env.example .env
php artisan key:generate
#   sesuaikan: APP_URL, DB_*, APP_ENV=production, APP_DEBUG=false, LOG_LEVEL=warning

# 4. Skema + data awal
php artisan migrate --force
php artisan db:seed --force

# 5. Aset frontend (build di mesin lokal lalu unggah folder public/build bila
#    Node tidak tersedia di server)
npm ci && npm run build

# 6. Optimasi produksi
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```

Scheduler (wajib, untuk `meja:bersihkan`) lewat cron:

```cron
* * * * * cd /var/www/tejas-pos && php artisan schedule:run >> /dev/null 2>&1
```

Konfigurasi Nginx ringkas:

```nginx
server {
    listen 80;
    server_name pos.example.com;
    root /var/www/tejas-pos/public;

    index index.php;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }
}
```

### Verifikasi Staging (isi saat pelaksanaan)

| # | Pemeriksaan | Target | Hasil |
| :---: | :--- | :--- | :--- |
| 1 | Halaman login terbuka | HTTP 200, < 1,5 detik | |
| 2 | Login 4 peran | Redirect sesuai peran | |
| 3 | Buat pesanan pelanggan | Nomor pesanan terbentuk | |
| 4 | Tandai lunas di kasir | Status `lunas`, poin bertambah | |
| 5 | KDS barista | Pesanan muncul tanpa refresh | |
| 6 | Laporan cetak A4 | Angka sama dengan dashboard | |
| 7 | `php artisan schedule:list` | `meja:bersihkan` tiap menit | |
| 8 | Webhook Midtrans | Notifikasi URL diisi `{APP_URL}/api/midtrans/callback` | |

## 3. Kontinjensi Offline (Server Lokal LAN)

Dipakai bila internet ruang ujian terputus atau staging tidak dapat diakses.
Seluruh fitur tetap berjalan kecuali pembayaran Midtrans (butuh internet).

### Persiapan di laptop (sekali saja)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm run build
```

### Menjalankan

1. Hubungkan laptop dan HP penguji ke **WiFi/hotspot yang sama** (tanpa internet).
2. Klik dua kali `docs/08-deployment/serve-lan.bat`, atau jalankan manual:

   ```bash
   php artisan serve --host=0.0.0.0 --port=8000
   ```

3. Catat IP lokal laptop (`ipconfig` → *IPv4 Address*, contoh `192.168.1.100`).
4. Buka dari HP: `http://192.168.1.100:8000`.
5. Pada `.env`, set `APP_URL=http://192.168.1.100:8000` lalu
   `php artisan config:clear` agar tautan QR dan webhook memakai alamat LAN.

### Catatan penting saat mode LAN

| Hal | Tindakan |
| :--- | :--- |
| **Geofence** | Browser menolak GPS pada `http://` non-localhost. Matikan geofence di **Store Settings → Geofence** sebelum demo, atau gunakan `http://localhost` di laptop. |
| **Midtrans** | Pembayaran QRIS/VA tidak bisa dipakai. Demo memakai **Tunai**; sampaikan alasan teknisnya. |
| **Firewall Windows** | Izinkan `php.exe` pada *Private network* saat dialog muncul. |
| **Aset Vite** | Pastikan `public/build` sudah ada hasil `npm run build`; tanpa internet tidak bisa `npm run dev`. |

## 4. Rollback

```bash
git fetch --tags
git checkout <tag-sebelumnya>
composer install --no-dev --optimize-autoloader
php artisan migrate --force   # hanya bila ada migrasi baru
php artisan optimize:clear && php artisan config:cache && php artisan route:cache
```

Migrasi tidak memiliki `down()` yang merusak data penting, tetapi **selalu
backup database** sebelum deploy:

```bash
mysqldump -u root -p coffee_shop_order > backup-$(date +%F-%H%M).sql
```

## 5. Checklist Sebelum Mengumumkan Rilis

- [ ] `APP_DEBUG=false` dan halaman error kustom tampil, bukan stack trace
- [ ] `APP_URL` benar (bukan `localhost`) agar QR & webhook valid
- [ ] Kredensial Midtrans diisi lewat Store Settings (bukan di repo)
- [ ] `.env` tidak ikut ter-commit (periksa `git status`)
- [ ] `php artisan test` lulus di mesin lokal sebelum tag
- [ ] Tag `v1.0.0-rc` ada di remote setelah `git push --tags`
- [ ] Backup database tersimpan di luar server
