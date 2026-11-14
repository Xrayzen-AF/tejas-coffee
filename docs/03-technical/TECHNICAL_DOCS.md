# Dokumentasi Teknis — Tejas Coffee POS

**Versi aplikasi:** 1.0.0-rc · **Laravel 12** · **PHP 8.2** · **MySQL 8**

## 1. Arsitektur

Aplikasi monolitik Laravel dengan pola **MVC** dan lapisan service untuk logika
yang dipakai lebih dari satu controller.

```text
Browser (Blade + Tailwind 4 + Vite 7)
        │  HTTP / form + fetch JSON
        ▼
routes/web.php ── middleware: auth, role:{peran}, geofence, throttle
routes/api.php ── /api/* (publik, session, webhook Midtrans, Ops Agent)
        ▼
Controller (per peran)  ──►  Service (Midtrans, MejaQr, Agents)
        │                        │
        │                        ├─ SalesAgent / StaffingAgent / InventoryAgent
        ▼                        ▼
Eloquent Model  ◄──────────►  MySQL (14 tabel)
```

| Lapisan | Lokasi | Tanggung jawab |
| :--- | :--- | :--- |
| Routing | `routes/web.php`, `routes/api.php` | Peta URL, prefix per peran, penamaan rute |
| Middleware | `app/Http/Middleware/` | `EnsureUserHasRole`, `EnsureWithinCafeGeofence`, `PreventBackAfterLogout` |
| Controller | `app/Http/Controllers/` | Validasi request, orkestrasi, render view/JSON |
| Service | `app/Services/` | `MidtransService`, `MejaQrService`, `Agents/*` |
| Policy | `app/Policies/PesananPolicy.php` | Otorisasi kepemilikan struk/pesanan (anti-IDOR) |
| Model | `app/Models/` | Relasi Eloquent, konstanta state machine, transaksi DB |
| View | `resources/views/` | Blade per peran + layout cetak A4 dan struk 80 mm |
| Command | `app/Console/Commands/BersihkanSesiMeja.php` | Penutupan sesi meja kedaluwarsa (scheduler tiap menit) |

## 2. Entity Relationship Diagram (3NF)

```mermaid
erDiagram
    PENGGUNA ||--o{ PESANAN : "membuat"
    PELANGGAN ||--o{ PESANAN : "memesan"
    PELANGGAN ||--o{ RESERVASI_MEJA : "check-in"
    KATEGORI ||--o{ PRODUK : "mengelompokkan"
    MEJA ||--o{ PESANAN : "dipakai"
    MEJA ||--o{ RESERVASI_MEJA : "sesi"
    PROMO ||--o{ PESANAN : "memotong"
    PESANAN ||--|{ DETAIL_PESANAN : "berisi"
    PRODUK ||--o{ DETAIL_PESANAN : "dijual"
    PESANAN ||--o| TRANSAKSI : "dibayar"
    METODE_PEMBAYARAN ||--o{ TRANSAKSI : "jenis"
    STOK_BIJI ||--o{ LOG_REORDER : "dispatch"

    PENGGUNA {
        bigint id_pengguna PK
        string nama_lengkap
        string email UK
        string nomor_telepon UK
        string password
        enum peran "admin|kasir|barista|pelanggan"
        bool aktif
    }
    PELANGGAN {
        bigint id_pelanggan PK
        string nama_pelanggan
        string nomor_telepon UK
        int poin_loyalitas
    }
    KATEGORI {
        bigint id_kategori PK
        string nama_kategori
    }
    PRODUK {
        bigint id_produk PK
        bigint id_kategori FK
        string kode_produk UK
        string nama_produk
        decimal harga
        int stok
        bool status_tersedia
        string url_gambar
    }
    MEJA {
        bigint id_meja PK
        string nomor_meja UK
        int kapasitas
        enum status "kosong|terisi|dipesan"
        string qr_token UK
    }
    RESERVASI_MEJA {
        bigint id_reservasi PK
        bigint id_meja FK
        bigint id_pelanggan FK
        string kode_booking UK
        enum status "aktif|selesai|kedaluwarsa|dibatalkan"
        datetime dicek_in_pada
        datetime berakhir_pada
    }
    PROMO {
        bigint id_promo PK
        string kode_promo UK
        enum tipe_diskon "persen|nominal"
        decimal nilai_diskon
        datetime berlaku_mulai
        datetime berlaku_sampai
        bool status_aktif
    }
    PESANAN {
        bigint id_pesanan PK
        string nomor_pesanan UK
        bigint id_pengguna FK
        bigint id_pelanggan FK
        bigint id_meja FK
        bigint id_promo FK
        enum tipe_pesanan "dine-in|takeaway|delivery"
        enum status_pesanan "baru|diproses|selesai|dibatalkan"
        decimal total_harga
        decimal diskon
        decimal biaya_layanan
        int poin_dipakai
        text catatan
        datetime waktu_pesan
    }
    DETAIL_PESANAN {
        bigint id_detail PK
        bigint id_pesanan FK
        bigint id_produk FK
        int jumlah
        decimal harga_satuan
        decimal subtotal
        string catatan_item
    }
    METODE_PEMBAYARAN {
        bigint id_metode PK
        string kode_metode UK
        string nama_metode
        bool ada_kembalian
    }
    TRANSAKSI {
        bigint id_transaksi PK
        bigint id_pesanan FK_UK "1:1"
        bigint id_metode FK
        decimal subtotal
        decimal total_bayar
        decimal kembalian
        string bank_atau_ewallet
        string nomor_referensi
        enum status_pembayaran "menunggu|lunas|gagal"
        datetime waktu_pembayaran
    }
    STOK_BIJI {
        bigint id_stok_biji PK
        string nama_biji
        decimal sisa_kg
        decimal threshold_kg
        decimal safety_cap_kg
        decimal laju_konsumsi_kg_per_jam
    }
    LOG_REORDER {
        bigint id_log_reorder PK
        bigint id_stok_biji FK
        string destination
        decimal order_quantity_kg
        enum status "terkirim|gagal"
        datetime waktu_dispatch
    }
```

**Bukti normalisasi.**

- **1NF** — setiap tabel memiliki primary key tunggal; tidak ada kolom berulang.
- **2NF** — kolom non-kunci bergantung penuh pada PK. Harga item disimpan sebagai
  *snapshot* di `detail_pesanan.harga_satuan` karena memang atribut transaksi
  (bukan duplikasi data produk).
- **3NF** — tidak ada ketergantungan transitif: nama kategori tidak disimpan di
  `produk` (cukup `id_kategori`), data pelanggan dipisah dari `pengguna`, dan
  detail pembayaran dipisah ke `transaksi` (relasi 1:1 dengan `pesanan`).

## 3. State Machine

**Pesanan** (`Pesanan::TRANSISI_STATUS`):

```
baru ──► diproses ──► selesai        (selesai mensyaratkan transaksi lunas)
  │          │
  └──────────┴──► dibatalkan          (stok & poin dikembalikan)
```

Transisi mundur (`diproses → baru`) dan lompatan (`baru → selesai`) ditolak
dengan HTTP 422. Barista tidak boleh melompat dari `baru` ke `selesai`.

**Transaksi:** `menunggu → lunas` (kasir/Midtrans) atau `menunggu → gagal`
(pesanan dibatalkan).

**Meja:** `kosong ⇄ terisi ⇄ dipesan`, disinkronkan `Pesanan::sinkronMeja()`
setelah setiap perubahan pesanan/reservasi.

**Reservasi meja:** `aktif → selesai | kedaluwarsa | dibatalkan`.
Durasi sesi 90 menit, diperpanjang selama ada pesanan aktif, batas keras 180 menit,
tenggang 15 menit setelah pesanan terakhir selesai.

## 4. Alur Transaksi Kritis

### 4.1 Membuat pesanan (`OrderController@store`)

1. Validasi input (`items[]`, `tipe_pesanan`, `id_meja` wajib untuk dine-in).
2. **Anti-spoofing:** bila pembuatnya pelanggan, `id_pengguna`/`id_pelanggan`
   dari client diabaikan; pelanggan hanya bisa memesan atas namanya sendiri.
3. Buka `DB::transaction`, lalu `lockForUpdate()` pada produk yang dibutuhkan
   untuk mencegah *race condition* stok.
4. Tolak bila stok tidak cukup; hitung subtotal dengan `bcmath` (bukan float).
5. Harga kiriman client tidak boleh di bawah harga dasar produk (anti order Rp1).
6. Terapkan voucher (persen/nominal) dan tukar poin (1 poin = Rp 10), tambahkan
   biaya layanan dari Store Settings.
7. Kurangi stok secara atomik, buat baris `transaksi` berstatus `menunggu`.
8. Sinkronkan status meja; commit.

### 4.2 Pembayaran Midtrans

1. Kasir memilih QRIS/VA → `MidtransService` membuat transaksi Core API.
2. Pelanggan membayar; Midtrans mengirim webhook ke `/api/midtrans/callback`.
3. Controller memverifikasi `signature_key`
   (`sha512(order_id + status_code + gross_amount + server_key)`) dengan
   `hash_equals`.
4. `applyStatus()` memperbarui transaksi/pesanan dan menambah poin pelanggan.
5. Karena pengembangan di localhost, status juga dipolling dari halaman kasir
   dan halaman status pesanan.

### 4.3 Laporan penjualan cetak (`SalesController@cetak`)

- Basis data: `transaksi` berstatus `lunas` dalam rentang `waktu_pembayaran`.
- Omzet = `SUM(transaksi.subtotal)` (nilai tagihan) agar kembalian tunai tidak
  dihitung pendapatan; `total_bayar` dan `kembalian` dilaporkan terpisah.
- Filter `dari`/`sampai` divalidasi ketat (`Y-m-d` + `checkdate`), tanggal
  tertukar dibalik otomatis, rentang dibatasi 366 hari.
- Ekspor CSV memakai BOM UTF-8 dan pemisah `;` agar rapi di Excel locale Indonesia.

## 5. Keamanan

| Risiko | Mitigasi di kode |
| :--- | :--- |
| SQL Injection | Eloquent/Query Builder dengan parameter binding; tidak ada query string mentah dari input |
| XSS | Blade auto-escape (`{{ }}`); atribut dinamis memakai `@json` |
| CSRF | Middleware `web` aktif untuk semua form; hanya webhook API yang dikecualikan |
| IDOR struk/pesanan | `PesananPolicy` + cek peran di controller (`showByNumber`, `StrukController`) |
| Escalation of role | Registrasi publik hanya membuat peran `pelanggan`; CRUD pegawai dibatasi admin |
| Brute force login | `throttle:10,1` pada login/registrasi; verifikasi kode booking `throttle:6,1` |
| Password | Hash Bcrypt (`BCRYPT_ROUNDS=12`) |
| Upload gambar produk | Whitelist tipe gambar, batas ukuran, nama berkas dibuat ulang |
| Webhook palsu | Verifikasi signature SHA-512 + `hash_equals` |
| Kebocoran kredensial | Kunci Midtrans disimpan di tabel `midtrans_settings` dan ditampilkan tersamar (`••••`) |
| Akses dari luar area | Middleware `geofence` (Haversine) + verifikasi GPS sisi server |
| Sesi meja menganggur | Command `meja:bersihkan` tiap menit + cleanup malas saat polling |

## 6. Konfigurasi Environment

| Variabel | Wajib | Keterangan |
| :--- | :---: | :--- |
| `APP_NAME`, `APP_URL` | ✅ | Dipakai pada tautan QR, webhook, dan kop laporan |
| `APP_ENV`, `APP_DEBUG` | ✅ | `production` / `false` saat rilis |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | ✅ | MySQL `coffee_shop_order` |
| `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` | ✅ | Default `database` |
| `MIDTRANS_SERVER_KEY`, `MIDTRANS_CLIENT_KEY`, `MIDTRANS_IS_PRODUCTION` | ⬜ | Opsional bila diisi lewat Store Settings |

Store Settings tersimpan di `storage/app/store-settings.json` (tanpa migrasi):
profil toko, jam buka, biaya layanan, poin per Rp 10.000, ambang stok, dan
geofence (lat/lng/radius/aktif).

## 7. Dependensi

| Paket | Versi | Fungsi |
| :--- | :--- | :--- |
| `laravel/framework` | ^12.0 | Kerangka aplikasi |
| `midtrans/midtrans-php` | ^2.6 | QRIS & virtual account |
| `bacon/bacon-qr-code` | ^3.0 | Generator QR meja |
| `laravel/tinker` | ^2.10 | REPL saat pengembangan |
| `laravel/pint` (dev) | ^1.24 | Perapian gaya kode |
| `phpunit/phpunit` (dev) | ^11.5 | Suite tes |
| `tailwindcss` + `@tailwindcss/vite` | ^4.0 | Styling |
| `vite` | ^7.0 | Bundling aset |
| `concurrently` (dev) | ^9.0 | `composer dev` (server + queue + log + vite) |

## 8. Operasional

```bash
php artisan migrate --seed      # siapkan skema + data demo
php artisan schedule:work       # jalankan meja:bersihkan tiap menit
php artisan test                # 138 tes, SQLite in-memory
./vendor/bin/pint               # rapikan gaya kode
npm run build                   # aset produksi
```

Log aplikasi: `storage/logs/laravel.log`. Untuk produksi, `LOG_LEVEL=warning`
dan pasang rotasi log agar tidak memenuhi disk.

## 9. Keputusan Desain Penting

1. **Satu login sentral, redirect per peran** — menghindari empat halaman login
   terpisah dan menyederhanakan pengujian RBAC.
2. **Snapshot harga pada detail pesanan** — perubahan harga menu tidak mengubah
   nilai transaksi historis, sehingga laporan lama tetap akurat.
3. **Omzet berbasis nilai tagihan, bukan uang diterima** — uang tunai berlebih
   dan kembalian tidak boleh menggelembungkan pendapatan.
4. **Stok dikurangi di dalam transaksi DB dengan row lock** — mencegah stok minus
   saat dua kasir menjual item yang sama bersamaan.
5. **QR meja memakai token acak 40 karakter** — URL meja tidak dapat ditebak atau
   dipakai ulang dari meja lain.
6. **Geofence di server, bukan hanya di browser** — koordinat diverifikasi ulang
   di backend sehingga tidak bisa dilewati dengan mengubah JavaScript.
