### 1. S4-01: FINAL BUG FIXING & GIT TAG RELEASE CANDIDATE

#### A. Log Penyelesaian Bug Terakhir (Sprint 4 Closure)

Sebelum melakukan _freeze_, seluruh isu minor ditutup dengan commit Git resmi:

| **Nomor Tiket** | **Modul Terkait** | **Deskripsi Masalah** | **Solusi Penanganan** | **Commit Hash** | **Status** |
| --- | --- | --- | --- | --- | --- |
| **BUG-06** | Keranjang & Stok | Keranjang masih dapat menambah item melebihi stok gabungan antar varian produk yang sama. | Menghitung total stok lintas varian sebelum item dimasukkan ke draf keranjang. | 37d8896 | **CLOSED** |
| **BUG-07** | Autentikasi & RBAC | Setelah login, pengguna diarahkan ke dashboard yang tidak sesuai perannya saat membuka URL peran lain. | Redirect dipusatkan pada peran pengguna (admin, kasir, barista, pelanggan). | ed48adc | **CLOSED** |
| **BUG-08** | Ops Console (Admin) | Tabel daftar pesanan terpotong dan tidak terbaca pada layar ponsel. | Tabel diubah menjadi kartu pada breakpoint mobile tanpa mengubah data. | 40b0099 | **CLOSED** |
| **BUG-09** | Master Produk | Unggah ulang gambar produk menimpa berkas sebelumnya karena nama berkas bertabrakan. | Nama berkas dibangun ulang menjadi unik sebelum disimpan ke storage. | 887cd85 | **CLOSED** |
| **BUG-10** | Struk & Otorisasi | Struk pesanan dapat dibuka pengguna lain dengan menebak ID pesanan (IDOR). | Otorisasi dipusatkan pada _PesananPolicy_: hanya pemilik, kasir, dan admin. | 848f923 | **CLOSED** |
| **BUG-11** | Geofence Pelanggan | Berkas `LokasiController.php` belum lolos pemeriksaan gaya kode Laravel Pint. | Merapikan format dengan Pint; seluruh 118 berkas kini berstatus PASS. | eb607e5 | **CLOSED** |

#### B. Perintah Eksekusi Git Release Tagging

```bash
# 1. Pastikan seluruh cabang fitur telah digabungkan ke cabang utama
git checkout main
git pull origin main

# 2. Periksa bahwa tidak ada berkas yang belum di-commit
git status

# 3. Terbitkan Git Annotated Tag
git tag -a v1.0.0-rc -m "Tejas Coffee POS Release Candidate 1.0.0 - Siap Asesmen UKK"

# 4. Dorong tag ke remote repository
git push origin v1.0.0-rc
```
