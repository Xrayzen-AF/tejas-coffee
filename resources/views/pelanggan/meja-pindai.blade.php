@extends('layouts.app')

@section('title', 'Scan QR Meja — Tejas Coffee')

@php $aktif = 'beranda'; @endphp

@section('content')
<style>
  .pindai-wrap { width: 100%; max-width: 520px; margin: 0 auto; padding: 20px 16px 96px; font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; }
  .pindai-back { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: #B8A8A1; text-decoration: none; margin-bottom: 18px; }
  .pindai-back:hover { color: #F1DFD8; }
  .pindai-title { font-family: 'Lora', ui-serif, Georgia, serif; font-size: 25px; line-height: 1.25; margin: 0 0 6px; color: #F1DFD8; }
  .pindai-lead { font-size: 13px; line-height: 1.65; color: #B8A8A1; margin: 0 0 18px; }
  .pindai-stage { position: relative; aspect-ratio: 4 / 3; border-radius: 14px; overflow: hidden; background: #120D0A; border: 1px solid #3D322E; }
  .pindai-stage video { width: 100%; height: 100%; object-fit: cover; display: block; }
  .pindai-frame { position: absolute; inset: 16%; border-radius: 14px; border: 2px solid rgba(224,169,109,.75); box-shadow: 0 0 0 999px rgba(12,9,7,.35) inset; pointer-events: none; }
  .pindai-frame::after { content: ''; position: absolute; left: 8%; right: 8%; top: 50%; height: 2px; background: linear-gradient(90deg, transparent, #F59E0B, transparent); animation: pindaiScan 2.2s ease-in-out infinite; }
  @keyframes pindaiScan { 0%, 100% { transform: translateY(-34px); opacity: .2; } 50% { transform: translateY(34px); opacity: 1; } }
  .pindai-hint { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; padding: 20px; text-align: center; font-size: 12.5px; line-height: 1.6; color: #B8A8A1; background: radial-gradient(circle at 50% 45%, #221A15 0%, #14100D 75%); }
  .pindai-actions { display: grid; gap: 10px; margin-top: 16px; }
  .pindai-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 13px 16px; border-radius: 10px; font-size: 13px; font-weight: 700; text-decoration: none; border: 1px solid transparent; cursor: pointer; font-family: inherit; }
  .pindai-btn-primary { background: #D97706; color: #241708; }
  .pindai-btn-primary:hover { background: #F59E0B; }
  .pindai-btn-ghost { background: transparent; border-color: #4A3B35; color: #D7C7BE; }
  .pindai-btn-ghost:hover { border-color: #6B5548; color: #F1DFD8; }
  .pindai-status { margin: 14px 0 0; padding: 11px 13px; border-radius: 10px; border: 1px solid #3D322E; background: #221A15; font-size: 12.5px; line-height: 1.6; color: #B8A8A1; }
  .pindai-status.is-wait { border-color: rgba(217,119,6,.5); background: rgba(217,119,6,.09); color: #F5C77E; }
  .pindai-status.is-ok { border-color: rgba(74,222,128,.45); background: rgba(74,222,128,.08); color: #86E0A4; }
  .pindai-status.is-err { border-color: rgba(248,113,113,.45); background: rgba(248,113,113,.08); color: #FCA5A5; }
  .pindai-help { margin-top: 14px; padding: 12px 13px; border-radius: 10px; border: 1px dashed #3D322E; background: #221A15; font-size: 11.5px; line-height: 1.65; color: #9D8E87; }
  .pindai-help b { color: #B8A8A1; }
</style>

<div class="pindai-wrap">
    <a href="{{ route('pelanggan.dashboard') }}" class="pindai-back">
        <i data-lucide="arrow-left" class="h-4 w-4"></i> Beranda
    </a>

    <h1 class="pindai-title">Scan QR Meja</h1>
    <p class="pindai-lead">
        Arahkan kamera ke QR yang tertempel di meja. Kamu akan melihat status meja,
        lalu bisa memilih <strong style="color:#F1DFD8;">memakai meja itu</strong> atau
        tidak. Kalau meja sedang dipesan orang lain, aplikasi menawarkan meja kosong lain.
    </p>

    @if (session('error'))
        <div class="pindai-status is-err" style="margin-bottom:16px;">
            <strong>{{ session('error') }}</strong>
        </div>
    @endif

    <div class="pindai-stage">
        <video id="qrVideo" playsinline muted aria-label="Pratinjau kamera untuk scan QR meja"></video>
        <div class="pindai-frame" aria-hidden="true"></div>
        <div id="qrHint" class="pindai-hint">
            Kamera belum aktif.<br>Tekan tombol <b>Buka kamera</b> di bawah.
        </div>
    </div>

    <div class="pindai-actions">
        <button type="button" id="qrStart" class="pindai-btn pindai-btn-primary">
            <i data-lucide="camera" class="h-4 w-4"></i> Buka kamera
        </button>
        <button type="button" id="qrStop" class="pindai-btn pindai-btn-ghost" hidden>
            <i data-lucide="camera-off" class="h-4 w-4"></i> Hentikan kamera
        </button>
        <a href="{{ route('pelanggan.dashboard') }}" class="pindai-btn pindai-btn-ghost">
            <i data-lucide="grid-3x3" class="h-4 w-4"></i> Lihat status semua meja
        </a>
    </div>

    <p id="qrStatus" class="pindai-status" role="status" aria-live="polite">
        Kamera hanya dipakai untuk membaca QR. Gambar tidak dikirim atau disimpan.
    </p>

    <div class="pindai-help">
        <b>Kamera tidak bisa dibuka?</b> Izinkan akses kamera untuk situs ini, lalu coba lagi.
        Di laptop tanpa kamera, buka QR memakai kamera HP. Pemindai juga butuh koneksi aman
        (HTTPS atau <b>localhost</b>).
    </div>
</div>

<script>
(function () {
    var video = document.getElementById('qrVideo');
    var hint = document.getElementById('qrHint');
    var statusEl = document.getElementById('qrStatus');
    var startBtn = document.getElementById('qrStart');
    var stopBtn = document.getElementById('qrStop');

    var stream = null;
    var detector = null;
    var pemindaiJs = null;
    var timer = null;
    var berhenti = true;
    var canvas = null;
    var ctx = null;

    function aturStatus(teks, kelas) {
        statusEl.textContent = teks;
        statusEl.className = 'pindai-status' + (kelas ? ' is-' + kelas : '');
    }

    // QR meja berisi URL /meja/{token}; terima juga token mentah.
    function bukaMeja(teks) {
        var bersih = String(teks || '').trim();
        var url = null;

        try {
            var u = new URL(bersih, window.location.origin);
            var cocok = u.pathname.match(/^\/meja\/([A-Za-z0-9]{20,64})\/?$/);
            if (u.origin === window.location.origin && cocok) {
                url = '/meja/' + cocok[1];
            }
        } catch (e) { /* bukan URL */ }

        if (!url && /^[A-Za-z0-9]{20,64}$/.test(bersih)) {
            url = '/meja/' + bersih;
        }

        if (!url) {
            aturStatus('QR ini bukan QR meja Tejas Coffee. Coba QR yang tertempel di meja.', 'err');
            return false;
        }

        hentikan();
        aturStatus('Meja ditemukan. Membuka status meja…', 'ok');
        window.location.href = url;
        return true;
    }

    function muatJsQr() {
        if (window.jsQR) return Promise.resolve(window.jsQR);

        return new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = 'https://unpkg.com/jsqr@1.4.0/dist/jsQR.js';
            s.onload = function () { window.jsQR ? resolve(window.jsQR) : reject(new Error('jsQR kosong')); };
            s.onerror = function () { reject(new Error('gagal memuat jsQR')); };
            document.head.appendChild(s);
        });
    }

    function pindaiBingkai() {
        if (berhenti) return;

        var janji = Promise.resolve(null);

        if (detector) {
            janji = detector.detect(video).then(function (hasil) {
                return hasil && hasil.length ? hasil[0].rawValue : null;
            }).catch(function () { return null; });
        } else if (pemindaiJs && video.readyState >= 2 && video.videoWidth) {
            try {
                if (!canvas) {
                    canvas = document.createElement('canvas');
                    ctx = canvas.getContext('2d', { willReadFrequently: true });
                }
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                var gambar = ctx.getImageData(0, 0, canvas.width, canvas.height);
                var kode = pemindaiJs(gambar.data, gambar.width, gambar.height, { inversionAttempts: 'dontInvert' });
                if (kode && kode.data) janji = Promise.resolve(kode.data);
            } catch (e) { /* frame belum siap */ }
        }

        janji.then(function (teks) {
            if (berhenti) return;
            if (teks && bukaMeja(teks)) return;
            timer = setTimeout(pindaiBingkai, 300);
        });
    }

    function hentikan() {
        berhenti = true;
        clearTimeout(timer);

        if (stream) {
            stream.getTracks().forEach(function (t) { t.stop(); });
            stream = null;
        }

        video.srcObject = null;
        stopBtn.hidden = true;
        startBtn.hidden = false;
        hint.hidden = false;
    }

    function mulai() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            aturStatus('Browser ini tidak menyediakan kamera. Buka aplikasi lewat Chrome/Safari terbaru dengan HTTPS.', 'err');
            return;
        }

        aturStatus('Meminta izin kamera…', 'wait');
        startBtn.disabled = true;

        navigator.mediaDevices.getUserMedia({
            audio: false,
            video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } }
        }).then(function (s) {
            stream = s;
            video.srcObject = s;
            return video.play().then(function () { return s; });
        }).then(function (s) {
            berhenti = false;
            startBtn.disabled = false;
            startBtn.hidden = true;
            stopBtn.hidden = false;
            hint.hidden = true;
            aturStatus('Kamera aktif. Arahkan ke QR di meja, jangan terlalu jauh.', 'wait');

            detector = null;
            pemindaiJs = null;

            if ('BarcodeDetector' in window) {
                try {
                    detector = new window.BarcodeDetector({ formats: ['qr_code'] });
                } catch (e) {
                    detector = null;
                }
            }

            if (!detector) {
                muatJsQr().then(function (lib) {
                    pemindaiJs = lib;
                    pindaiBingkai();
                }).catch(function () {
                    hentikan();
                    aturStatus('Pemindai QR gagal dimuat. Periksa koneksi lalu coba lagi.', 'err');
                });
                return;
            }

            pindaiBingkai();
        }).catch(function (err) {
            startBtn.disabled = false;
            hentikan();

            if (err && (err.name === 'NotAllowedError' || err.name === 'SecurityError')) {
                aturStatus('Izin kamera ditolak. Klik ikon gembok di address bar → Camera → Allow, lalu muat ulang halaman.', 'err');
            } else if (err && err.name === 'NotFoundError') {
                aturStatus('Kamera tidak ditemukan di perangkat ini. Coba buka QR memakai kamera HP.', 'err');
            } else {
                aturStatus('Kamera gagal dibuka. Tutup aplikasi lain yang memakai kamera, lalu coba lagi.', 'err');
            }
        });
    }

    startBtn.addEventListener('click', mulai);
    stopBtn.addEventListener('click', function () {
        hentikan();
        aturStatus('Kamera dimatikan.', '');
    });

    window.addEventListener('pagehide', hentikan);
})();
</script>

@include('pelanggan._nav')
@endsection
