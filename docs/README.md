# Dokumentasi Sprint 4 — Tejas Coffee POS

Fase **Finalization & Release Candidate** untuk aplikasi Tejas Coffee POS
(Sistem Pemesanan Coffee Shop). Seluruh isi folder ini adalah artefak wajib
Sprint 4 pada job sheet UKK RPL.

Versi rilis: **v1.0.0-rc** · Basis kode: `main` · Tanggal: 14 November 2026

## Peta Artefak

| Kode | Tugas Job Sheet | Artefak di repo ini |
| :---: | :--- | :--- |
| S4-01 | Final Bug Fixing (Release Candidate) | [`01-release/RELEASE_NOTES.md`](01-release/RELEASE_NOTES.md) |
| S4-01 | Audit akhir Gate 5 | [`01-release/GATE5_CHECKLIST.md`](01-release/GATE5_CHECKLIST.md) |
| S4-02 | Deployment staging + fallback LAN | [`08-deployment/DEPLOYMENT.md`](08-deployment/DEPLOYMENT.md), [`08-deployment/serve-lan.bat`](08-deployment/serve-lan.bat) |
| S4-03 | User Manual | [`03-manual/USER_MANUAL.md`](03-manual/USER_MANUAL.md) · [`USER_MANUAL.docx`](03-manual/USER_MANUAL.docx) · struktur: [`S4-03_struktur-user-manual.docx`](03-manual/S4-03_struktur-user-manual.docx) |
| S4-03 | Technical Documentation | [`03-technical/TECHNICAL_DOCS.md`](03-technical/TECHNICAL_DOCS.md) |
| S4-04 | README repositori | [`../README.md`](../README.md) |
| S4-05 | API Documentation | [`04-api/openapi.json`](04-api/openapi.json), [`04-api/API_CONTRACT.md`](04-api/API_CONTRACT.md) |
| S4-06 | Slide presentasi | [`05-presentation/pitch-deck.html`](05-presentation/pitch-deck.html) |
| S4-07 | Demo rehearsal | [`06-demo/DEMO_SCRIPT.md`](06-demo/DEMO_SCRIPT.md), [`06-demo/BERITA_ACARA_REHEARSAL.md`](06-demo/BERITA_ACARA_REHEARSAL.md) |
| S4-08 | Pertanggungjawaban individu | [`07-defense/BANK_SOAL_DEFENSE.md`](07-defense/BANK_SOAL_DEFENSE.md) |

## Status Ringkas

| Item | Status |
| :--- | :--- |
| Suite tes (`php artisan test`) | ✅ 138 lulus / 669 assertion |
| Format kode (`./vendor/bin/pint --test`) | ✅ 118 file bersih |
| Sisa kode debug (`dd`, `dump`, `console.log`) | ✅ Tidak ada |
| Git tag `v1.0.0-rc` | ✅ Terpasang di `main` |
| Deployment cloud | ⏳ Menunggu kredensial hosting |
| Video cadangan demo | ⏳ Belum direkam (perlu perangkat + narasi) |

## Cara Mengekspor Artefak ke PDF

Dokumen di folder ini ditulis dalam Markdown. Konverter `pandoc` tidak terpasang
di mesin ini, jadi ekspor PDF dilakukan lewat salah satu cara berikut:

1. **VS Code** — buka berkas `.md`, jalankan perintah *Markdown PDF: Export (pdf)*.
2. **Browser** — buka [`05-presentation/pitch-deck.html`](05-presentation/pitch-deck.html)
   atau pratinjau Markdown, lalu `Ctrl+P` → *Save as PDF* (ukuran A4, margin default).

Berkas cetak laporan penjualan (A4) sudah punya gaya khusus print di aplikasi:
menu **Admin → Sales & Revenue → Cetak Laporan**.
