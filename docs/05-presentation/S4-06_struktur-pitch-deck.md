### 6. S4-06: STRUKTUR MATERI SLIDE PRESENTASI (PITCH DECK 10 SLIDE)

*Catatan Media Presenter: Berkas interaktif siap pakai tersedia pada `pitch-deck.html` yang dilengkapi penghitung waktu 10 menit (countdown timer), teleprompter naskah bicara per slide, dan grafik alokasi scope proyek.*

Sesi ujian presentasi di hadapan Asesor Industri dibagi menjadi dua segmen: **10 Menit Pemaparan Pitching (10 Slide) dilanjutkan 10–15 Menit Demonstrasi Aplikasi Langsung (Live Demo)**.

Berkas media: [`pitch-deck.html`](pitch-deck.html)

| **Nomor Slide** | **Judul & Topik Slide** | **Waktu Paparan** | **Poin Kunci yang Wajib Disampaikan Siswa** |
| --- | --- | --- | --- |
| **Slide 1** | Identitas Proyek & Tim | 30 detik | Nama aplikasi (Tejas Coffee POS), identitas 2 siswa, mitra cafe, dan sekolah. |
| **Slide 2** | Masalah Riil Lapangan (_As-Is_) | 45 detik | Antrean 4–6 menit per pesanan, stok menu tidak sinkron, dan rekap penjualan manual 1–2 jam tiap tutup toko. |
| **Slide 3** | Target Persona Pengguna | 45 detik | Memperkenalkan Rani (pelanggan butuh kecepatan) & Pak Bagas (pemilik merangkap kasir butuh angka & stok). |
| **Slide 4** | Solusi Aplikasi Tejas Coffee POS | 45 detik | Pesan mandiri lewat QR meja, POS satu layar (tunai/QRIS/VA), KDS barista, dan laporan siap cetak. |
| **Slide 5** | Batasan Lingkup (In vs Out Scope) | 30 detik | Menjelaskan mengapa memakai QRIS dinamis + unggah bukti manual dibanding membangun payment gateway sendiri. |
| **Slide 6** | Arsitektur Sistem & Database 3NF | 45 detik | Skema MVC Laravel 12, 14 tabel relasional terhubung foreign key tanpa redundansi. |
| **Slide 7** | Fitur Killer: Logika Anti-Oversell Stok | 60 detik | Memaparkan row lock `SELECT ... FOR UPDATE` di dalam transaksi ACID dan perhitungan `bcmath` agar stok tidak minus. |
| **Slide 8** | Penjaminan Kualitas (QA & Security) | 45 detik | Hasil 138 test cases 100% PASS, parameter binding anti SQL Injection, dan otorisasi `PesananPolicy` anti-IDOR. |
| **Slide 9** | Dampak Sosial & Ekonomi Lokal | 30 detik | Menghilangkan antrean & salah catat pesanan, menaikkan omzet jam sibuk UMKM kopi. |
| **Slide 10** | Penutup & Ajakan Live Demo | 15 detik | Mengucapkan terima kasih dan mengundang penguji menyaksikan live demo transaksi. |
