# Bank Soal & Strategi Pertanggungjawaban Individu

Sepuluh pertanyaan wajib yang disesuaikan dengan kode **Tejas Coffee POS**.
Semua rujukan berkas benar-benar ada di repositori, sehingga saat penguji meminta
"tunjukkan baris kodenya", Anda bisa langsung membukanya.

**Aturan menjawab:** 45 detik per jawaban, sebutkan nama berkas, dan tawarkan
untuk menunjukkan kode. Jangan menghafal kata per kata — hafalkan alurnya.

---

## Q1. "Dua kasir menjual croissant terakhir di detik yang sama. Bagaimana sistem Anda mencegah stok minus?"

> "Stok dikurangi di dalam `DB::transaction` dengan `lockForUpdate()`, jadi baris
> produk terkunci sampai transaksi selesai. Kasir kedua akan menunggu, lalu
> membaca stok terbaru dan ditolak dengan pesan stok tidak mencukupi. Kami juga
> menghitung stok gabungan lintas varian sebelum item masuk keranjang."

**Bukti:** `app/Http/Controllers/OrderController.php` (method `store`),
`app/Models/Produk.php`, tes `tests/Feature/CustomerOrderFlowTest.php`.

**Pertanyaan lanjutan yang mungkin:** *"Kenapa tidak cukup cek stok di JavaScript?"*
→ "Karena dua browser bisa mengirim request bersamaan. Validasi klien hanya untuk
kenyamanan; kebenaran data harus di server dan di dalam transaksi DB."

---

## Q2. "Mengapa checkout memakai transaksi basis data dan `bcmath`?"

> "Checkout menyentuh beberapa tabel sekaligus: `pesanan`, `detail_pesanan`,
> `transaksi`, `produk` (stok), dan `pelanggan` (poin). Kalau listrik padam di
> tengah proses, `ROLLBACK` menjamin tidak ada pesanan menggantung tanpa tagihan.
> Perhitungan uang memakai `bcmath`, bukan float, supaya tidak ada selisih
> pembulatan pada nilai rupiah."

**Bukti:** `OrderController@store`, `Pesanan::batalkan()` (rollback stok & poin).

---

## Q3. "Bagaimana password pengguna diamankan?"

> "Kami tidak pernah menyimpan password asli. Laravel menyimpannya sebagai hash
> Bcrypt satu arah dengan salt otomatis (`BCRYPT_ROUNDS=12`). Verifikasi memakai
> `Hash::check`, jadi bahkan admin database tidak bisa membaca password pengguna.
> Login dibatasi `throttle:10,1` untuk mencegah percobaan berulang."

**Bukti:** `app/Http/Controllers/Auth/LoginController.php`, `phpunit.xml`.

---

## Q4. "Tunjukkan bagaimana sistem mencegah SQL Injection!"

> "Semua query memakai Eloquent atau Query Builder dengan parameter binding,
> sehingga input pengguna tidak pernah dirangkai menjadi perintah SQL. Contohnya
> filter pencarian produk memakai `where('nama_produk','like',"%{$search}%")`
> yang dikirim sebagai parameter terikat. Tidak ada `DB::raw` yang menerima input
> pengguna."

**Bukti:** `app/Http/Controllers/ProductController.php`.

**Catatan kejujuran teknis:** kalau penguji menemukan `selectRaw`, jelaskan bahwa
yang di-`raw` hanya ekspresi tanggal/agregat internal, bukan input pengguna.

---

## Q5. "Apa beda validasi di browser dengan validasi di server?"

> "Validasi HTML hanya untuk kenyamanan tampilan dan mudah ditembus dengan
> Postman atau mematikan JavaScript. Validasi utama ada di server memakai
> `$request->validate()`. Contoh nyata: kalau client mengirim `harga_satuan`
> lebih rendah dari harga dasar produk, server menolak dengan 422 — jadi tidak
> mungkin memesan kopi seharga Rp 1."

**Bukti:** `OrderController@store` (blok *Floor: harga kiriman tak boleh di bawah
harga dasar*).

---

## Q6. "Bagaimana keamanan unggahan gambar produk dan bukti pembayaran?"

> "Berkas dibatasi pada tipe gambar yang diizinkan dan ukuran maksimal, lalu nama
> aslinya dibangun ulang menjadi nama unik. Tujuannya mencegah penyerang menyisipkan
> skrip berbahaya lewat nama/ekstensi berkas. Berkas disimpan di storage, bukan di
> direktori publik yang bisa dieksekusi."

**Bukti:** `app/Http/Controllers/Admin/MasterProdukController.php`,
konfigurasi `filesystems` di `config/filesystems.php`.

---

## Q7. "Buktikan skema database Anda sudah 3NF!"

> "Semua tabel punya primary key tunggal (1NF). Kolom non-kunci bergantung penuh
> pada PK (2NF) — contohnya `detail_pesanan` memakai `id_detail` dan seluruh
> kolomnya bergantung pada kunci itu. Tidak ada ketergantungan transitif (3NF):
> nama kategori tidak disimpan di `produk` (cukup `id_kategori`), data member
> dipisah dari `pengguna`, dan detail pembayaran dipisah ke `transaksi` yang
> berelasi 1:1 dengan `pesanan`."

**Bukti:** `database/migrations/*`, diagram ERD di `docs/03-technical/TECHNICAL_DOCS.md` §2.

**Pertanyaan jebakan:** *"Bukankah `harga_satuan` di detail pesanan duplikasi
data produk?"* → "Bukan. Itu **snapshot** nilai transaksi saat itu, sehingga
perubahan harga menu tidak mengubah laporan historis. Duplikasi data referensi
yang dilarang 3NF berbeda dengan pencatatan nilai historis."

---

## Q8. "Kalau internet ruang ujian diputus saat demo, apa yang terjadi?"

> "Kami punya dua jalur. Pertama, server lokal LAN lewat `serve-lan.bat` yang
> diakses dari HP penguji melalui IP laptop pada WiFi yang sama — seluruh fitur
> tetap jalan karena database ada di laptop. Kedua, video cadangan tiga menit dan
> laporan A4 tercetak bila perangkat bermasalah. Satu-satunya fitur yang butuh
> internet adalah pembayaran Midtrans; alur tunai tetap bisa didemokan."

**Bukti:** `docs/08-deployment/DEPLOYMENT.md`, `docs/08-deployment/serve-lan.bat`.

---

## Q9. "Bagaimana pembagian kerja kalian, dan apa buktinya?"

> "Siswa 1 menangani analisis kebutuhan, antarmuka Blade/Tailwind, alur pemesanan
> pelanggan, dan seluruh dokumentasi pengguna. Siswa 2 menangani skema basis data,
> controller transaksi, integrasi Midtrans, keamanan, dan API. Buktinya riwayat
> commit di Git — setiap commit menyebut modul yang dikerjakan — dan kami saling
> bisa menjelaskan bagian rekan."

**Bukti:** `git log --oneline`, pembagian pada `README.md` §Anggota Tim.

**Penting:** penguji sering minta Anda menjelaskan bagian rekan. Siapkan jawaban
untuk minimal satu fitur milik rekan Anda.

---

## Q10. "Kalau aplikasi ini dipakai cafe sungguhan, apa kendala operasional terbesar dan solusinya?"

> "Kendalanya literasi digital staf dan keengganan pelanggan memindai QR. Solusi
> yang sudah kami terapkan: (1) antarmuka kasir satu layar dengan tombol besar dan
> istilah Indonesia, (2) fallback pesanan lewat kasir bila pelanggan tidak mau scan,
> (3) kode booking manual bila kamera gagal, dan (4) geofence bisa dimatikan admin
> saat kondisi lapangan tidak mendukung. Rencana berikutnya adalah notifikasi
> WhatsApp untuk pelanggan."

**Bukti:** `docs/02-manual/USER_MANUAL.md` §2.2, `SettingController@updateGeofence`.

---

## Pertanyaan Tambahan yang Sering Muncul

| Pertanyaan | Arah jawaban |
| :--- | :--- |
| "Kenapa omzet laporan memakai `subtotal`, bukan `total_bayar`?" | `total_bayar` memuat uang fisik yang diserahkan; kembalian bukan pendapatan. Uang diterima dan kembalian dilaporkan terpisah. |
| "Bagaimana struk dicegah dibuka orang lain?" | `PesananPolicy` memeriksa pemilik atau peran kasir/admin — anti-IDOR. |
| "Bagaimana webhook Midtrans dipalsukan?" | Signature SHA-512 diverifikasi dengan `hash_equals` sebelum status diubah. |
| "Kenapa QR meja memakai token, bukan ID?" | Token acak 40 karakter agar URL meja tidak bisa ditebak atau dipakai ulang. |
| "Bagaimana meja kembali kosong sendiri?" | Command `meja:bersihkan` tiap menit + `Pesanan::sinkronMeja()` saat status berubah. |
| "Kenapa geofence diverifikasi di server?" | Koordinat dikirim ke backend dan dihitung dengan rumus Haversine; mengubah JavaScript tidak melewatinya. |
| "Kalau pesanan dibatalkan setelah bayar?" | Ditolak dengan 422; refund dilakukan kasir secara manual — keputusan desain yang kami sebutkan di batas lingkup. |
| "Apa yang belum dikerjakan?" | Notifikasi WhatsApp, PWA offline, dan settlement otomatis — tercantum di slide *Out of Scope*. |

## Simulasi Live Debugging (latihan 5 menit)

Minta rekan/guru merusak **satu** baris berikut, lalu perbaiki sambil menjelaskan:

1. Ubah `Pesanan::TRANSISI_STATUS['baru']` agar `selesai` diizinkan → tunjukkan tes gagal, kembalikan.
2. Hapus `lockForUpdate()` pada query produk → jelaskan risikonya.
3. Ubah `where('status_pembayaran','lunas')` menjadi `menunggu` pada laporan → angka omzet jadi nol.
4. Ganti `hash_equals` dengan `==` pada webhook → jelaskan risiko *timing attack*.
5. Hapus middleware `geofence` dari grup rute pelanggan → halaman terbuka dari luar radius.

Setelah setiap perbaikan, jalankan hanya tes terkait:

```bash
php artisan test --filter=CustomerOrderFlowTest
php artisan test --filter=AdminSalesReportTest
php artisan test --filter=GeofenceTest
```
