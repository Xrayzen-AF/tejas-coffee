### 8. S4-08: BANK SOAL & STRATEGI PERTANGGUNGJAWABAN INDIVIDU (TECHNICAL DEFENSE)

Asesor industri akan mengajukan pertanyaan mendalam untuk memastikan siswa tidak sekadar menjiplak kode (_copy-paste_). Berikut adalah 10 pertanyaan teknis yang wajib dikuasai:

#### Q1: "Dua kasir menjual menu terakhir pada detik yang sama. Bagaimana sistem Anda mencegah stok menjadi minus?"

-   **Jawaban Standar Siswa:**

_Pada saat checkout, stok tidak dihitung di browser, melainkan dikunci di basis data. Kami membungkus prosesnya dengan `DB::transaction` dan memanggil `lockForUpdate()` pada baris produk, sehingga baris itu terkunci sampai transaksi selesai. Kasir kedua akan menunggu, membaca stok terbaru, lalu ditolak dengan pesan stok tidak mencukupi. Kami juga menjumlahkan stok lintas varian sebelum item masuk keranjang, agar satu menu tidak bisa dipesan melebihi sisanya._

#### Q2: "Mengapa Anda menggunakan Database Transaction (BEGIN ... COMMIT / ROLLBACK) pada alur checkout?"

-   **Jawaban Standar Siswa:**

_Karena satu kali checkout menyentuh beberapa tabel sekaligus: `pesanan`, `detail_pesanan`, `transaksi`, `produk` untuk stok, dan `pelanggan` untuk poin loyalitas. Jika server mati atau listrik padam di tengah proses, `ROLLBACK` menjamin tidak ada pesanan menggantung tanpa tagihan dan tidak ada stok yang terpotong percuma, sehingga prinsip ACID terpenuhi. Perhitungan uang memakai `bcmath` (`bcadd`, `bcmul`) supaya tidak ada selisih pembulatan rupiah._

#### Q3: "Bagaimana Anda mengamankan password pengguna di basis data?"

-   **Jawaban Standar Siswa:**

_Password disimpan sebagai hash satu arah Bcrypt dengan salt acak bawaan Laravel, dengan `BCRYPT_ROUNDS` di berkas konfigurasi. Kami tidak pernah menyimpan password dalam bentuk teks terbuka, dan verifikasi memakai `Hash::check` sehingga bahkan administrator basis data tidak dapat membaca password asli. Login juga dibatasi `throttle:10,1` agar tidak bisa ditebak berulang kali._

#### Q4: "Tunjukkan bagaimana sistem Anda mencegah serangan SQL Injection!"

-   **Jawaban Standar Siswa:**

_(Membuka `app/Http/Controllers/ProductController.php`)_ _Seluruh query memakai Eloquent atau Query Builder dengan parameter binding, sehingga input pengguna tidak pernah dirangkai menjadi perintah SQL. Contohnya filter pencarian produk dikirim sebagai parameter terikat, bukan disambung ke string query. Karakter kutip tunggal diperlakukan sebagai teks biasa. Satu-satunya `raw` yang kami pakai hanya ekspresi tanggal dan agregat internal, tidak pernah menerima input pengguna._

#### Q5: "Apa yang membedakan validasi di browser (HTML) dengan validasi di server (PHP)?"

-   **Jawaban Standar Siswa:**

_Validasi HTML hanya untuk kenyamanan tampilan agar form tidak terkirim kosong, dan sangat mudah ditembus dengan mematikan JavaScript atau mengirim request langsung lewat Postman. Karena itu validasi utama berada di server memakai `$request->validate()` dan aturan validasi di controller. Contoh nyatanya, bila client mengirim `harga_satuan` lebih rendah dari harga dasar produk, server menolak dengan status 422 sehingga tidak mungkin memesan kopi seharga satu rupiah._

#### Q6: "Bagaimana sistem Anda mencegah kebocoran file berbahaya saat pengguna mengunggah berkas?"

-   **Jawaban Standar Siswa:**

_Kami menerapkan whitelist tipe berkas yang ketat hanya untuk gambar, yaitu `jpeg`, `png`, `jpg`, `webp`, `gif`, dan `avif`, membatasi ukuran maksimal 5 MB, lalu membangun ulang nama berkas menjadi nama unik sebelum disimpan. Berkas diletakkan di direktori storage, bukan direktori publik yang bisa dieksekusi, sehingga penyerang tidak bisa menanam skrip backdoor lewat nama atau ekstensi. Perlu dicatat, bukti pembayaran tidak diunggah sebagai berkas: kasir mencatat nomor referensi dan nama bank atau e-wallet, sehingga tidak ada permukaan unggahan tambahan._

#### Q7: "Jelaskan struktur tabel basis data Anda dan buktikan bahwa skema tersebut sudah memenuhi bentuk normal ketiga (3NF)!"

-   **Jawaban Standar Siswa:**

_(Membuka diagram ERD di `docs/03-technical/TECHNICAL_DOCS.md`)_ _Keempat belas tabel memiliki primary key tunggal, sehingga memenuhi 1NF. Seluruh kolom non-kunci bergantung penuh pada primary key dan bukan sebagian kunci, sehingga 2NF terpenuhi; contohnya `detail_pesanan` memakai `id_detail` dan semua kolomnya bergantung pada kunci itu. Tidak ada ketergantungan transitif, sehingga 3NF terpenuhi: nama kategori tidak disimpan di `produk` melainkan di tabel `kategori` yang dirujuk `id_kategori`, data pelanggan dipisah dari tabel `pengguna`, dan detail pembayaran dipisah ke tabel `transaksi` yang berelasi satu-satu dengan `pesanan`. Kolom `harga_satuan` di detail pesanan bukan duplikasi yang melanggar 3NF, melainkan snapshot nilai transaksi saat itu agar laporan lama tidak berubah ketika harga menu naik._

#### Q8: "Apa yang terjadi jika koneksi internet terputus mendadak saat demo berlangsung?"

-   **Jawaban Standar Siswa:**

_Kami menyiapkan mitigasi kontingensi offline. Di laptop sudah tersedia skrip `serve-lan.bat` yang menjalankan server Laravel pada `0.0.0.0` sehingga seluruh perangkat di WiFi yang sama dapat mengaksesnya lewat IP laptop; basis data dan aset ada di laptop, jadi sistem tetap berjalan penuh. Bila perangkat bermasalah, kami memutar video cadangan dan menunjukkan laporan A4 yang sudah dicetak. Satu-satunya fitur yang benar-benar butuh internet adalah pembayaran Midtrans, dan alur tunai tetap bisa didemokan._

#### Q9: "Bagaimana pembagian peran kerja di antara kalian berdua selama pengerjaan proyek ini?"

-   **Jawaban Standar Siswa:**

_Saya (Siswa 1) bertanggung jawab atas analisis kebutuhan, perancangan antarmuka Blade dan Tailwind, alur pemesanan pelanggan dari QR meja sampai keranjang, serta penyusunan manual pengguna. Rekan saya (Siswa 2) bertanggung jawab atas skema basis data, controller transaksi, integrasi Midtrans, keamanan, dan API operasional. Seluruh kolaborasi tercatat transparan pada commit log repositori Git, dan kami masing-masing mampu menjelaskan bagian rekan._

#### Q10: "Jika aplikasi ini benar-benar diterapkan di cafe mitra, apa kendala operasional terbesar dan bagaimana solusinya?"

-   **Jawaban Standar Siswa:**

_Kendala terbesar adalah literasi digital staf senior dan keengganan sebagian pelanggan memindai QR. Solusi yang sudah kami terapkan: antarmuka kasir satu layar dengan tombol besar dan istilah bahasa Indonesia, jalur cadangan pesanan lewat kasir bila pelanggan tidak mau memindai, kode booking manual bila kamera gagal, serta geofence yang bisa dinonaktifkan admin saat kondisi lapangan tidak mendukung. Rencana berikutnya adalah notifikasi WhatsApp untuk pelanggan dan mode PWA agar halaman tetap terbuka saat sinyal lemah._
