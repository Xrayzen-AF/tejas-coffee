# Diagram Sistem (PlantUML) — Tejas Coffee POS

Versi **PlantUML** dari diagram yang sama dengan [`DIAGRAM_SISTEM.md`](DIAGRAM_SISTEM.md).
Semua blok bisa di-copy langsung ke:

- <http://www.plantuml.com/plantuml/uml> (paste → Submit → PNG/SVG)
- VS Code (extension **PlantUML** / **PlantUML Preview**)
- IntelliJ / Eclipse / Confluence (plugin PlantUML)
- CLI: `java -jar plantuml.jar diagram.puml`

> Catatan render: PlantUML di sini diuji dengan **PlantUML 1.2024.8**. Untuk diagram
> entity (ERD & skema) butuh GraphViz; bila tidak ada, PlantUML otomatis memakai
> mesin layout bawaannya (Smetana) dengan tampilan sedikit berbeda.

Daftar isi:

1. [Use Case Diagram](#1-use-case-diagram)
2. [Activity Diagram per Peran](#2-activity-diagram-per-peran)
3. [Flowchart per Peran](#3-flowchart-per-peran)
4. [Sequence Diagram](#4-sequence-diagram)
5. [ERD](#5-erd)
6. [Skema Database](#6-skema-database)
7. [Alur Transaksi](#7-alur-transaksi)

---

## 1. Use Case Diagram

```plantuml
@startuml
left to right direction
skinparam packageStyle rectangle
skinparam shadowing false

actor "Admin" as Admin
actor "Kasir" as Kasir
actor "Barista" as Barista
actor "Pelanggan" as Pelanggan
actor "Tamu" as Tamu

rectangle "Tejas Coffee POS" {
  usecase "Login sentral (email / No. HP)" as UC01
  usecase "Registrasi akun pelanggan" as UC02
  usecase "Logout" as UC03

  usecase "Lihat menu & filter kategori" as UC10
  usecase "Kustomisasi item & catatan" as UC11
  usecase "Kelola keranjang" as UC12
  usecase "Pakai voucher promo" as UC13
  usecase "Tukar poin loyalitas" as UC14
  usecase "Checkout pesanan" as UC15
  usecase "Scan QR meja & check-in" as UC16
  usecase "Verifikasi kode booking" as UC17
  usecase "Pantau status pesanan" as UC18
  usecase "Lihat riwayat pesanan" as UC19
  usecase "Ubah profil & No. HP" as UC20
  usecase "Batalkan pesanan" as UC21

  usecase "Buat tiket pesanan (POS)" as UC30
  usecase "Pilih & pantau status meja" as UC31
  usecase "Tandai lunas (tunai / transfer)" as UC32
  usecase "Buat pembayaran QRIS / Virtual Account" as UC33
  usecase "Cetak struk 80 mm" as UC34
  usecase "Ubah status pesanan" as UC35
  usecase "Batalkan pesanan" as UC36

  usecase "Lihat antrean pesanan" as UC40
  usecase "Tandai pesanan diproses" as UC41
  usecase "Tandai pesanan selesai" as UC42
  usecase "Bump / dismiss kartu pesanan" as UC43
  usecase "Ubah status meja" as UC44

  usecase "Kelola akun pegawai" as UC50
  usecase "Kelola master kategori" as UC51
  usecase "Kelola master produk" as UC52
  usecase "Kelola master meja & cetak QR" as UC53
  usecase "Kelola master promo" as UC54
  usecase "Kelola inventori & reorder biji" as UC55
  usecase "Kelola pelanggan & poin" as UC56
  usecase "Lihat laporan penjualan" as UC57
  usecase "Atur pengaturan toko" as UC58
  usecase "Atur kredensial Midtrans" as UC59
  usecase "Kelola semua pesanan" as UC60
}

' ===== Tamu =====
Tamu --> UC01
Tamu --> UC02
Tamu --> UC16
Tamu --> UC17

' ===== Pelanggan =====
Pelanggan --> UC01
Pelanggan --> UC03
Pelanggan --> UC10
Pelanggan --> UC11
Pelanggan --> UC12
Pelanggan --> UC13
Pelanggan --> UC14
Pelanggan --> UC15
Pelanggan --> UC16
Pelanggan --> UC18
Pelanggan --> UC19
Pelanggan --> UC20
Pelanggan --> UC21

' ===== Kasir =====
Kasir --> UC01
Kasir --> UC03
Kasir --> UC30
Kasir --> UC31
Kasir --> UC32
Kasir --> UC33
Kasir --> UC34
Kasir --> UC35
Kasir --> UC36

' ===== Barista =====
Barista --> UC01
Barista --> UC03
Barista --> UC40
Barista --> UC41
Barista --> UC42
Barista --> UC43
Barista --> UC44

' ===== Admin =====
Admin --> UC01
Admin --> UC03
Admin --> UC50
Admin --> UC51
Admin --> UC52
Admin --> UC53
Admin --> UC54
Admin --> UC55
Admin --> UC56
Admin --> UC57
Admin --> UC58
Admin --> UC59
Admin --> UC60

' ===== Include =====
UC15 ..> UC13 : <<include>>
UC15 ..> UC14 : <<include>>
UC18 ..> UC15 : <<include>>
UC30 ..> UC32 : <<include>>
UC30 ..> UC33 : <<include>>
UC32 ..> UC34 : <<include>>
UC42 ..> UC32 : <<include>>
UC60 ..> UC35 : <<include>>
@enduml
```

---

## 2. Activity Diagram per Peran

### 2.1 Activity — Login & Otorisasi (semua peran)

```plantuml
@startuml
title Activity — Login & Otorisasi

start
:Buka halaman /;
if (Sudah login?) then (ya)
else (belum)
  :Tampilkan form login;
  :Input email atau No. HP + kata sandi;
  if (Validasi input?) then (ya)
  else (tidak)
    :Tampilkan pesan validasi;
    stop
  endif
  if (Autentikasi berhasil?) then (ya)
  else (tidak)
    :Tampilkan pesan email atau No. HP salah;
    stop
  endif
endif

if (status_aktif bernilai true?) then (ya)
else (tidak)
  :Logout paksa + pesan akun dinonaktifkan;
  stop
endif

:Regenerasi session;
if (Cek kolom peran) then (admin)
  :Buka /admin/dashboard;
elseif (kasir) then (kasir)
  :Buka /kasir/dashboard;
elseif (barista) then (barista)
  :Buka /barista/dashboard;
else (pelanggan)
  :Buka /pelanggan/dashboard;
endif
stop
@enduml
```

### 2.2 Activity — Pelanggan Memesan & Membayar

```plantuml
@startuml
title Activity — Pelanggan Memesan & Membayar

start
:Buka menu /pelanggan/menu;
:Filter kategori atau cari produk;
repeat
  :Pilih produk;
  if (Butuh kustomisasi?) then (ya)
    :Atur opsi, jumlah, catatan;
  else (tidak)
  endif
  :Tambah ke keranjang;
repeat while (Lanjut belanja?) is (ya) not (tidak)

:Buka keranjang;
if (Ubah jumlah atau hapus item?) then (ya)
  :Update / hapus item;
else (tidak)
endif

if (Pakai voucher?) then (ya)
  :Input kode promo;
  if (Voucher aktif & dalam periode?) then (ya)
    :Simpan kode di session;
  else (tidak)
    :Tampilkan error voucher;
    :Kembali ke keranjang;
    stop
  endif
else (tidak)
endif

if (Tukar poin?) then (ya)
  :Hitung poin terpakai;
else (tidak)
endif

:Buka checkout;
if (Tipe pesanan?) then (dine-in)
  :Pilih meja;
else (takeaway)
endif

:Isi nama & No. HP pelanggan;
if (No. HP akun terisi?) then (tidak)
  :Redirect ke profil untuk melengkapi No. HP;
  stop
else (ya)
endif

:OrderController::store;
:Kunci baris produk lockForUpdate;
if (Stok mencukupi?) then (tidak)
  :HTTP 422 stok tidak cukup;
  stop
else (ya)
endif

:Hitung subtotal + diskon promo + poin + biaya layanan;
:INSERT pesanan status baru;
:INSERT detail_pesanan;
:UPDATE stok produk;
:INSERT transaksi status menunggu;
:Sinkron status meja;
:Kosongkan session keranjang, voucher, poin;
:Buka halaman status pesanan;

if (Metode bayar?) then (tunai)
  :Kasir tandai lunas + hitung kembalian;
else (QRIS / VA)
  :Kasir buat charge Midtrans;
endif

if (Status pembayaran?) then (lunas)
  :Poin member bertambah 1 per Rp 10.000;
elseif (gagal) then (gagal)
  :Minta metode pembayaran lain;
  stop
else (menunggu)
  repeat
    :Polling status / tunggu webhook;
  repeat while (Masih menunggu?) is (ya) not (lunas)
  :Status menjadi lunas;
endif

:Barista memproses pesanan;
:Kasir / barista menandai selesai;
:Buka struk & riwayat pesanan;
stop
@enduml
```

### 2.3 Activity — Pelanggan / Tamu Scan QR Meja

```plantuml
@startuml
title Activity — Scan QR Meja

start
:Scan QR fisik meja;
:Buka /meja/{qr_token};
if (Token valid?) then (tidak)
  :Tampilkan 404;
  stop
else (ya)
endif

:Bersihkan sesi meja kedaluwarsa;

if (Status meja?) then (kosong)
  if (Sudah login sebagai pelanggan?) then (tidak)
    :Tampilkan tombol login;
    stop
  else (ya)
  endif
  if (No. HP di profil terisi?) then (tidak)
    :Minta lengkapi profil;
    stop
  else (ya)
  endif
  :Kunci baris meja lockForUpdate;
  if (Ada reservasi aktif?) then (milik sendiri)
    :Tandai meja terisi, pakai sesi lama;
  elseif (milik orang lain) then (orang lain)
    :Error meja sudah dipesan pelanggan lain;
    stop
  else (tidak ada)
    if (Ada pesanan aktif atau status bukan kosong?) then (ya)
      :Error meja sedang terpakai;
      stop
    else (tidak)
      :INSERT reservasi_meja kode booking 90 menit;
      :UPDATE meja.status = terisi;
    endif
  endif
  :Tampilkan kode booking;
  :Pelanggan memesan menu;

elseif (terisi) then (terisi)
  :Info meja sedang terpakai;
  :Minta kode booking;
  :Verifikasi kode booking;
  if (Cocok & sesi aktif?) then (ya)
    :Izinkan akses meja;
  else (tidak)
    :Error kode tidak cocok;
    stop
  endif

else (dipesan)
  :Info meja dipesan;
  :Minta kode booking;
  :Verifikasi kode booking;
  if (Cocok & sesi aktif?) then (ya)
    :Izinkan akses meja;
  else (tidak)
    :Error kode tidak cocok;
    stop
  endif
endif

if (Selesai memakai meja?) then (ya)
  :Lepas meja menjadi kosong;
else (tidak)
  :Meja kosong otomatis setelah tenggang 15 menit;
endif
stop
@enduml
```

### 2.4 Activity — Kasir (POS)

```plantuml
@startuml
title Activity — Kasir POS

start
:Login kasir;
:Buka /kasir/dashboard;
:Pilih meja pada papan status realtime;
repeat
  :Tambah produk ke tiket session;
repeat while (Tambah item lagi?) is (ya) not (tidak)

if (Ubah atau hapus item?) then (ya)
  :Update jumlah atau hapus item;
else (tidak)
endif

:Isi tipe pesanan, meja, nama & No. HP;
:Pilih metode cash, qris, atau transfer;
:Klik Charge;

if (Stok cukup & produk tersedia?) then (tidak)
  :Tampilkan error stok;
  stop
else (ya)
endif

:OrderController::store;
:INSERT pesanan, detail, transaksi menunggu;
:Kurangi stok & sinkron meja;
:Kosongkan session tiket;

if (Metode pembayaran?) then (cash)
  :Masukkan uang diterima;
  if (Uang >= tagihan?) then (tidak)
    :Error uang kurang;
    stop
  else (ya)
  endif
  :Hitung kembalian;
  :UPDATE transaksi menjadi lunas;
  :Poin member bertambah 1 per Rp 10.000;
else (qris / transfer)
  :Minta charge ke Midtrans Core API;
  if (Ada pembayaran pending?) then (ya)
    :Tampilkan ulang QR / VA lama;
  else (tidak)
    :Tampilkan QRIS / nomor VA;
  endif
  repeat
    :Tunggu webhook atau polling status;
  repeat while (Belum lunas?) is (ya) not (lunas / kedaluwarsa)
  if (Kedaluwarsa 15 menit?) then (ya)
    :Tandai gagal dan ulangi;
    stop
  else (tidak)
    :UPDATE transaksi menjadi lunas + poin;
  endif
endif

:Barista memproses pesanan di KDS;
:Update status diproses lalu selesai;
stop
@enduml
```

### 2.5 Activity — Barista (KDS)

```plantuml
@startuml
title Activity — Barista KDS

start
:Login barista;
:Buka /barista/dashboard;
repeat
  :Auto-refresh kartu pesanan;
  if (Mulai mengerjakan?) then (ya)
    :POST bump, sematkan kartu ke atas;
    :Ubah status pesanan menjadi diproses;
  else (tidak)
    :POST dismiss, sembunyikan dari papan;
  endif
repeat while (Masih ada antrean?) is (ya) not (selesai)

if (Semua item selesai?) then (ya)
  if (Pesanan sudah lunas?) then (tidak)
    :Error lunasi dulu di kasir;
    stop
  else (ya)
  endif
  if (Status saat ini?) then (baru)
    :Error harus diproses dulu;
    stop
  else (diproses)
    :Update status menjadi selesai;
    :Sinkron status meja;
  endif
else (belum)
  :Kembali ke antrean;
  stop
endif

if (Perlu ubah status meja?) then (ya)
  :Tandai meja kosong, terisi, atau dipesan;
  :UPDATE meja.status;
else (tidak)
endif
stop
@enduml
```

### 2.6 Activity — Admin

```plantuml
@startuml
title Activity — Admin Ops Console

start
:Login admin;
:Buka /admin/dashboard;
:Lihat ringkasan toko;

if (Pilih modul?) then (Pegawai)
  :CRUD akun kasir & barista;
elseif (Master Kategori) then (Kategori)
  :CRUD kategori;
elseif (Master Produk) then (Produk)
  :CRUD produk + upload gambar;
elseif (Master Meja) then (Meja)
  :CRUD meja & cetak lembar QR;
elseif (Master Promo) then (Promo)
  :CRUD promo + masa berlaku;
elseif (Inventori) then (Inventori)
  :CRUD bahan & reorder biji kopi;
elseif (CRM) then (CRM)
  :Kelola pelanggan & sesuaikan poin;
elseif (Penjualan) then (Laporan)
  :Lihat laporan penjualan;
elseif (Pengaturan) then (Settings)
  :Atur profil toko, biaya layanan, poin, metode bayar;
elseif (Midtrans) then (Midtrans)
  :Simpan server key & client key;
else (Pesanan)
  :Ubah status atau hapus pesanan;
endif

if (Validasi input?) then (tidak)
  :Tampilkan pesan validasi;
  stop
else (ya)
endif
:Simpan ke database atau JSON settings;
stop
@enduml
```

---

## 3. Flowchart per Peran

Flowchart di PlantUML digambar dengan sintaks activity (paling terbaca untuk alur
navigasi); bentuknya sama dengan versi Mermaid.

### 3.1 Flowchart Pelanggan

```plantuml
@startuml
title Flowchart — Pelanggan

start
:Buka aplikasi;
if (Punya akun?) then (belum)
  :Registrasi;
else (sudah)
endif
:Login;
:Dashboard pelanggan;
:Buka menu;
if (Scan QR meja?) then (ya)
  :Check-in meja;
else (tidak)
endif
:Pilih produk & kustom;
:Keranjang;
:Checkout;
:Pesanan dibuat;
:Bayar;
repeat
  :Lihat status pesanan;
repeat while (Belum selesai?) is (ya) not (selesai)
:Buka riwayat & poin;
stop
@enduml
```

### 3.2 Flowchart Kasir

```plantuml
@startuml
title Flowchart — Kasir

start
:Login kasir;
:POS dashboard;
if (Tipe transaksi?) then (pesanan baru)
  :Pilih meja;
  repeat
    :Tambah item ke tiket;
  repeat while (Belum charge?) is (ya) not (charge)
  :Pilih metode bayar;
  :Pesanan dibuat;
  if (Metode?) then (tunai)
    :Input uang & kembalian;
  else (qris / VA)
    :Tampilkan QR / nomor VA;
    repeat
      :Cek status pembayaran;
    repeat while (Belum terbayar?) is (ya) not (lunas)
  endif
  :Lunas & poin;
else (pesanan lama)
  :Ubah status atau lunasi;
endif
:Cetak struk;
if (Pesanan selesai dimasak?) then (ya)
  :Update status pesanan;
else (belum)
endif
stop
@enduml
```

### 3.3 Flowchart Barista

```plantuml
@startuml
title Flowchart — Barista

start
:Login barista;
:KDS dashboard;
repeat
  :Lihat antrean;
  if (Aksi?) then (bump)
    :Sematkan kartu;
  elseif (dismiss) then (dismiss)
    :Sembunyikan kartu;
  elseif (proses) then (proses)
    :Status diproses;
  elseif (selesai) then (selesai)
    if (Sudah lunas & diproses?) then (tidak)
      :Pesan error;
    else (ya)
      :Status selesai;
      :Sinkron meja;
    endif
  else (ubah meja)
    :Set kosong, terisi, atau dipesan;
  endif
repeat while (Masih ada antrean?) is (ya) not (selesai)
stop
@enduml
```

### 3.4 Flowchart Admin

```plantuml
@startuml
title Flowchart — Admin

start
:Login admin;
:Dashboard ringkasan;
if (Pilih menu?) then (Master Data)
  :Kategori, Produk, Meja+QR, Promo;
elseif (Kepegawaian) then (Kepegawaian)
  :Akun kasir & barista;
elseif (Inventori) then (Inventori)
  :Stok bahan & reorder;
elseif (CRM) then (CRM)
  :Pelanggan & poin;
elseif (Penjualan) then (Penjualan)
  :Laporan penjualan;
elseif (Pengaturan) then (Pengaturan)
  :Toko, biaya layanan, Midtrans;
else (Pesanan)
  :Ubah status & hapus pesanan;
endif
:Simpan, ubah, atau hapus data;
stop
@enduml
```

### 3.5 Flowchart Tamu (tanpa akun)

```plantuml
@startuml
title Flowchart — Tamu tanpa akun

start
:Scan QR meja;
if (Status meja?) then (kosong)
  :Diminta login atau registrasi;
  stop
elseif (terisi) then (terisi)
  :Masukkan kode booking;
else (dipesan)
  :Masukkan kode booking;
endif
if (Kode cocok?) then (tidak)
  :Ulangi input kode;
  stop
else (ya)
  :Lihat menu publik & info meja;
endif
stop
@enduml
```

---

## 4. Sequence Diagram

### 4.1 Sequence — Login & RBAC

```plantuml
@startuml
title Sequence — Login & RBAC
autonumber

actor "Pengguna" as U
participant "Browser (Blade)" as B
participant "LoginController" as L
participant "Pengguna (Model)" as M
participant "Session" as S
participant "Middleware role" as MW

U -> B : Isi email / No. HP + kata sandi
B -> L : POST /login dengan throttle 10 per menit
L -> L : Validasi input
L -> M : Auth::attempt field, password
M --> L : Berhasil / gagal

alt Kredensial salah
  L --> B : ValidationException
  B --> U : Tampilkan pesan error
else Kredensial benar
  L -> M : Cek status_aktif
  alt Akun nonaktif
    L -> S : invalidate + regenerate token
    L --> B : Pesan akun dinonaktifkan
  else Akun aktif
    L -> S : regenerate session
    L --> B : Redirect sesuai peran
    B -> MW : GET dashboard peran
    MW -> MW : Bandingkan user.peran
    alt Peran cocok
      MW --> B : 200 halaman dashboard
    else Peran tidak cocok
      MW --> B : 403 Forbidden
    end
  end
end
@enduml
```

### 4.2 Sequence — Checkout Pelanggan

```plantuml
@startuml
title Sequence — Checkout Pelanggan
autonumber

actor "Pelanggan" as P
participant "Browser" as B
participant "CartController" as C
participant "OrderController" as O
database "Database" as DB
participant "Store Settings JSON" as SET

P -> B : Klik "Buat Pesanan" di checkout
B -> C : POST /pelanggan/checkout
C -> C : Ambil keranjang dari session
C -> C : Validasi tipe pesanan & meja
C -> DB : Cari atau buat pelanggan by No. HP
C -> DB : Validasi ulang voucher promo
C -> O : store inner request + user resolver

O -> DB : BEGIN TRANSACTION
O -> DB : SELECT produk FOR UPDATE
alt Stok tidak cukup
  O --> C : abort 422
  C --> B : Redirect checkout + pesan error
else Stok cukup
  O -> DB : INSERT pesanan status baru
  loop Setiap item
    O -> DB : INSERT detail_pesanan snapshot harga
  end
  O -> DB : UPDATE produk.stok = stok - jumlah
  O -> O : Hitung subtotal
  opt Voucher valid
    O -> O : Hitung diskon persen / nominal
  end
  opt Tukar poin
    O -> DB : SELECT pelanggan FOR UPDATE
    O -> DB : UPDATE poin_loyalitas
  end
  O -> SET : Baca biaya_layanan
  O -> DB : UPDATE pesanan total, diskon, biaya
  O -> DB : INSERT transaksi status menunggu
  O -> DB : Sinkronisasi status meja
  O -> DB : COMMIT
  O --> C : 201 + data pesanan
  C -> C : Hapus session keranjang, voucher, poin
  C --> B : Redirect halaman status pesanan
  B --> P : Halaman status pesanan
end
@enduml
```

### 4.3 Sequence — Pembayaran QRIS Midtrans & Webhook

```plantuml
@startuml
title Sequence — Pembayaran QRIS Midtrans
autonumber

actor "Kasir" as K
participant "Browser Kasir" as B
participant "MidtransPaymentController" as PC
participant "MidtransService" as MS
participant "Midtrans Core API" as MT
database "Database" as DB
participant "MidtransNotificationController" as WH
actor "Pelanggan" as P

K -> B : Pilih metode QRIS pada pesanan
B -> PC : POST /pesanan/id/midtrans/qris
PC -> PC : Cek peran kasir & status pesanan
PC -> MS : isConfigured

alt Belum dikonfigurasi
  MS --> PC : false
  PC --> B : 422 Midtrans belum dikonfigurasi
else Terkonfigurasi
  PC -> MS : pendingPayment pesanan
  alt Ada pembayaran pending
    MS --> PC : data pembayaran lama
    PC --> B : reuse true + QR lama
  else Belum ada
    PC -> MS : chargeQris pesanan
    MS -> MT : POST /v2/charge qris expiry 15 menit
    MT --> MS : transaction_id, qr_string, expiry
    MS -> DB : INSERT / UPDATE transaksi
    MS --> PC : data QR
    PC --> B : reuse false + QR
  end
  B --> K : Tampilkan QRIS ke pelanggan
  P -> MT : Scan & bayar QRIS
  MT -> WH : POST /api/midtrans/callback
  WH -> WH : Verifikasi signature SHA512
  WH -> MS : applyStatus pesanan, payload
  MS -> DB : UPDATE transaksi menjadi lunas
  MS -> DB : UPDATE pelanggan poin
  WH --> MT : 200 OK
  loop Polling tiap beberapa detik
    B -> PC : GET /pesanan/id/midtrans/status
    PC -> MS : remoteStatus bila masih menunggu
    MS -> MT : GET /v2/order_id/status
    MT --> MS : transaction_status
    MS -> DB : Sinkronkan status
    PC --> B : payment_status
  end
  B --> K : Tampilkan LUNAS + tombol cetak struk
end
@enduml
```

### 4.4 Sequence — Check-in Meja via QR

```plantuml
@startuml
title Sequence — Check-in Meja via QR
autonumber

actor "Pelanggan / Tamu" as P
participant "Browser" as B
participant "MejaQrController" as Q
participant "MejaQrService" as S
database "Database" as DB

P -> B : Scan QR fisik meja
B -> Q : GET /meja/qr_token
Q -> S : statusMeja meja
S -> DB : Reservasi bersihkanKedaluwarsa
S -> DB : SELECT reservasi aktif + pesanan aktif
S --> Q : status kosong / terisi / dipesan
Q --> B : Halaman scan meja

alt Meja kosong & pelanggan login
  P -> B : Klik check-in meja ini
  B -> Q : POST /pelanggan/meja/qr_token/checkin
  Q -> S : klaim meja, pengguna
  S -> DB : BEGIN
  S -> DB : SELECT meja FOR UPDATE
  alt Meja sudah dipesan orang lain
    S --> Q : ValidationException
    Q --> B : Pesan error
  else Masih kosong
    S -> DB : INSERT reservasi 90 menit
    S -> DB : UPDATE meja.status terisi
    S -> DB : COMMIT
    Q --> B : Tampilkan kode booking
  end
else Meja terisi atau dipesan
  alt Sudah login & belum punya akses
    P -> B : Masukkan kode booking
    B -> Q : POST /meja/qr_token/verifikasi
    Q -> S : verifikasiKode meja, kode
    S -> DB : SELECT reservasi aktif dengan kode
    S --> Q : valid atau error
    Q --> B : Izinkan akses / pesan error
  else Tamu tanpa akun
    Q --> B : Minta kode booking tanpa login
  end
end

note over P, DB
  Scheduler tiap menit menjalankan
  meja:bersihkan untuk sesi kedaluwarsa
end note
@enduml
```

### 4.5 Sequence — Kasir Melunasi Tunai

```plantuml
@startuml
title Sequence — Kasir Melunasi Tunai
autonumber

actor "Kasir" as K
participant "Browser" as B
participant "DashboardController" as D
database "Database" as DB

K -> B : Pilih pesanan, masukkan uang diterima
B -> D : PATCH /pesanan/id/lunas
D -> D : abort_unless user.isKasir

D -> DB : BEGIN
D -> DB : SELECT transaksi FOR UPDATE
alt Sudah lunas
  D --> B : 422 pesanan sudah lunas
else Belum lunas
  D -> DB : Ambil metode pembayaran default CASH
  alt Metode dengan kembalian
    D -> D : Cek uang diterima >= tagihan
    D -> D : Hitung kembalian
  else Transfer / QRIS manual
    D -> D : Nominal harus pas + wajib referensi
  end
  D -> DB : UPDATE transaksi lunas, total_bayar, kembalian
  D -> DB : UPDATE pelanggan poin_loyalitas
  D -> DB : COMMIT
  D --> B : Redirect + pesan lunas
end
B --> K : Status lunas & tombol cetak struk
@enduml
```

---

## 5. ERD

```plantuml
@startuml
title ERD — Tejas Coffee POS
hide circle
skinparam linetype ortho

entity "pengguna" as pengguna {
  * id_pengguna : bigint PK
  --
  nama_lengkap : varchar
  email : varchar UK
  kata_sandi : varchar
  nomor_telepon : varchar
  peran : enum
  status_aktif : boolean
  dibuat_pada : timestamp
}

entity "pelanggan" as pelanggan {
  * id_pelanggan : bigint PK
  --
  nama_pelanggan : varchar
  nomor_telepon : varchar
  poin_loyalitas : int
  terdaftar_pada : timestamp
}

entity "kategori" as kategori {
  * id_kategori : bigint PK
  --
  nama_kategori : varchar
  deskripsi : varchar
  status_aktif : boolean
}

entity "produk" as produk {
  * id_produk : bigint PK
  --
  id_kategori : bigint FK
  kode_produk : varchar UK
  nama_produk : varchar
  deskripsi : text
  harga : decimal
  stok : int
  status_tersedia : boolean
  url_gambar : varchar
}

entity "meja" as meja {
  * id_meja : bigint PK
  --
  nomor_meja : varchar UK
  kapasitas : int
  status : enum
  qr_token : varchar UK
}

entity "reservasi_meja" as reservasi_meja {
  * id_reservasi : bigint PK
  --
  id_meja : bigint FK
  id_pelanggan : bigint FK
  kode_booking : varchar UK
  status : enum
  dicek_in_pada : datetime
  berakhir_pada : datetime
  dibuat_pada : timestamp
}

entity "promo" as promo {
  * id_promo : bigint PK
  --
  kode_promo : varchar UK
  tipe_diskon : enum
  nilai_diskon : decimal
  berlaku_mulai : datetime
  berlaku_sampai : datetime
  status_aktif : boolean
}

entity "pesanan" as pesanan {
  * id_pesanan : bigint PK
  --
  nomor_pesanan : varchar UK
  id_pengguna : bigint FK
  id_pelanggan : bigint FK
  id_meja : bigint FK
  id_promo : bigint FK
  tipe_pesanan : enum
  status_pesanan : enum
  total_harga : decimal
  diskon : decimal
  poin_dipakai : int
  biaya_layanan : decimal
  catatan : text
  waktu_pesan : datetime
}

entity "detail_pesanan" as detail_pesanan {
  * id_detail : bigint PK
  --
  id_pesanan : bigint FK
  id_produk : bigint FK
  jumlah : int
  harga_satuan : decimal
  subtotal : decimal
  catatan_item : text
}

entity "metode_pembayaran" as metode_pembayaran {
  * id_metode : bigint PK
  --
  kode_metode : varchar UK
  nama_metode : varchar
  ada_kembalian : boolean
}

entity "transaksi" as transaksi {
  * id_transaksi : bigint PK
  --
  id_pesanan : bigint FK
  id_metode : bigint FK
  subtotal : decimal
  total_bayar : decimal
  kembalian : decimal
  bank_atau_ewallet : varchar
  nomor_referensi : varchar
  status_pembayaran : enum
  waktu_pembayaran : datetime
  midtrans_order_id : varchar UK
  midtrans_payment_type : varchar
  midtrans_payload : json
  midtrans_expires_at : datetime
}

entity "stok_biji" as stok_biji {
  * id_stok_biji : bigint PK
  --
  nama_biji : varchar
  kategori : varchar
  satuan : varchar
  deskripsi : varchar
  sisa_kg : decimal
  threshold_kg : decimal
  safety_cap_kg : decimal
  laju_konsumsi_kg_per_jam : decimal
  created_at : timestamp
  updated_at : timestamp
}

entity "log_reorder" as log_reorder {
  * id_log_reorder : bigint PK
  --
  id_stok_biji : bigint FK
  destination : varchar
  order_quantity_kg : decimal
  status : enum
  response_reference : varchar
  waktu_dispatch : datetime
}

entity "midtrans_settings" as midtrans_settings {
  * id : bigint PK
  --
  server_key : varchar
  client_key : varchar
  is_production : boolean
  webhook_verified : varchar
  created_at : timestamp
  updated_at : timestamp
}

pengguna ||--o{ pesanan : menangani
pelanggan o|--o{ pesanan : memesan
meja o|--o{ pesanan : dipakai
promo o|--o{ pesanan : diskon
pesanan ||--|{ detail_pesanan : berisi
produk ||--o{ detail_pesanan : dipesan
kategori ||--o{ produk : mengelompokkan
pesanan ||--|| transaksi : dibayar
metode_pembayaran ||--o{ transaksi : metode
meja ||--o{ reservasi_meja : sesi
pelanggan o|--o{ reservasi_meja : checkin
stok_biji ||--o{ log_reorder : reorder
@enduml
```

---

## 6. Skema Database

### 6.1 Peta relasi

```plantuml
@startuml
title Peta Relasi Tabel
skinparam componentStyle rectangle
skinparam shadowing false

[pengguna] --> [pesanan]
[pelanggan] --> [pesanan]
[meja] --> [pesanan]
[promo] --> [pesanan]
[pesanan] --> [detail_pesanan]
[produk] --> [detail_pesanan]
[kategori] --> [produk]
[pesanan] --> [transaksi]
[metode_pembayaran] --> [transaksi]
[meja] --> [reservasi_meja]
[pelanggan] --> [reservasi_meja]
[stok_biji] --> [log_reorder]
@enduml
```

### 6.2 Kamus tabel

Kamus kolom lengkap (tipe, NOT NULL, default, foreign key, index) sama persis dengan
versi Mermaid di [`DIAGRAM_SISTEM.md` §6.2](DIAGRAM_SISTEM.md#62-kamus-tabel), sehingga
tidak diduplikasi di sini agar tidak terjadi perbedaan data antar dokumen.

### 6.3 Skema lengkap (dengan tipe kolom)

```plantuml
@startuml
title Skema Database — kolom & tipe
hide circle
skinparam linetype ortho

entity "pengguna" as pengguna {
  * id_pengguna : bigint unsigned PK
  --
  nama_lengkap : varchar 255
  email : varchar 255 UK
  kata_sandi : varchar 255
  nomor_telepon : varchar 20
  peran : enum admin, kasir, barista, pelanggan
  status_aktif : boolean default true
  dibuat_pada : timestamp
}

entity "pelanggan" as pelanggan {
  * id_pelanggan : bigint unsigned PK
  --
  nama_pelanggan : varchar 255
  nomor_telepon : varchar 20
  poin_loyalitas : int unsigned default 0
  terdaftar_pada : timestamp
}

entity "kategori" as kategori {
  * id_kategori : bigint unsigned PK
  --
  nama_kategori : varchar 255
  deskripsi : varchar 255
  status_aktif : boolean default true
}

entity "produk" as produk {
  * id_produk : bigint unsigned PK
  --
  id_kategori : bigint unsigned FK
  kode_produk : varchar 255 UK
  nama_produk : varchar 255
  deskripsi : text
  harga : decimal 10 2
  stok : int unsigned default 0
  status_tersedia : boolean default true
  url_gambar : varchar 255
}

entity "meja" as meja {
  * id_meja : bigint unsigned PK
  --
  nomor_meja : varchar 255 UK
  kapasitas : int unsigned
  status : enum kosong, terisi, dipesan
  qr_token : varchar 64 UK
}

entity "reservasi_meja" as reservasi_meja {
  * id_reservasi : bigint unsigned PK
  --
  id_meja : bigint unsigned FK
  id_pelanggan : bigint unsigned FK
  kode_booking : varchar 12 UK
  status : enum aktif, selesai, kedaluwarsa, dibatalkan
  dicek_in_pada : datetime
  berakhir_pada : datetime
  dibuat_pada : timestamp
}

entity "promo" as promo {
  * id_promo : bigint unsigned PK
  --
  kode_promo : varchar 255 UK
  tipe_diskon : enum persen, nominal
  nilai_diskon : decimal 10 2
  berlaku_mulai : datetime
  berlaku_sampai : datetime
  status_aktif : boolean default true
}

entity "pesanan" as pesanan {
  * id_pesanan : bigint unsigned PK
  --
  nomor_pesanan : varchar 255 UK
  id_pengguna : bigint unsigned FK
  id_pelanggan : bigint unsigned FK
  id_meja : bigint unsigned FK
  id_promo : bigint unsigned FK
  tipe_pesanan : enum dine-in, takeaway, delivery
  status_pesanan : enum baru, diproses, selesai, dibatalkan
  total_harga : decimal 12 2
  diskon : decimal 12 2 default 0
  poin_dipakai : int unsigned default 0
  biaya_layanan : decimal 12 2 default 0
  catatan : text
  waktu_pesan : datetime
}

entity "detail_pesanan" as detail_pesanan {
  * id_detail : bigint unsigned PK
  --
  id_pesanan : bigint unsigned FK
  id_produk : bigint unsigned FK
  jumlah : int unsigned default 1
  harga_satuan : decimal 10 2
  subtotal : decimal 12 2
  catatan_item : text
}

entity "metode_pembayaran" as metode_pembayaran {
  * id_metode : bigint unsigned PK
  --
  kode_metode : varchar 255 UK
  nama_metode : varchar 255
  ada_kembalian : boolean default false
}

entity "transaksi" as transaksi {
  * id_transaksi : bigint unsigned PK
  --
  id_pesanan : bigint unsigned FK UK
  id_metode : bigint unsigned FK
  subtotal : decimal 12 2
  total_bayar : decimal 12 2
  kembalian : decimal 12 2 default 0
  bank_atau_ewallet : varchar 255
  nomor_referensi : varchar 255
  status_pembayaran : enum menunggu, lunas, gagal
  waktu_pembayaran : datetime
  midtrans_order_id : varchar 80 UK
  midtrans_payment_type : varchar 40
  midtrans_payload : json
  midtrans_expires_at : datetime
}

entity "stok_biji" as stok_biji {
  * id_stok_biji : bigint unsigned PK
  --
  nama_biji : varchar 255
  kategori : varchar 255 default Biji Kopi
  satuan : varchar 20 default kg
  deskripsi : varchar 255
  sisa_kg : decimal 8 2
  threshold_kg : decimal 8 2
  safety_cap_kg : decimal 8 2
  laju_konsumsi_kg_per_jam : decimal 8 4
  created_at : timestamp
  updated_at : timestamp
}

entity "log_reorder" as log_reorder {
  * id_log_reorder : bigint unsigned PK
  --
  id_stok_biji : bigint unsigned FK
  destination : varchar 255
  order_quantity_kg : decimal 8 2
  status : enum terkirim, gagal
  response_reference : varchar 255
  waktu_dispatch : datetime
}

entity "midtrans_settings" as midtrans_settings {
  * id : bigint unsigned PK
  --
  server_key : varchar 255
  client_key : varchar 255
  is_production : boolean default false
  webhook_verified : varchar 255 default pending
  created_at : timestamp
  updated_at : timestamp
}

pengguna ||--o{ pesanan
pelanggan o|--o{ pesanan
meja o|--o{ pesanan
promo o|--o{ pesanan
pesanan ||--|{ detail_pesanan
produk ||--o{ detail_pesanan
kategori ||--o{ produk
pesanan ||--|| transaksi
metode_pembayaran ||--o{ transaksi
meja ||--o{ reservasi_meja
pelanggan o|--o{ reservasi_meja
stok_biji ||--o{ log_reorder
@enduml
```

---

## 7. Alur Transaksi

### 7.1 State machine status pesanan

```plantuml
@startuml
title State — status_pesanan

[*] --> baru : Pesanan dibuat oleh pelanggan atau kasir
baru --> diproses : Barista / kasir mulai mengerjakan
baru --> dibatalkan : Dibatalkan, stok dikembalikan
diproses --> selesai : Serah terima
diproses --> dibatalkan : Dibatalkan, stok dikembalikan
selesai --> [*]
dibatalkan --> [*]

note right of baru
  Mengikuti Pesanan::TRANSISI_STATUS
  baru tidak boleh langsung selesai
  diproses tidak boleh mundur ke baru
end note
@enduml
```

### 7.2 State machine status pembayaran

```plantuml
@startuml
title State — status_pembayaran

[*] --> menunggu : Pesanan dibuat, transaksi otomatis
menunggu --> lunas : Kasir tandai lunas atau webhook Midtrans
menunggu --> gagal : QRIS atau VA kedaluwarsa
gagal --> menunggu : Kasir membuat charge ulang
lunas --> [*]

note right of lunas
  Saat lunas poin member bertambah
  floor total_harga / 10000
  Status pesanan tidak otomatis berubah
end note
@enduml
```

### 7.3 Alur uang, stok, dan poin

```plantuml
@startuml
title Alur — uang, stok, dan poin

start
:Pelanggan atau kasir membuat pesanan;
:OrderController::store;
:Kunci produk dengan lockForUpdate;
if (Stok cukup?) then (tidak)
  :HTTP 422, pesanan batal;
  stop
else (ya)
endif

:Kurangi stok;
:Simpan pesanan + detail;
:Hitung subtotal - voucher - poin + biaya layanan;
:Transaksi menunggu dengan total_bayar 0;

if (Metode bayar?) then (tunai)
  :Kasir input uang diterima;
  if (Uang >= tagihan?) then (tidak)
    :Tolak, minta nominal cukup;
    stop
  else (ya)
    :total_bayar = uang, kembalian = selisih;
  endif
else (QRIS / VA)
  :Midtrans charge dengan masa berlaku 15 menit;
  if (Status gateway?) then (settlement)
    :total_bayar = gross_amount;
  elseif (expire atau deny) then (gagal)
    :status gagal, boleh charge ulang;
    stop
  else (pending)
    :Tunggu webhook atau polling;
    stop
  endif
endif

:status_pembayaran = lunas;
:Poin member bertambah 1 per Rp 10.000;
:Barista memproses di KDS;
:Pesanan selesai;
:Struk 80 mm dicetak;
stop
@enduml
```

### 7.4 Rincian pembentukan total

```plantuml
@startuml
title Perhitungan Total Pesanan

start
:Subtotal = jumlah seluruh harga_satuan x jumlah;
if (Pakai voucher?) then (persen)
  :diskon = subtotal x persen;
elseif (nominal) then (nominal)
  :diskon = min nilai nominal, subtotal;
else (tidak)
  :diskon voucher = 0;
endif

if (Pakai poin?) then (ya)
  :poin_dipakai = min saldo, floor total / 10;
  :diskon poin = poin x Rp 10;
else (tidak)
  :diskon poin = 0;
endif

:biaya_layanan dari Store Settings;
:total_harga = subtotal - diskon + biaya_layanan;
stop
@enduml
```

---

## Catatan

- Angka mengikuti implementasi saat ini:
  `Pesanan::RUPIAH_PER_POIN` = 10.000,
  `ReservasiMeja::DURASI_MENIT` = 90,
  `ReservasiMeja::TENGGANG_MENIT` = 15,
  `ReservasiMeja::MAKS_SESI_MENIT` = 180,
  `MidtransService::EXPIRY_MINUTES` = 15.
- Diagram alur (activity/flowchart) memakai sintaks *activity beta* PlantUML, bukan
  bentuk UML activity formal dengan swimlane; strukturnya sama dengan versi Mermaid.
