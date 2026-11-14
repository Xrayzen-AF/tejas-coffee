# Buku Panduan Pengguna — Tejas Coffee POS

**Versi 1.0.0-rc** · Untuk staf cafe dan pelanggan

Aplikasi dibuka lewat browser (Chrome/Edge/Firefox terbaru). Alamat default saat
pengembangan: `http://localhost:8000`. Semua peran memakai **satu halaman login**
yang sama; sistem mengarahkan ke dashboard sesuai peran.

> Tangkapan layar pada panduan ini merujuk berkas di folder `screenshots/`.
> Bila berkas belum tersedia, ulangi langkah pada aplikasi lalu ambil tangkapan
> layar baru dengan nama berkas yang sama.

---

## 1. Masuk dan Keluar

1. Buka alamat aplikasi, halaman login muncul otomatis.
2. Isi **Email atau Nomor HP** dan **Password**.
3. Klik **Masuk**. Anda diarahkan ke dashboard sesuai peran:

   | Peran | Halaman awal |
   | :--- | :--- |
   | Admin | `/admin/dashboard` |
   | Kasir | `/kasir/dashboard` |
   | Barista | `/barista/dashboard` |
   | Pelanggan | `/pelanggan/dashboard` |

4. Untuk keluar, klik **Keluar** pada menu profil. Sesi ditutup dan Anda kembali
   ke halaman login; tombol *back* browser tidak membuka halaman dashboard lagi.

Akun demo (password semuanya `password`):

| Peran | Email | Nomor HP |
| :--- | :--- | :--- |
| Admin | `admin@kopi.dev` | `081100000001` |
| Kasir | `kasir@kopi.dev` | `081200000001` |
| Barista | `barista@kopi.dev` | `081300000001` |
| Pelanggan | `pelanggan@kopi.dev` | `081234567890` |

**Bila login gagal:** pastikan akun aktif (admin bisa menonaktifkan pegawai di
menu *Staff & Roster*). Setelah 10 percobaan gagal dalam satu menit, login
diblokir sementara.

---

## 2. Panduan Pelanggan

### 2.1 Registrasi

1. Dari halaman login klik **Daftar**.
2. Isi nama lengkap, email, **nomor HP** (wajib), dan password.
3. Klik **Daftar**. Anda langsung masuk sebagai pelanggan.

Nomor HP wajib karena dipakai sebagai identitas member: poin loyalitas dan
struk lama tertaut ke nomor tersebut.

### 2.2 Verifikasi Lokasi (Geofence)

Saat fitur geofence aktif, aplikasi hanya bisa dipakai di dalam radius cafe.

1. Setelah login, halaman **Verifikasi Lokasi** tampil.
2. Klik **Izinkan Lokasi** pada dialog browser, lalu tekan **Verifikasi**.
3. Bila posisi di luar radius, halaman menampilkan jarak Anda ke cafe. Datang ke
   area cafe lalu klik **Cek Ulang**.
4. Bila GPS tidak tersedia, gunakan tombol **Check-in dengan Kode Booking** di
   halaman scan meja.

> Browser hanya mengizinkan GPS pada `https://` atau `http://localhost`.
> Bila memakai IP LAN (`http://192.168.x.x`), minta admin mematikan geofence
> sementara untuk keperluan demo.

### 2.3 Check-in Meja lewat QR

1. Pindai QR fisik di meja memakai kamera HP → halaman status meja terbuka.
2. Atau, di dalam aplikasi buka **Menu → Pindai QR Meja**.
3. Untuk tamu tanpa akun: masukkan **kode booking** yang diberikan staf.
4. Setelah check-in, meja tercatat atas nama Anda selama **90 menit**, otomatis
   diperpanjang selama ada pesanan aktif (maksimal 180 menit), lalu dilepas
   15 menit setelah pesanan terakhir selesai.

### 2.4 Memesan

1. Buka **Menu**, pilih kategori atau gunakan kotak pencarian.
2. Klik sebuah menu → halaman **Kustomisasi**: pilih ukuran/pilihan tambahan,
   isi catatan (mis. *less sugar*), lalu **Tambah ke Keranjang**.
3. Buka **Keranjang**:
   - ubah jumlah dengan tombol **+ / −**;
   - masukkan **kode voucher** (contoh: `WELCOME10`) lalu **Terapkan**;
   - centang **Tukar Bean Points** bila ingin memakai poin (1 poin = Rp 10);
   - pilih tipe pesanan: **Dine-in / Takeaway / Delivery**.
4. Klik **Checkout**, periksa ringkasan, lalu **Buat Pesanan**. Nomor pesanan
   muncul (format `PSN-YYYYMMDD-XXXXXX`).

### 2.5 Membayar dan Melacak Pesanan

- **Tunai** — bayar di kasir; kasir menandai lunas dan mencetak struk.
- **QRIS / Virtual Account** — pilih metode di halaman status pesanan, lalu pindai
  QR atau salin nomor VA. Status diperbarui otomatis (polling) dan oleh webhook.
- Halaman **status pesanan** menampilkan progres `baru → diproses → selesai`.
- Pesanan yang belum dibayar dapat dibatalkan lewat tombol **Batalkan Pesanan**;
  stok dan poin otomatis dikembalikan.
- **Riwayat** berisi daftar pesanan lama; klik salah satu untuk membuka struk.

---

## 3. Panduan Kasir

Halaman: **`/kasir/dashboard`** (`screenshots/theme-kasir.png`,
`screenshots/theme-kasir-ticket.png`).

1. **Pilih meja** pada papan status meja (kosong / terisi / dipesan). Papan
   diperbarui otomatis tiap beberapa detik.
2. **Tambahkan item** dengan mengklik kartu menu; item masuk ke *draf tiket* di
   panel kanan. Ubah jumlah atau hapus item bila perlu.
3. Klik **Charge** untuk mengubah draf menjadi pesanan. Pesanan langsung
   berstatus `baru` dan tampil di KDS barista.
4. **Pembayaran tunai** — masukkan uang diterima pada kalkulator; kembalian
   dihitung otomatis (`screenshots/kalkulator-tunai-final.png`).
5. **Pembayaran non-tunai** — tekan **QRIS** atau **Virtual Account**; QR/nomor VA
   muncul di layar kasir (`screenshots/midtrans-qris.png`,
   `screenshots/midtrans-va.png`).
6. Klik **Tandai Lunas** setelah pembayaran diterima. Poin pelanggan bertambah
   otomatis (1 poin per Rp 10.000).
7. Klik **Cetak Struk** untuk struk 80 mm (`screenshots/midtrans-lunas.png`).

**Aturan penting:** pesanan hanya bisa ditandai `selesai` bila transaksinya sudah
`lunas`; pembatalan hanya boleh sebelum pesanan selesai dan belum dibayar.

---

## 4. Panduan Barista

Halaman: **`/barista/dashboard`** (`screenshots/theme-barista.png`,
`screenshots/theme-barista-wide.png`).

1. KDS menampilkan pesanan aktif beserta item dan catatan pelanggan.
2. Klik **Bump** untuk menyematkan tiket ke atas papan (prioritas).
3. Klik **Dismiss** untuk menyembunyikan tiket yang sudah ditangani dari antrean.
4. Ubah **status meja** (kosong / terisi / dipesan) langsung dari panel meja.

Pesanan baru muncul tanpa perlu refresh (halaman melakukan polling).

---

## 5. Panduan Admin

Halaman: **`/admin/dashboard`** (`screenshots/admin-dashboard.png`,
`screenshots/theme-admin-dashboard.png`).

| Menu | Kegunaan |
| :--- | :--- |
| **Overview** | Ringkasan pengguna per peran, penjualan, stok kritis |
| **Sales & Revenue** | Omzet 7 hari, breakdown metode bayar, margin produk, **laporan cetak** |
| **Inventory & Roastery** | Stok biji kopi, ambang kritis, dispatch reorder ke roastery |
| **Staff & Roster** | Ringkasan beban kerja staf, aktif/nonaktifkan akun |
| **Customer CRM & Loyalty** | Tambah pelanggan, sesuaikan poin, hapus data |
| **Master data** | Kategori, produk, meja (+ cetak lembar QR), promo |
| **Pesanan** | Pantau semua pesanan, ubah status, batalkan |
| **Store Settings** | Profil toko, biaya layanan, poin, metode bayar, Midtrans, geofence |

### 5.1 Mencetak Laporan Penjualan

1. Buka **Sales & Revenue** → tombol **Cetak Laporan**.
2. Pilih periode (**dari**–**sampai**, default awal bulan s/d hari ini), lalu
   filter opsional: tipe pesanan dan metode pembayaran.
3. Klik **Tampilkan**. Halaman pratinjau A4 berisi:
   - kop laporan (nama toko, alamat, periode, waktu cetak, admin pencetak);
   - ringkasan: jumlah transaksi, omzet, uang diterima, kembalian, diskon,
     biaya layanan, item terjual;
   - rekap harian, rekap per produk, rekap per metode bayar;
   - lampiran rincian transaksi dan pesanan batal (tidak dihitung omzet).
4. Klik **Cetak** → pilih printer atau **Save as PDF**.
5. Untuk data mentah di Excel, klik **Unduh CSV** pada periode yang sama.

Basis angka: hanya transaksi **lunas**. Omzet memakai nilai tagihan (`subtotal`),
bukan uang diterima, sehingga kembalian tidak terhitung sebagai pendapatan.
Rentang dibatasi 366 hari per laporan.

### 5.2 Mengatur Meja dan QR

1. **Master data → Meja** → tambah nomor meja dan kapasitas.
2. Klik **Cetak QR** untuk membuka lembar QR seluruh meja, lalu cetak dan tempel
   di meja. Token QR acak 40 karakter sehingga meja lain tidak bisa ditebak.

### 5.3 Mengatur Metode Pembayaran dan Midtrans

1. **Store Settings → Metode Pembayaran**: tambah metode baru (kode unik).
2. **Store Settings → Midtrans**: isi *Server Key*, *Client Key*, dan aktifkan
   *Production* bila sudah live. Kosongkan kolom untuk mempertahankan nilai lama.
3. Salin **Payment Notification URL** yang tampil (`{APP_URL}/api/midtrans/callback`)
   ke dashboard Midtrans.

### 5.4 Mengatur Geofence

1. **Store Settings → Geofence**.
2. Isi **latitude** dan **longitude** titik cafe (ambil dari Google Maps: klik
   kanan lokasi → salin koordinat) dan **radius** dalam meter.
3. Centang **Aktifkan geofence** lalu simpan. Matikan bila demo dilakukan lewat
   IP LAN tanpa HTTPS.

### 5.5 Mengelola Akun Pegawai

1. **Employees** → **Tambah Pegawai**.
2. Hanya peran **Kasir** dan **Barista** yang bisa dibuat dari sini; registrasi
   mandiri hanya menghasilkan peran pelanggan.
3. Admin tidak dapat menghapus akunnya sendiri.

---

## 6. Pesan Kesalahan Umum

| Pesan | Penyebab | Tindakan |
| :--- | :--- | :--- |
| *Kamar / meja tidak tersedia* | Meja sedang dipakai reservasi aktif | Pilih meja lain atau tunggu sesi berakhir |
| *Stok ... tidak mencukupi* | Stok produk habis/berkurang | Perbarui stok di **Master data → Produk** |
| *Pesanan belum dibayar. Lunasi dulu di kasir.* | Menandai `selesai` sebelum lunas | Tandai lunas di kasir lebih dulu |
| *Pesanan yang sudah dibayar tidak dapat dibatalkan* | Aturan integritas transaksi | Lakukan refund manual oleh kasir |
| *Midtrans belum dikonfigurasi* | Kredensial kosong | Isi di **Store Settings → Midtrans** |
| *Anda berada di luar radius cafe* | Geofence aktif, GPS di luar area | Datang ke area cafe atau minta admin menonaktifkan geofence |
