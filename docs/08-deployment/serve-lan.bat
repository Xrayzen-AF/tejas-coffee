@echo off
REM ============================================================
REM  Tejas Coffee POS - Server Lokal LAN (mode cadangan offline)
REM  Dipakai bila internet ruang ujian terputus.
REM  Jalankan dari root proyek, atau lewat pintasan di folder ini.
REM ============================================================
setlocal

cd /d "%~dp0..\.."

echo.
echo === Tejas Coffee POS : Server Lokal LAN ===
echo.

REM Tampilkan alamat IP agar bisa dibuka dari HP penguji
echo Alamat IP laptop ini:
ipconfig | findstr /i "IPv4"
echo.
echo Buka dari HP: http://IP-DI-ATAS:8000
echo.

REM Bersihkan cache konfigurasi agar perubahan .env terbaca
php artisan config:clear >nul 2>&1
php artisan view:clear >nul 2>&1

echo Menjalankan server pada 0.0.0.0:8000 ...
echo Tekan Ctrl+C untuk menghentikan server.
echo.

php artisan serve --host=0.0.0.0 --port=8000

if errorlevel 1 (
    echo.
    echo [GAGAL] Server tidak dapat dijalankan.
    echo Periksa: PHP terpasang di PATH, dependensi sudah di-install
    echo ^(composer install^), dan database sudah dimigrasi ^(php artisan migrate --seed^).
)

pause
endlocal
