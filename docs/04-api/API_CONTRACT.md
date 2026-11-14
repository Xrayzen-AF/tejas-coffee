# Kontrak Data & Referensi Aksi Controller

Dokumen pendamping `openapi.json`. Untuk aplikasi monolitik Blade, tabel ini
berfungsi sebagai *Data Dictionary & Controller Action Reference*: memetakan
rute web, method controller, input, validasi, dan keluaran.

## 1. Ringkasan Rute Web per Peran

| Prefix | Middleware | Peran | Controller |
| :--- | :--- | :--- | :--- |
| `/` , `/login`, `/register` | `guest` | publik | `Auth\LoginController`, `Auth\RegisterController` |
| `/meja/{qr_token}` | `throttle:6,1` | publik | `MejaQrController` |
| `/admin/*` | `auth`, `role:admin` | admin | `Admin\*` |
| `/kasir/*` | `auth`, `role:kasir` | kasir | `DashboardController` |
| `/barista/*` | `auth`, `role:barista` | barista | `DashboardController` |
| `/pelanggan/lokasi*` | `auth`, `role:pelanggan` | pelanggan | `LokasiController` |
| `/pelanggan/*` | `auth`, `role:pelanggan`, `geofence` | pelanggan | `DashboardController`, `CartController`, `MejaQrController`, `ProfilController` |
| `/struk/{pesanan}` | `auth` + policy | pemilik / kasir / admin | `StrukController` |
| `/pesanan/*` | `auth` | kasir, barista, pelanggan pemilik | `DashboardController`, `MidtransPaymentController` |

## 2. Aksi Controller Penting

| Method | Rute | Controller@aksi | Input utama | Keluaran |
| :--- | :--- | :--- | :--- | :--- |
| GET | `/admin/dashboard` | `Admin\OverviewController@index` | — | View ringkasan toko |
| GET | `/admin/sales` | `Admin\SalesController@index` | — | View omzet 7 hari, metode bayar, margin |
| GET | `/admin/sales/cetak` | `Admin\SalesController@cetak` | `dari`, `sampai`, `tipe`, `metode` | View A4 siap cetak |
| GET | `/admin/sales/cetak/csv` | `Admin\SalesController@exportCsv` | `dari`, `sampai`, `tipe`, `metode` | Unduhan CSV (BOM UTF-8) |
| GET/POST/PUT/DELETE | `/admin/employees` | `Admin\AdminEmployeeController` | nama, email, nomor HP, peran (kasir/barista), aktif | Redirect + flash |
| GET | `/admin/inventory` | `Admin\InventoryController@index` | — | View stok biji + alert |
| POST | `/admin/inventory/{id}/reorder` | `Admin\InventoryController@reorder` | kuantitas kg | Redirect + log reorder |
| GET | `/admin/staff` | `Admin\StaffController@index` | — | View beban kerja staf |
| PATCH | `/admin/staff/{id}/toggle` | `Admin\StaffController@toggle` | — | Redirect (aktif/nonaktif) |
| GET | `/admin/customers` | `Admin\CustomerController@index` | — | View CRM + poin |
| PATCH | `/admin/customers/{id}/poin` | `Admin\CustomerController@adjustPoin` | `poin`, `aksi` | Redirect |
| GET/PUT | `/admin/settings` | `Admin\SettingController@index/update` | profil toko, biaya layanan, poin | Redirect |
| PUT | `/admin/settings/midtrans` | `Admin\SettingController@updateMidtrans` | `server_key`, `client_key`, `is_production` | Redirect |
| PUT | `/admin/settings/geofence` | `Admin\SettingController@updateGeofence` | `cafe_latitude`, `cafe_longitude`, `radius_meter`, `geofence_enabled` | Redirect |
| POST | `/admin/orders/{id}/status` | `Admin\OrderManagementController@updateStatus` | `status_pesanan` | Redirect |
| GET | `/kasir/dashboard` | `DashboardController@kasir` | — | View POS |
| POST | `/kasir/ticket/tambah/{produk}` | `DashboardController@ticketTambah` | `jumlah`, opsi kustom | Redirect (session tiket) |
| POST | `/kasir/ticket/buat` | `DashboardController@ticketBuat` | `tipe_pesanan`, `id_meja`, `id_metode` | Redirect + nomor pesanan |
| GET | `/kasir/meja/status` | `DashboardController@mejaStatus` | — | JSON status meja |
| GET | `/barista/dashboard` | `DashboardController@barista` | — | View KDS |
| POST | `/barista/bump/{pesanan}` | `DashboardController@kdsPin` | — | Redirect |
| POST | `/barista/dismiss/{pesanan}` | `DashboardController@kdsDismiss` | — | Redirect |
| GET | `/pelanggan/menu` | `CartController@menu` | `kategori`, `q` | View katalog |
| POST | `/pelanggan/keranjang/tambah/{produk}` | `CartController@tambah` | `jumlah`, opsi, `catatan_item` | Redirect ke keranjang |
| POST | `/pelanggan/keranjang/voucher` | `CartController@voucher` | `kode_promo` | Redirect + diskon |
| POST | `/pelanggan/keranjang/poin` | `CartController@poinToggle` | — | Redirect |
| POST | `/pelanggan/checkout` | `CartController@simpanPesanan` | `tipe_pesanan`, `id_meja`, `catatan`, `metode` | Redirect ke status pesanan |
| GET | `/pelanggan/pesanan/{nomor}/status-live` | `CartController@statusLive` | — | JSON progres pesanan |
| PATCH | `/pelanggan/pesanan/{id}/batal` | `CartController@batalkan` | — | JSON/redirect |
| POST | `/pelanggan/lokasi` | `LokasiController@verify` | `latitude`, `longitude`, `accuracy` | JSON status geofence |
| POST | `/pelanggan/lokasi/cek` | `LokasiController@cek` | `latitude`, `longitude` | JSON watchdog |
| GET | `/struk/{pesanan}` | `StrukController@show` | — | View struk 80 mm (policy) |
| PATCH | `/pesanan/{id}/lunas` | `DashboardController@tandaiLunas` | `metode`, `dibayar` | Redirect/JSON |
| POST | `/pesanan/{id}/midtrans/qris` | `MidtransPaymentController@qris` | — | JSON QR string |
| POST | `/pesanan/{id}/midtrans/transfer` | `MidtransPaymentController@transfer` | `bank` | JSON nomor VA |
| GET | `/pesanan/{id}/midtrans/status` | `MidtransPaymentController@status` | — | JSON status pembayaran |

## 3. Data Dictionary — Tabel Inti

| Tabel | Kolom | Tipe | Keterangan |
| :--- | :--- | :--- | :--- |
| `pengguna` | `id_pengguna` | bigint PK | Auto increment |
| | `email`, `nomor_telepon` | varchar unique | Keduanya bisa dipakai login |
| | `peran` | enum | admin / kasir / barista / pelanggan |
| | `aktif` | boolean | Nonaktif = tidak bisa login |
| `pelanggan` | `poin_loyalitas` | int | 1 poin per Rp 10.000 belanja bersih |
| `produk` | `harga` | decimal(10,2) | Bukan `int` — harga kopi punya pecahan rupiah |
| | `stok` | unsigned int | Dikurangi atomik saat checkout |
| `meja` | `qr_token` | varchar(64) unique | Token acak 40 karakter, bukan ID |
| `reservasi_meja` | `berakhir_pada` | datetime | Sesi 90 menit, maks 180 menit |
| `pesanan` | `total_harga` | decimal(12,2) | Total bersih termasuk biaya layanan |
| | `diskon`, `biaya_layanan`, `poin_dipakai` | decimal/int | Rincian potongan |
| `transaksi` | `subtotal` | decimal(12,2) | Nilai tagihan (dasar hitung omzet) |
| | `total_bayar`, `kembalian` | decimal(12,2) | Uang fisik & kembalian |
| | `status_pembayaran` | enum | menunggu / lunas / gagal |
| `stok_biji` | `threshold_kg` | decimal(8,2) | Ambang alert inventori |

## 4. Aturan Validasi Kunci

| Field | Aturan | Alasan |
| :--- | :--- | :--- |
| `items[].jumlah` | integer, 1–99 | Mencegah pesanan tidak wajar |
| `items[].harga_satuan` | numeric ≥ harga dasar produk | Anti order Rp 1 dari client |
| `id_meja` | wajib bila `tipe_pesanan=dine-in` | Dine-in harus punya meja |
| `dari`, `sampai` | regex `Y-m-d` + `checkdate`, maks 366 hari | Mencegah query raksasa & tanggal palsu |
| `cafe_latitude` / `cafe_longitude` | `-90..90` / `-180..180`, wajib bila geofence aktif | Titik cafe valid |
| `order_quantity_kg` | numeric 0.1–1000 | Batas reorder wajar |
| Login/registrasi | `throttle:10,1` | Anti brute force |
