# Naskah Gladi Bersih & Live Demo

Total ±33 menit: **10 menit pitching (10 slide)**, **8 menit demo aplikasi**, **15 menit tanya-jawab**.
Semua langkah di bawah sudah diuji; jangan menambah langkah baru saat hari-H.

## Persiapan 10 Menit Sebelum Masuk

| # | Item | Perintah / Lokasi |
| :---: | :--- | :--- |
| 1 | Database terisi data demo | `php artisan migrate:fresh --seed` |
| 2 | Aset terbaru | `npm run build` |
| 3 | Server utama | `php artisan serve` |
| 4 | Server cadangan (tab terminal kedua) | `docs/08-deployment/serve-lan.bat` |
| 5 | Buka 3 tab browser | Tab A: jendela normal (pelanggan). Tab B: incognito (kasir). Tab C: incognito kedua (barista + admin) |
| 6 | HP untuk pindai QR | Buka `/admin/master/meja/qr` lalu cetak/preview QR meja |
| 7 | Slide presentasi | `docs/05-presentation/pitch-deck.html` |
| 8 | Video cadangan | `docs/06-demo/video-cadangan.mp4` (siap di desktop) |
| 9 | Laporan cadangan tercetak | 1 lembar laporan A4 sebagai bukti bila laptop bermasalah |

Wajib: **jangan pernah** menjalankan `migrate:fresh` setelah demo dimulai.

---

## Segmen 1 — Pitching 10 Menit (10 Slide)

| Waktu | Slide | Poin yang diucapkan |
| :--- | :---: | :--- |
| 0:00–0:30 | 1 | Identitas tim, nama aplikasi, tujuan rilis kandidat |
| 0:30–1:15 | 2 | Empat masalah lapangan: antrean, stok tidak sinkron, rekap manual, meja tidak terpantau |
| 1:15–2:00 | 3 | Persona Rani (pelanggan) dan Pak Bagas (pemilik/kasir) |
| 2:00–2:45 | 4 | Enam solusi utama |
| 2:45–3:15 | 5 | Batas lingkup + grafik alokasi 72% in-scope / 28% out |
| 3:15–4:00 | 6 | Arsitektur MVC, 14 tabel 3NF, 138 tes |
| 4:00–5:00 | 7 | Anti-oversell: row lock SELECT ... FOR UPDATE dan bcmath |
| 5:00–5:45 | 8 | Hasil QA & keamanan: 138 tes, PesananPolicy, signature webhook |
| 5:45–6:30 | 9 | Dampak dan rencana keberlanjutan |
| 6:30–7:00 | 10 | Ajakan demo + akun uji coba |
| 7:00–10:00 | — | Cadangan: transisi slide, pertanyaan sisipan, atau memperpanjang slide 7 (demo kode) |

Kalimat pembuka yang disarankan: *"Kami membangun sistem pemesanan coffee shop
yang menyatukan meja, kasir, barista, dan laporan pemilik dalam satu sumber data.
Demo akan kami mulai dari pelanggan yang memesan lewat QR meja."*

---

## Segmen 2 — Live Demo 8 Menit (skenario peran)

### 2.1 Pelanggan memesan dari meja (2 menit) — Tab A

1. Login `pelanggan@kopi.dev` / `password`.
2. Bila muncul halaman **Verifikasi Lokasi**: klik **Izinkan Lokasi** → **Verifikasi**.
   *(Bila geofence dimatikan, langkah ini dilewati — sebutkan alasannya.)*
3. Buka `/meja/{qr_token}` (atau **Menu → Pindai QR Meja**).
4. Klik **Check-in Meja** → meja tercatat atas nama pelanggan, berlaku 90 menit.
5. **Menu** → pilih *Cappuccino* → kustomisasi (ukuran Large, catatan *less sugar*) → **Tambah ke Keranjang**.
6. Tambah satu item lagi. Buka **Keranjang**:
   - masukkan voucher `WELCOME10` → tunjukkan diskon muncul;
   - centang **Tukar Bean Points**;

   > Jika poin pelanggan kosong, sebutkan: "poin bertambah otomatis setiap
   > transaksi lunas; untuk demo saya tampilkan mekanisme potongannya."

7. Pilih **Dine-in** → **Checkout** → **Buat Pesanan**.
8. Catat **nomor pesanan** yang muncul (format `PSN-…`).

**Kalimat penjelas:** *"Harga dihitung ulang di server. Kalau saya mengubah harga
lewat browser, server menolak — ini yang kami buktikan pada pengujian."*

### 2.2 Kasir menagih dengan QRIS (2,5 menit) — Tab B

1. Login `kasir@kopi.dev` / `password`.
2. Papan status meja: tunjukkan meja tadi berubah menjadi **terisi**.
3. Buka panel pembayaran pesanan tersebut → klik **QRIS**.
4. Tunjukkan QR muncul di layar (atau pesan konfigurasi bila kredensial sandbox
   belum diisi — jelaskan bahwa alur tunai tetap bisa didemokan).
5. Klik **Tandai Lunas** → status pesanan berubah, poin pelanggan bertambah.
6. Klik **Cetak Struk** → tunjukkan struk 80 mm.

**Cadangan bila Midtrans tidak dapat diakses:** gunakan **Tunai**, isi uang
diterima, tunjukkan kalkulator kembalian, lalu tandai lunas. Sampaikan:
*"Metode non-tunai memerlukan kredensial sandbox aktif; alur logikanya sama."*

### 2.3 Barista memproses pesanan (1,5 menit) — Tab C

1. Login `barista@kopi.dev` / `password`.
2. KDS menampilkan pesanan baru beserta catatan *less sugar*.
3. Klik **Bump** → tiket tersemat di atas papan (prioritas).
4. Ubah status pesanan menjadi **diproses**, lalu **selesai**
   (tombol ini hanya aktif setelah lunas — tunjukkan bila penguji bertanya).
5. Ubah status meja menjadi **kosong** setelah tamu selesai.

### 2.4 Admin mencetak laporan (2 menit) — Tab C

1. Login `admin@kopi.dev` / `password`.
2. **Sales & Revenue** → **Cetak Laporan**.
3. Pilih periode hari ini → **Tampilkan**.
4. Tunjukkan: kop laporan, ringkasan omzet, rekap metode bayar, lampiran transaksi.
5. Klik **Cetak** → pilih *Save as PDF* (bila penguji ingin berkas), atau
   tunjukkan lembar A4 yang sudah disiapkan.
6. Klik **Unduh CSV** → buka di Excel untuk membuktikan format langsung rapi.

**Kalimat penutup demo:** *"Satu transaksi tadi muncul utuh di laporan, tanpa
satu pun langkah penyalinan manual."*

---

## Segmen 3 — Tanya Jawab 15 Menit

Aturan menjawab:

1. Ulangi inti pertanyaan dengan kalimat sendiri (5 detik) agar tidak salah arah.
2. Jawab maksimal 45 detik, lalu tawarkan: *"Saya bisa tunjukkan baris kodenya."*
3. Bila tidak tahu: *"Belum kami tangani pada rilis ini karena di luar lingkup —
   rencananya ada di slide keberlanjutan."* Jangan mengarang.
4. Satu siswa menjawab, siswa lain menyiapkan file yang relevan.

Bank soal lengkap: `docs/07-defense/BANK_SOAL_DEFENSE.md`.

---

## Rencana Kontinjensi

| Gejala | Tindakan langsung |
| :--- | :--- |
| Internet putus | Ganti ke server LAN: buka `http://<IP-laptop>:8000`, lanjutkan alur yang sama |
| Laptop mati / server error | Putar `video-cadangan.mp4`, lalu tunjukkan laporan A4 tercetak |
| Midtrans error / kredensial kosong | Lanjut dengan pembayaran tunai; jelaskan alur non-tunai secara lisan |
| Data demo hilang | `php artisan migrate:fresh --seed` **hanya** bila demo belum dimulai |
| Halaman error 500 | Buka `storage/logs/laravel.log`, tunjukkan penanganan error yang rapi (halaman error kustom) |
| Penguji minta ubah kode di tempat | Kerjakan edit kecil, jelaskan sebelum mengetik: apa yang diubah dan mengapa |

## Checklist Penutup

- [ ] Semua akun demo bisa login
- [ ] Pesanan demo berhasil dibuat dan muncul di kasir
- [ ] Pembayaran ditandai lunas dan struk tercetak
- [ ] KDS menampilkan pesanan dan status berubah ke `selesai`
- [ ] Laporan hari ini memuat transaksi demo
- [ ] Server LAN sudah diuji dari HP anggota lain
- [ ] Video cadangan bisa diputar tanpa internet
