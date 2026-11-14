# Gate 5 — Finalization & Release Candidate Review

Instrumen audit akhir sebelum tim boleh masuk Uji Praktik 4 Hari.
Diisi berdasarkan kondisi repo per **14 November 2026**.

| # | Kriteria | Status | Bukti / Lokasi |
| :---: | :--- | :---: | :--- |
| 1 | Code Freeze & Git tag `v1.0.0-rc` terpasang | ✅ | `git tag` → `v1.0.0-rc`; kebijakan freeze di `docs/01-release/RELEASE_NOTES.md` §1 |
| 2 | Staging cloud aktif & fallback LAN offline siap | ⏳ | Prosedur + skrip `docs/08-deployment/`; URL publik menunggu kredensial hosting |
| 3 | User Manual & Technical Docs lengkap di `docs/` | ✅ | `docs/02-manual/USER_MANUAL.md`, `docs/03-technical/TECHNICAL_DOCS.md` |
| 4 | `README.md` profesional siap dievaluasi penguji | ✅ | `README.md` (stack, instalasi, akun demo, API, struktur direktori) |
| 5 | API Documentation / kontrak data terdokumentasi | ✅ | `docs/04-api/openapi.json` + `docs/04-api/API_CONTRACT.md` |
| 6 | Slide presentasi 10–12 slide matang (pitching 7–10 menit) | ✅ | `docs/05-presentation/pitch-deck.html` (12 slide, siap cetak) |
| 7 | Gladi bersih live demo tuntas tanpa kendala teknis | ⏳ | Naskah: `docs/06-demo/DEMO_SCRIPT.md`; berita acara menunggu tanda tangan pembimbing |
| 8 | Kedua siswa lolos simulasi ujian lisan individu | ⏳ | Bank soal: `docs/07-defense/BANK_SOAL_DEFENSE.md`; status latihan diisi setelah simulasi |

## Ringkasan Keputusan

**Status: PENDING REMEDIATION (3 dari 8 item).**

Tiga item yang belum tertutup bukan pekerjaan kode, melainkan kegiatan yang
menuntut pihak ketiga (akun hosting, pembimbing, perekaman video) dan
kehadiran kedua anggota tim:

1. **Item 2** — butuh kredensial hosting/domain. Alternatif aman: demo memakai
   server lokal LAN (`serve-lan.bat`) dan siapkan tangkapan layar staging.
2. **Item 7** — butuh jadwal gladi bersih dengan pembimbing.
3. **Item 8** — butuh sesi simulasi tanya-jawab.

Seluruh item yang bergantung pada kode dan dokumen sudah tertutup, sehingga
tim dapat mengerjakan tiga item tersisa tanpa menyentuh `main`.

## Bukti Teknis Pendukung

| Pemeriksaan | Perintah | Hasil |
| :--- | :--- | :--- |
| Suite tes | `php artisan test` | 138 lulus, 669 assertion |
| Gaya kode | `./vendor/bin/pint --test` | PASS, 118 file |
| Sisa kode debug | pencarian `dd(`/`dump(`/`console.log(` | 0 hasil |
| Tag rilis | `git tag --list` | `v1.0.0-rc` |

## Tanda Tangan

| Peran | Nama | Tanda Tangan | Tanggal |
| :--- | :--- | :--- | :--- |
| Siswa 1 (Frontend) | | | |
| Siswa 2 (Backend) | | | |
| Guru Pembimbing | | | |
