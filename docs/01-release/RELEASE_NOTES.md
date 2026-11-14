# Release Notes — Tejas Coffee POS v1.0.0-rc

**Release Candidate 1.0.0** · 14 November 2026
Tag Git: `v1.0.0-rc` · Branch: `main`

## 1. Ringkasan Rilis

Rilis kandidat ini membekukan seluruh fungsionalitas aplikasi pemesanan coffee
shop: pemesanan mandiri lewat QR meja, POS kasir dengan tiga metode pembayaran
(tunai/QRIS/virtual account), KDS barista, Ops Console admin (sales, inventory,
staff, CRM, master data), dan laporan penjualan siap cetak A4.

Mulai titik ini berlaku **Code Freeze**: tidak ada fitur baru. Perubahan yang
diizinkan hanya perbaikan cacat kritis (hotfix) yang disetujui pembimbing.

## 2. Cakupan Fungsional

| Area | Fitur yang dibekukan |
| :--- | :--- |
| Autentikasi | Login sentral email **atau** nomor HP, registrasi mandiri khusus pelanggan, RBAC 4 peran |
| Pelanggan | Menu + filter kategori, kustomisasi item, keranjang, voucher, tukar poin, check-in QR meja, checkout dine-in/takeaway/delivery, status real-time, profil |
| Kasir | POS draf tiket, papan status meja (polling), tunai + kalkulator kembalian, QRIS dinamis, virtual account, struk 80 mm, tandai lunas + poin otomatis |
| Barista | KDS dengan bump/dismiss, ubah status meja |
| Admin | Overview, Sales & Revenue + **laporan cetak A4 & ekspor CSV**, Inventory & Roastery, Staff & Roster, Customer CRM & Loyalty, master data (kategori/produk/meja/promo), Store Settings + Midtrans + geofence |
| Integrasi | Webhook Midtrans (verifikasi signature SHA-512), Ops Agent API (`/api/v1/*`), command `meja:bersihkan` terjadwal |

## 3. Log Penyelesaian Bug (Bukti S4-01)

Seluruh tiket perbaikan pada siklus terakhir sudah **CLOSED** dan tervalidasi
oleh suite tes otomatis:

| # | Tiket | Perbaikan | Status |
| :---: | :--- | :--- | :---: |
| 1 | Keranjang bisa menambah item melebihi stok gabungan varian | Stok dihitung lintas varian sebelum item masuk keranjang | CLOSED |
| 2 | Redirect setelah login tidak konsisten antar peran | Redirect dibatasi sesuai peran pengguna | CLOSED |
| 3 | Daftar pesanan admin rusak di layar mobile | Tabel diubah menjadi kartu pada breakpoint kecil | CLOSED |
| 4 | Nama berkas gambar produk bertabrakan saat unggah ulang | Nama berkas dibangun ulang (unik) | CLOSED |
| 5 | Struk bisa dibuka pengguna lain lewat penebakan ID (IDOR) | Otorisasi dipusatkan pada `PesananPolicy` | CLOSED |
| 6 | Sisa isu gaya kode pada `LokasiController` | Diformat ulang dengan Laravel Pint | CLOSED |
| 7 | Celah akses halaman pelanggan dari luar area cafe | Middleware `geofence` + halaman verifikasi lokasi | CLOSED |

Catatan audit: pencarian `dd(`, `dump(`, `var_dump(`, `print_r(`, dan
`console.log(` di luar `vendor/` dan `node_modules/` menghasilkan **0 hasil**.

## 4. Hasil Verifikasi Rilis

```text
php artisan test
  Tests:    138 passed (669 assertions)
  Duration: 6.50s

./vendor/bin/pint --test
  PASS   118 files
```

Ikhtisar tes yang mencakup alur kritis:

- `MultiRoleAuthTest` — login/redirect/RBAC, struk anti-IDOR.
- `CustomerOrderFlowTest` — keranjang → checkout → status pesanan.
- `KasirTableMidtransTest` — POS, tunai/kembalian, Midtrans QRIS & VA.
- `MejaQrScanTest`, `GeofenceTest` — QR meja, verifikasi lokasi, watchdog.
- `BaristaTableStatusTest`, `PelangganTableStatusTest` — sinkronisasi status meja.
- `AdminSalesReportTest` — laporan cetak + ekspor CSV.
- `OpsAgentApiTest` — kontrak API agen operasional.
- `InventoryBahanTest` — stok biji, ambang kritis, reorder.
- `AllPagesSmokeTest`, `DashboardRenderTest`, `ErrorPageTest` — semua halaman utama merender.

## 5. Batasan yang Diketahui (Diterima untuk RC)

1. **Pembayaran non-tunai bergantung kredensial Midtrans.** Tanpa kredensial
   valid, tombol QRIS/VA menampilkan pesan konfigurasi belum lengkap. Status
   juga disinkronkan lewat polling karena pengembangan berjalan di localhost.
2. **Notifikasi WhatsApp belum diimplementasikan** (di luar lingkup, tercatat
   pada slide *Out of Scope*).
3. **Geofence memerlukan HTTPS atau localhost** karena browser hanya
   mengizinkan Geolocation API pada konteks aman.
4. **Laporan cetak dibatasi maksimal 366 hari** per unduhan agar satu permintaan
   tidak membebani server.

## 6. Langkah Setelah RC

1. Deployment staging + uji fallback LAN (`docs/08-deployment/DEPLOYMENT.md`).
2. Gladi bersih di depan pembimbing, isi berita acara.
3. Rekam video cadangan demo 3 menit.
4. Latihan 10 pertanyaan defense (`docs/07-defense/BANK_SOAL_DEFENSE.md`).
