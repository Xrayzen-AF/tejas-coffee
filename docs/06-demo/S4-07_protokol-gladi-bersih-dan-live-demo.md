### 7. S4-07: PROTOKOL GLADI BERSIH & LIVE DEMO APP (10 MENIT)

Saat mendemonstrasikan aplikasi di hadapan asesor industri, dilarang berpindah-pindah layar tanpa alur cerita. Gunakan skenario peran nyata (_role-playing scenario_):

```mermaid
sequenceDiagram
    autonumber
    actor Penguji as 👨‍⚖️ Penguji Industri
    participant Siswa1 as 👨‍💻 Siswa 1 (Presenter/Pelanggan)
    participant Siswa2 as 👨‍💻 Siswa 2 (Technical/Kasir & Admin)
    participant App as 💻 Sistem Tejas Coffee POS

    Note over Siswa1,Siswa2: Skenario 1: Alur Pesan Mandiri dari Meja (Happy Path)
    Siswa1->>App: Pindai QR Meja 01 & Buka Menu Publik
    Siswa1->>App: Check-in Meja 01 (berlaku 90 menit)
    Siswa1->>App: Pilih Mont Blanc ukuran Large, catatan "less sugar" & Tambah ke Keranjang
    App-->>Penguji: Total dihitung ulang di server, keranjang tersimpan
    Siswa1->>App: Terapkan Voucher WELCOME10 & Tukar Bean Points
    Siswa1->>App: Checkout Dine-in & Buat Pesanan
    App-->>Penguji: Pesanan PSN-xxxxx dibuat (status "baru") & muncul di antrean kasir

    Note over Siswa1,Siswa2: Skenario 2: Pembuktian Pencegahan Oversell Stok
    Siswa1->>App: Buka Tab Incognito & Pesan Menu yang Stoknya Tersisa 1
    App-->>Penguji: ❌ SISTEM MENOLAK: "Stok tidak mencukupi" (Zero Oversell Terbukti!)
    Note over App: Dua pesanan bersamaan dikunci SELECT ... FOR UPDATE dalam satu transaksi

    Note over Siswa1,Siswa2: Skenario 3: Verifikasi di Sisi Kasir (Pak Bagas)
    Siswa2->>App: Login kasir@kopi.dev & Tampilkan Papan Meja (Meja 01 Terisi)
    Siswa2->>App: Buka Panel Pembayaran Pesanan PSN-xxxxx & Klik QRIS
    App-->>Penguji: QR QRIS tampil (cadangan: Tunai + kalkulator kembalian)
    Siswa2->>App: Klik "Tandai Lunas" & Cetak Struk 80 mm
    App-->>Penguji: Status pembayaran LUNAS & Bean Points pelanggan bertambah

    Note over Siswa1,Siswa2: Skenario 4: Dapur & Laporan Pemilik
    Siswa2->>App: Login barista@kopi.dev & Buka KDS (tiket + catatan "less sugar")
    Siswa2->>App: Klik Bump, lalu Ubah Status menjadi diproses & selesai
    App-->>Penguji: Status mengikuti state machine baru -> diproses -> selesai (tidak bisa mundur)
    Siswa2->>App: Login admin@kopi.dev & Sales & Revenue, periode hari ini
    Siswa2->>App: Cetak Laporan A4 & Unduh CSV
    App-->>Penguji: Transaksi demo muncul utuh di laporan tanpa penyalinan manual
```

Alokasi 10 menit untuk Skenario 1–4 beserta perintah terminalnya ada di [`DEMO_SCRIPT.md`](DEMO_SCRIPT.md); halaman ini adalah protokol peran yang wajib diikuti saat gladi bersih maupun saat ujian.
