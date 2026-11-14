# Buku Panduan Pengguna — Tejas Coffee POS

**Versi 1.0.0-rc** · Sistem Pemesanan Coffee Shop

Aplikasi dibuka lewat browser (Chrome, Edge, atau Firefox terbaru). Alamat default
saat pengembangan: `http://localhost:8000`. Seluruh peran memakai **satu halaman
login** yang sama; sistem mengarahkan ke dashboard sesuai peran pengguna.

## Bab I: Pengenalan Aplikasi

### A. Tujuan Aplikasi Tejas Coffee POS

Tejas Coffee POS menyatukan empat pekerjaan yang sebelumnya terpisah menjadi satu
sumber data, sehingga angka di meja, kasir, dapur, dan laporan pemilik selalu sama.

- **Pelanggan** memindai QR di meja, memesan sendiri, dan memantau status pesanan.
- **Kasir** menagih dalam satu layar: tunai dengan kalkulator kembalian, QRIS, atau virtual account.
- **Barista** melihat antrean pesanan masuk secara real-time di KDS (Kitchen Display System).
- **Admin** mengelola menu, meja, promo, stok biji kopi, serta mencetak laporan penjualan A4.

### B. Persyaratan Minimum Perangkat

- **Perangkat pelanggan dan staf:** browser modern yang mendukung JavaScript (Chrome/Edge/Firefox versi 2 tahun terakhir), layar minimal 360 px untuk tampilan ponsel.
- **Fitur lokasi (geofence):** browser hanya mengizinkan GPS pada alamat `https://` atau `http://localhost`. Pada alamat IP lokal (`http://192.168.x.x`) fitur ini harus dimatikan sementara oleh admin.
- **Server lokal (pengembangan):** PHP 8.2 dengan ekstensi `pdo_mysql`, `mbstring`, `openssl`, `bcmath`, dan `fileinfo`; MySQL 8 atau MariaDB 10.6; Node.js 20 untuk membangun aset (`npm run build`). Laragon atau XAMPP sudah mencukupi.
- **Koneksi pembayaran non-tunai:** memerlukan kredensial Midtrans yang aktif. Tanpa kredensial, pembayaran tunai tetap berjalan normal.

### C. Akun Uji Coba Default

Semua akun memakai password `password` dan dapat login memakai email **atau** nomor HP.

| Peran | Email | Nomor HP | Halaman Awal |
| --- | --- | --- | --- |
| Admin | admin@kopi.dev | 081100000001 | /admin/dashboard |
| Kasir | kasir@kopi.dev | 081200000001 | /kasir/dashboard |
| Barista | barista@kopi.dev | 081300000001 | /barista/dashboard |
| Pelanggan | pelanggan@kopi.dev | 081234567890 | /pelanggan/dashboard |

## Bab II: Panduan Operasional Pelanggan (Rani)

### Langkah 1: Registrasi Akun dan Verifikasi Lokasi

1. Buka alamat aplikasi. Halaman login tampil otomatis, lalu klik **Daftar**.
2. Isi nama lengkap, email, **nomor HP** (wajib), dan password, kemudian klik **Daftar**.
3. Nomor HP menjadi identitas member: poin loyalitas dan struk lama tertaut ke nomor tersebut.
4. Bila geofence aktif, halaman **Verifikasi Lokasi** muncul. Klik **Izinkan Lokasi** pada dialog browser, lalu tekan **Verifikasi**.
5. Bila Anda berada di luar radius cafe, halaman menampilkan jarak ke cafe. Datang ke area cafe dan klik **Cek Ulang**.

### Langkah 2: Check-in Meja lewat QR atau Kode Booking

1. Pindai QR fisik di meja memakai kamera ponsel, atau buka **Menu → Pindai QR Meja** di dalam aplikasi.
2. Tamu tanpa akun dapat memakai **kode booking** yang diberikan staf pada halaman scan publik.
3. Setelah check-in, meja tercatat atas nama Anda selama **90 menit** dan otomatis diperpanjang selama masih ada pesanan aktif (batas 180 menit).
4. Meja dilepas otomatis **15 menit** setelah pesanan terakhir selesai.
5. Bila ingin mengakhiri lebih cepat, tekan **Lepas Meja**.

### Langkah 3: Memilih Menu dan Mengustomisasi Pesanan

1. Buka **Menu**, pilih kategori atau gunakan kotak pencarian.
2. Klik sebuah menu untuk membuka halaman **Kustomisasi**: pilih ukuran atau tambahan, isi catatan (misalnya *less sugar*), lalu klik **Tambah ke Keranjang**.
3. Buka **Keranjang** untuk mengubah jumlah dengan tombol **+ / −** atau menghapus item.
4. Masukkan **kode voucher** (contoh: `WELCOME10`) lalu klik **Terapkan**; diskon langsung tampil pada ringkasan.
5. Centang **Tukar Bean Points** bila ingin memakai poin (1 poin = Rp 10). Poin bertambah 1 setiap Rp 10.000 pembayaran lunas.

### Langkah 4: Checkout dan Memilih Metode Pembayaran

1. Pada halaman **Keranjang**, pilih tipe pesanan: **Dine-in**, **Takeaway**, atau **Delivery**.
2. Klik **Checkout**, periksa ringkasan biaya (subtotal, diskon, biaya layanan, total), lalu klik **Buat Pesanan**.
3. Nomor pesanan muncul dengan format `PSN-YYYYMMDD-XXXXXX`. Simpan nomor ini bila perlu.
4. **Tunai:** tunjukkan nomor pesanan ke kasir. Kasir menerima uang, menghitung kembalian, menandai lunas, dan mencetak struk.
5. **QRIS atau Virtual Account:** pilih metode pada halaman status pesanan, lalu pindai QR atau salin nomor VA. Status diperbarui otomatis; masa berlaku tagihan 15 menit.

### Langkah 5: Memantau Status, Struk, dan Riwayat Pesanan

1. Halaman **status pesanan** menampilkan progres `baru → diproses → selesai` secara real-time.
2. Pesanan yang belum dibayar dapat dibatalkan lewat tombol **Batalkan Pesanan**; stok dan poin otomatis dikembalikan.
3. Setelah lunas, buka **Riwayat** untuk melihat daftar pesanan lama.
4. Klik salah satu pesanan pada riwayat untuk membuka dan mencetak **struk**.
5. Perbarui data akun pada halaman **Profil** bila nomor HP atau nama berubah.

## Bab III: Panduan Operasional Pengelola dan Staf (Pak Bagas)

### Langkah 1: Login ke Panel Sesuai Peran

1. Buka halaman login dan masukkan email **atau** nomor HP beserta password.
2. Sistem mengarahkan ke dashboard sesuai peran: admin, kasir, atau barista. Peran pelanggan tidak dapat membuka panel staf.
3. Setelah 10 percobaan gagal dalam satu menit, login diblokir sementara untuk mencegah penebakan password.
4. Bila pegawai dinonaktifkan, login ditolak sampai admin mengaktifkannya kembali di menu **Staff & Roster**.
5. Klik **Keluar** pada menu profil untuk mengakhiri sesi; tombol *back* browser tidak membuka dashboard lagi.

### Langkah 2: Memantau Dashboard dan Antrean Pesanan

1. **Kasir** membuka `/kasir/dashboard`: papan status meja (kosong, terisi, dipesan) diperbarui otomatis.
2. **Kasir** mengklik kartu menu untuk menambah item ke draf tiket di panel kanan, lalu mengubah jumlah atau menghapus item bila perlu.
3. **Barista** membuka `/barista/dashboard`: KDS menampilkan pesanan aktif beserta catatan pelanggan.
4. **Barista** menekan **Bump** untuk menyematkan tiket prioritas dan **Dismiss** untuk menyembunyikan tiket yang sudah ditangani.
5. **Admin** membuka `/admin/dashboard` untuk melihat ringkasan pengguna per peran, penjualan, dan stok kritis.

### Langkah 3: Memeriksa dan Menandai Pembayaran

1. Buka pesanan yang akan ditagih, lalu pilih metode pembayaran.
2. **Tunai:** masukkan uang diterima pada kalkulator; kembalian dihitung otomatis.
3. **QRIS:** tekan **QRIS**, lalu tunjukkan QR di layar kasir agar dipindai pelanggan.
4. **Virtual Account:** pilih bank, lalu bacakan atau tunjukkan nomor VA kepada pelanggan.
5. Tekan **Tandai Lunas** setelah dana diterima. Poin pelanggan bertambah otomatis, dan struk 80 mm dapat dicetak.

### Langkah 4: Memproses Pesanan dan Persetujuan Status

1. Pesanan baru berstatus `baru` dan langsung tampil di KDS barista.
2. Barista mengubah status menjadi **diproses** saat mulai menyiapkan pesanan.
3. Tombol **selesai** hanya aktif setelah transaksi **lunas**; sistem menolak penyelesaian pesanan yang belum dibayar.
4. Pesanan hanya dapat dibatalkan sebelum selesai dan belum dibayar; stok serta poin otomatis dikembalikan.
5. Setelah tamu selesai, barista mengubah status meja menjadi **kosong** atau melepaskan sesi meja.

### Langkah 5: Mencetak Rekap Laporan Penjualan

1. Admin membuka **Sales & Revenue**, lalu klik **Cetak Laporan**.
2. Pilih periode (**dari**–**sampai**) dan filter opsional (tipe pesanan, metode pembayaran), lalu klik **Tampilkan**.
3. Halaman pratinjau A4 menampilkan kop toko, ringkasan omzet, rekap harian, rekap per produk, dan rekap metode bayar.
4. Klik **Cetak** untuk mencetak atau memilih **Save as PDF**.
5. Klik **Unduh CSV** pada periode yang sama untuk membuka rincian transaksi di Excel.

## Bab IV: Pemecahan Masalah Sederhana (Troubleshooting)

### A. Gagal Mengunggah Gambar Produk (Ukuran Melebihi Batas)

Penyebab: berkas melebihi batas ukuran atau tipe berkas tidak diizinkan.
Penanganan: kompres gambar terlebih dahulu (disarankan di bawah 2 MB) dan gunakan format JPG/PNG, lalu unggah ulang dari **Master data → Produk**.

### B. Notifikasi Meja Sedang Dipakai

Penyebab: meja masih memiliki sesi check-in aktif atau pesanan yang belum selesai.
Penanganan: pilih meja lain, atau minta barista menandai meja **kosong** bila tamu sebelumnya sudah pergi.

### C. Notifikasi Stok Tidak Mencukupi

Penyebab: stok produk berkurang saat item ditambahkan atau pesanan dibuat.
Penanganan: perbarui jumlah stok di **Master data → Produk**, lalu ulangi pemesanan dengan jumlah yang tersedia.

### D. Pesan "Anda Berada di Luar Radius Cafe"

Penyebab: geofence aktif dan posisi GPS di luar radius cafe, atau browser menolak akses lokasi.
Penanganan: datang ke area cafe lalu klik **Cek Ulang**, izinkan akses lokasi pada browser, atau minta admin mematikan geofence sementara bila demo dilakukan lewat IP LAN.

### E. Pesan "Midtrans Belum Dikonfigurasi"

Penyebab: kredensial Midtrans belum diisi atau masih bernilai contoh.
Penanganan: admin membuka **Store Settings → Midtrans**, mengisi *Server Key* dan *Client Key*, lalu menyimpan. Selama belum diisi, gunakan pembayaran **tunai**.
