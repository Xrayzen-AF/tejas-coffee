# ☕ Tejas Coffee POS — Sistem Pemesanan Coffee Shop

[![Release](https://img.shields.io/badge/Release-v1.0.0--rc-0D9488?style=for-the-badge)](https://github.com)
[![Build Status](https://img.shields.io/badge/Build-Passing-10B981?style=for-the-badge)](https://github.com)
[![Security Audit](https://img.shields.io/badge/Security-Protected-3B82F6?style=for-the-badge)](https://github.com)
[![License](https://img.shields.io/badge/License-MIT-slate?style=for-the-badge)](LICENSE)

> Proyek Uji Kompetensi Keahlian (UKK) Rekayasa Perangkat Lunak / PPLG — Tahun Ajaran 2026/2027

> **Tema:** Local Digital Solution &bull; **Mitra:** Kedai Tejas Coffee (studi kasus operasional coffee shop)

---

## 📌 1. Latar Belakang Masalah & Solusi

Pencatatan pesanan di coffee shop masih sering dilakukan di kertas nota, lalu diketik ulang ke mesin kasir. Antrean memanjang pada jam sibuk, stok menu tidak sinkron dengan ketersediaan bahan, status meja tidak terpantau, dan rekap penjualan harian disalin manual ke Excel setiap tutup toko.

**Tejas Coffee POS** hadir sebagai solusi digital terintegrasi yang menyediakan:

1. **QR Table Ordering:** Pelanggan memindai QR meja, memesan sendiri, dan meja otomatis tercatat terisi selama 90 menit — dengan sesi yang diperpanjang selama masih ada pesanan aktif.

2. **Unified POS & Payment:** Kasir menagih dalam satu layar (tunai dengan kalkulator kembalian, QRIS dinamis, virtual account), stok dipotong atomik di dalam transaksi basis data sehingga tidak pernah minus.

3. **Ops Console & Printable Report:** KDS barista real-time, inventori biji kopi dengan ambang kritis, CRM & poin loyalitas, serta laporan penjualan A4 berfilter periode yang dapat dicetak atau diekspor ke CSV.

---

## 🛠️ 2. Tech Stack & Architecture

* **Frontend:** Blade Templating, Tailwind CSS 4, Vite 7, Lucide Icons, JavaScript Vanilla.
* **Backend:** PHP 8.2 (Laravel 12 MVC Engine) dengan lapisan Service (`MidtransService`, `MejaQrService`, `Agents/*`).
* **Database:** MySQL 8 / MariaDB 10.6 (InnoDB Engine, 14 tabel relasional, 3rd Normal Form — 3NF).
* **Security:** Bcrypt Hashing, Eloquent Parameter Binding (anti SQL Injection), CSRF Shield, Role Middleware, `PesananPolicy` (anti-IDOR), verifikasi signature webhook SHA-512, serta geofence yang divalidasi di sisi server.

---

## 🚀 3. Panduan Instalasi Lokal (Quick Local Setup)

Ikuti langkah berikut untuk menjalankan aplikasi di lingkungan pengembang lokal:

### Prasyarat Sistem:

* PHP $\ge$ 8.2 & Composer
* MySQL Server (Laragon / XAMPP)
* Node.js 20.x & npm (untuk membangun aset)
* Git CLI

### Langkah Instalasi:

```bash
# 1. Klon repositori ini
git clone https://github.com/<akun-anda>/tejas-coffee-pos.git
cd tejas-coffee-pos

# 2. Pasang dependensi pustaka
composer install

# 3. Salin berkas environment
cp .env.example .env

# 4. Generate Application Key
php artisan key:generate

# 5. Konfigurasi basis data pada berkas .env
# DB_DATABASE=coffee_shop_order
# DB_USERNAME=root
# DB_PASSWORD=

# 6. Jalankan migrasi dan data seeder dummy
php artisan migrate:fresh --seed

# 7. Hubungkan storage symlink untuk berkas gambar menu
php artisan storage:link

# 8. Bangun aset frontend
npm install && npm run build

# 9. Jalankan server lokal
php artisan serve
```

Akses aplikasi melalui browser Anda pada tautan: http://localhost:8000.

## 🔑 4. Akun Pengujian Default (Credentials)

Gunakan kredensial berikut untuk menguji hak akses multi-role. Semua akun memakai password `password` dan dapat login memakai email **atau** nomor HP.

| **Role Pengguna** | **Alamat Email** | **Nomor HP** | **Password** | **Hak Akses Utama** |
| --- | --- | --- | --- | --- |
| **Admin** | admin@kopi.dev | 081100000001 | password | Sales & Revenue, Inventori, Staff, CRM, Master Data, Store Settings, cetak laporan |
| **Kasir** | kasir@kopi.dev | 081200000001 | password | POS tiket, status meja, tunai/QRIS/VA, struk 80 mm, tandai lunas |
| **Barista** | barista@kopi.dev | 081300000001 | password | KDS (bump/dismiss), ubah status meja |
| **Pelanggan** | pelanggan@kopi.dev | 081234567890 | password | Pesan menu via QR meja, voucher, poin, checkout, riwayat & struk |

## 👥 5. Anggota Tim Pengembang (Kelompok 04)

* **Siswa 1 — Frontend Specialist & UI/UX Designer** • _NISN: (sesuaikan)_
* **Siswa 2 — Backend Architect & Database Administrator** • _NISN: (sesuaikan)_

_Pembimbing UKK:_ **(sesuaikan nama pembimbing, S.Kom.)**
**(sesuaikan nama sekolah) — 2026**

---

## 📋 6. Fitur per Peran

### Admin (`/admin/dashboard`)

- Ringkasan toko: jumlah pengguna per peran, penjualan, dan stok kritis.
- CRUD akun pegawai (khusus kasir & barista; admin tidak dapat menghapus dirinya sendiri).
- **Sales & Revenue:** omzet 7 hari, breakdown metode bayar, margin produk, serta laporan penjualan siap cetak A4 + ekspor CSV.
- **Inventory & Roastery:** stok biji kopi, ambang kritis, dispatch *reorder* ke roastery.
- **Staff & Roster**, **Customer CRM & Loyalty**, dan **Master data** (kategori, produk, meja + cetak lembar QR, promo).
- **Store Settings:** profil toko, biaya layanan, poin per Rp 10.000, metode pembayaran, kredensial Midtrans, geofence cafe.
- Kelola status pesanan dan pembatalan.

### Kasir (`/kasir/dashboard`)

- POS: tambah/ubah item pada draf tiket (session), lalu **Charge** menjadi pesanan.
- Papan status meja real-time (dipolling).
- Pembayaran tunai (kalkulator kembalian), QRIS dinamis, dan virtual account.
- Cetak struk 80 mm; tandai lunas (poin pelanggan bertambah otomatis).

### Barista (`/barista/dashboard`)

- KDS: daftar pesanan aktif, **bump** (sematkan ke atas) dan **dismiss** (sembunyikan).
- Ubah status meja (kosong / terisi / dipesan).

### Pelanggan (`/pelanggan/dashboard`)

- Registrasi mandiri (hanya peran pelanggan), login via email atau nomor HP.
- Menu + filter kategori, kustomisasi item, keranjang, voucher, dan tukar poin.
- Check-in meja lewat QR atau kode booking; registrasi, login, dan scan QR juga tersedia untuk tamu tanpa akun.
- Checkout (dine-in / takeaway / delivery), status pesanan real-time, riwayat, dan profil.
- Verifikasi lokasi (geofence) sebelum membuka halaman pelanggan.

## 🗄️ 7. Struktur Database

| Tabel | Keterangan |
|---|---|
| `pengguna` | Akun login semua peran (`peran`: admin, kasir, barista, pelanggan) |
| `pelanggan` | Data member + `poin_loyalitas` |
| `kategori` | Kategori menu |
| `produk` | Menu: kode unik, harga, stok, status tersedia, gambar |
| `meja` | Nomor meja, kapasitas, `status`, `qr_token` acak untuk QR fisik |
| `reservasi_meja` | Sesi meja: `kode_booking`, status, waktu berakhir (90 menit) |
| `promo` | Voucher persen/nominal dengan masa berlaku |
| `pesanan` | Header pesanan: tipe, status, total, diskon, biaya layanan |
| `detail_pesanan` | Rincian item (snapshot harga satuan & subtotal) |
| `metode_pembayaran` | Tunai / QRIS / Transfer, penanda kembalian |
| `transaksi` | Pembayaran 1:1 dengan pesanan, status & referensi |
| `stok_biji`, `log_reorder` | Inventori biji kopi + riwayat dispatch reorder |
| `midtrans_settings` | Kredensial aktif Midtrans (diatur dari halaman Settings) |

Alur status pesanan: `baru → diproses → selesai`, atau `dibatalkan`.
Status pembayaran: `menunggu → lunas` / `gagal`.

## 🔌 8. Endpoint API

Semua endpoint berada di prefix `/api`. Endpoint pesanan wajib login (session) dan harga selalu dihitung ulang di server. Spesifikasi lengkap: [`docs/04-api/openapi.json`](docs/04-api/openapi.json).

| Method | Endpoint | Akses | Deskripsi |
|---|---|---|---|
| `GET` | `/categories` | publik | Daftar kategori |
| `GET` | `/products?category=&search=` | publik | Daftar menu + filter |
| `GET` | `/products/{kode_produk}` | publik | Detail menu |
| `POST` | `/orders` | login | Buat pesanan |
| `GET` | `/orders/{nomor_pesanan}` | login | Detail pesanan |
| `GET` | `/orders?status=` | kasir/barista | Daftar pesanan |
| `PATCH` | `/orders/{id}/status` | kasir/barista | Ubah status |
| `PATCH` | `/orders/{id}/cancel` | kasir/barista | Batalkan |
| `POST` | `/midtrans/callback` | Midtrans | Webhook pembayaran |
| `GET` | `/v1/ops/financial-summary` | — | Ringkasan keuangan (Ops Console) |
| `GET` | `/v1/ops/traffic-telemetry` | — | Telemetri trafik |
| `GET` | `/v1/inventory/alerts` | — | Biji kopi di bawah ambang |
| `POST` | `/v1/inventory/reorder` | — | Dispatch reorder ke roastery |

## 💳 9. Pembayaran, Penjadwalan & Pengujian

**Midtrans.** Kredensial dapat diatur di **Admin → Store Settings → Midtrans** (tersimpan di tabel `midtrans_settings`) atau lewat `.env`:

```env
MIDTRANS_SERVER_KEY=SB-Mid-server-xxxxxxxx
MIDTRANS_CLIENT_KEY=SB-Mid-client-xxxxxxxx
MIDTRANS_IS_PRODUCTION=false
```

Isi **Payment Notification URL** di dashboard Midtrans dengan `{APP_URL}/api/midtrans/callback`. Webhook memverifikasi signature SHA-512, memperbarui status transaksi/pesanan, dan menambah poin pelanggan. Karena pengembangan berjalan di localhost, status juga disinkronkan lewat polling.

**Penjadwalan.** Sesi meja kedaluwarsa ditutup oleh `php artisan meja:bersihkan` dan dijadwalkan tiap menit (`routes/console.php`). Saat produksi jalankan scheduler lewat cron:

```
* * * * * cd /path/ke-proyek && php artisan schedule:run >> /dev/null 2>&1
```

**Pengujian.** Test berjalan di SQLite in-memory (lihat `phpunit.xml`), sehingga tidak menyentuh database MySQL aplikasi:

```bash
php artisan test          # 138 tes, 669 assertion
./vendor/bin/pint         # rapikan gaya kode
```

Cakupan: autentikasi multi-role, alur pesanan pelanggan, QR meja & geofence, status meja barista/pelanggan, POS kasir + Midtrans, inventori, laporan cetak, dan endpoint Ops Agent.

## 📂 10. Struktur Direktori Penting

```
app/Http/Controllers/Admin/     Ops Console (sales, inventory, staff, CRM, master data)
app/Http/Controllers/Auth/      Login & registrasi terpusat (email/nomor HP)
app/Http/Controllers/           Alur pelanggan, kasir, barista, QR meja, struk, Midtrans
app/Services/                   MidtransService, MejaQrService, Agents (sales/staffing/inventory)
app/Console/Commands/           meja:bersihkan (scheduler)
resources/views/                Blade per peran (admin, kasir, barista, pelanggan)
tests/Feature/                  Test integrasi lintas peran
docs/                           Dokumentasi Sprint 4 (manual, teknis, API, slide, deployment)
screenshots/                    Tangkapan layar dokumentasi UI
```

## 📚 11. Dokumentasi Sprint 4

| Artefak | Berkas |
|---|---|
| Log bug fixing & Git tag (S4-01) | [`docs/01-release/`](docs/01-release/) |
| Deployment & kontingensi offline (S4-02) | [`docs/08-deployment/DEPLOYMENT.md`](docs/08-deployment/DEPLOYMENT.md) |
| User Manual (S4-03) | [`docs/03-manual/USER_MANUAL.docx`](docs/03-manual/USER_MANUAL.docx) |
| Dokumentasi teknis (S4-03) | [`docs/03-technical/TECHNICAL_DOCS.md`](docs/03-technical/TECHNICAL_DOCS.md) |
| Spesifikasi API (S4-05) | [`docs/04-api/openapi.json`](docs/04-api/openapi.json) |
| Slide presentasi (S4-06) | [`docs/05-presentation/pitch-deck.html`](docs/05-presentation/pitch-deck.html) |
| Naskah demo & berita acara (S4-07) | [`docs/06-demo/`](docs/06-demo/) |
| Bank soal defense (S4-08) | [`docs/07-defense/BANK_SOAL_DEFENSE.md`](docs/07-defense/BANK_SOAL_DEFENSE.md) |

Indeks lengkap: [`docs/README.md`](docs/README.md).

## 📄 Lisensi

Dirilis dengan lisensi MIT — lihat berkas [`LICENSE`](LICENSE).
