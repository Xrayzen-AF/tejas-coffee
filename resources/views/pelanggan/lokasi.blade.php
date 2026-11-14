@extends('layouts.app')

@section('title', 'Verifikasi Lokasi — Tejas Coffee')

@php
    $radius = (int) $settings['radius'];
    $akurasiMaks = (int) $maxAkurasi;
    $aktif = (bool) $settings['enabled'];
    $namaToko = $store['store_name'] ?? 'Tejas Coffee';
    $cabang = $store['branch'] ?? '';
    $alamat = $store['address'] ?? '';
@endphp

@section('content')
<style>
  .geo { font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; min-height: 100vh; background: radial-gradient(ellipse at 50% -10%, #2F231D 0%, #1E1511 55%, #17100D 100%); color: #F1DFD8; display: flex; align-items: center; justify-content: center; padding: 28px 18px 40px; }
  .geo-card { width: 100%; max-width: 460px; background: #271D19; border: 1px solid #3D322E; border-radius: 18px; padding: 26px 22px 22px; box-shadow: 0 20px 48px rgba(0,0,0,.55); }
  .geo-mark { width: 58px; height: 58px; border-radius: 18px; background: linear-gradient(150deg, #D97706, #B56100); color: #241708; display: flex; align-items: center; justify-content: center; margin-bottom: 16px; box-shadow: 0 10px 22px rgba(217,119,6,.28); }
  .geo-title { font-family: 'Lora', ui-serif, Georgia, serif; font-size: 23px; line-height: 1.25; margin: 0 0 8px; }
  .geo-lead { font-size: 13.5px; line-height: 1.65; color: #B8A8A1; margin: 0 0 16px; }
  .geo-lead strong { color: #F1DFD8; font-weight: 600; }
  .geo-cafe { display: flex; gap: 10px; background: #221A15; border: 1px solid #3D322E; border-radius: 12px; padding: 12px 13px; margin-bottom: 14px; }
  .geo-cafe svg { flex: none; margin-top: 2px; color: #E0A96D; }
  .geo-cafe h2 { font-size: 13px; font-weight: 700; margin: 0 0 2px; }
  .geo-cafe p { font-size: 12px; line-height: 1.5; color: #B8A8A1; margin: 0; }
  .geo-status { border-radius: 12px; border: 1px solid #3D322E; background: #221A15; padding: 12px 13px; margin-bottom: 14px; }
  .geo-status strong { display: block; font-size: 12.5px; margin-bottom: 3px; }
  .geo-status span { font-size: 12px; line-height: 1.55; color: #B8A8A1; }
  .geo-status.is-wait { border-color: rgba(217,119,6,.55); background: rgba(217,119,6,.1); }
  .geo-status.is-wait strong { color: #F59E0B; }
  .geo-status.is-ok { border-color: rgba(74,222,128,.45); background: rgba(74,222,128,.08); }
  .geo-status.is-ok strong { color: #4ADE80; }
  .geo-status.is-err { border-color: rgba(248,113,113,.5); background: rgba(248,113,113,.09); }
  .geo-status.is-err strong { color: #F87171; }
  .geo-alert { display: flex; gap: 8px; font-size: 12.5px; line-height: 1.55; border-radius: 12px; padding: 11px 12px; margin-bottom: 14px; border: 1px solid rgba(248,113,113,.5); background: rgba(248,113,113,.09); color: #FCA5A5; }
  .geo-btn { width: 100%; border: 0; border-radius: 12px; background: #D97706; color: #241708; font-family: inherit; font-size: 14.5px; font-weight: 700; padding: 14px 16px; display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer; transition: .15s; }
  .geo-btn:hover:not(:disabled) { background: #B56100; }
  .geo-btn:disabled { opacity: .65; cursor: not-allowed; }
  .geo-btn-ghost { width: 100%; margin-top: 10px; border: 1px solid #3D322E; background: transparent; color: #B8A8A1; border-radius: 12px; padding: 10px; font-family: inherit; font-size: 12.5px; cursor: pointer; }
  .geo-btn-ghost:hover { color: #F1DFD8; border-color: #6B5A52; }
  .geo-meta { margin-top: 14px; display: grid; gap: 5px; font-size: 11.5px; color: #82746E; }
  .geo-meta b { color: #B8A8A1; font-weight: 600; }
  .geo-steps { margin: 14px 0 0; padding: 12px 13px; border-radius: 12px; background: #221A15; border: 1px dashed #3D322E; font-size: 11.5px; line-height: 1.6; color: #9D8E87; }
  .geo-steps b { color: #B8A8A1; }
  .spin { width: 15px; height: 15px; border-radius: 50%; border: 2px solid rgba(36,23,8,.35); border-top-color: #241708; animation: geospin .7s linear infinite; }
  @keyframes geospin { to { transform: rotate(360deg); } }
</style>

<main class="geo">
  <div class="geo-card">
    <div class="geo-mark" aria-hidden="true">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 21s7-5.4 7-11a7 7 0 1 0-14 0c0 5.6 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/>
      </svg>
    </div>

    <h1 class="geo-title">{{ $terverifikasi ? 'Lokasi Kamu Sudah Terverifikasi' : 'Aktifkan GPS Sebelum Memesan' }}</h1>

    <p class="geo-lead">
      @if ($aktif)
        {{ $namaToko }} hanya menerima pesanan dari pelanggan yang berada di dalam
        <strong>radius {{ $radius }} meter</strong> dari cafe. GPS dipakai untuk
        memastikan pesanan benar-benar dibuat di tempat, bukan pesanan fiktif dari luar cafe.
      @else
        Verifikasi lokasi sedang tidak diwajibkan. Kamu bisa langsung memesan seperti biasa.
      @endif
    </p>

    <div class="geo-cafe">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 10.5 12 3l9 7.5"/><path d="M5 10v10h14V10"/>
      </svg>
      <div>
        <h2>{{ $namaToko }}</h2>
        <p>{{ $cabang }}<br>{{ $alamat }}</p>
      </div>
    </div>

    @if (session('error'))
      <div class="geo-alert" role="alert">
        <span aria-hidden="true">⚠</span>
        <span>{{ session('error') }}</span>
      </div>
    @endif

    <div id="geoStatus" class="geo-status {{ $terverifikasi ? 'is-ok' : '' }}" role="status" aria-live="polite">
      <strong id="geoStatusTitle">{{ $terverifikasi ? 'Terverifikasi' : 'GPS belum dicek' }}</strong>
      <span id="geoStatusText">
        @if ($terverifikasi)
          Perangkat kamu tercatat ±{{ $jarakTersimpan }} m dari cafe dan dipantau otomatis tiap {{ $interval }} detik.
        @else
          Tekan tombol di bawah, lalu pilih <b>Izinkan</b> saat browser meminta akses lokasi.
        @endif
      </span>
    </div>

    @if ($terverifikasi)
      <a href="{{ route('pelanggan.dashboard') }}" class="geo-btn" style="text-decoration:none;">
        Lanjut ke Beranda
      </a>
      <form method="POST" action="{{ route('logout') }}" style="margin:0;">
        @csrf
        <button type="submit" class="geo-btn-ghost">Keluar dari akun</button>
      </form>
    @else
      <form id="geoForm" method="POST" action="{{ route('pelanggan.lokasi.verifikasi') }}">
        @csrf
        <input type="hidden" name="latitude" id="geoLat" value="{{ old('latitude') }}">
        <input type="hidden" name="longitude" id="geoLng" value="{{ old('longitude') }}">
        <input type="hidden" name="accuracy" id="geoAcc" value="{{ old('accuracy') }}">
        <button type="button" id="geoBtn" class="geo-btn">
          <span id="geoBtnSpin" class="spin" hidden></span>
          <span id="geoBtnText">Aktifkan GPS &amp; Cek Lokasi</span>
        </button>
      </form>

      <form method="POST" action="{{ route('logout') }}" style="margin:0;">
        @csrf
        <button type="submit" class="geo-btn-ghost">Keluar dari akun</button>
      </form>

      <div class="geo-meta">
        <span><b>Radius izin:</b> {{ $radius }} meter dari titik cafe</span>
        <span><b>Akurasi maksimal:</b> ±{{ $akurasiMaks }} meter</span>
        <span><b>Target akurasi:</b> ±{{ (int) $targetAkurasi }} meter</span>
        <span><b>Dicek ulang:</b> otomatis tiap {{ $interval }} detik</span>
      </div>

      <div class="geo-steps">
        <b>GPS tidak terdeteksi?</b> Pastikan Location/GPS menyala, izin lokasi untuk
        browser ini diatur ke <b>Allow</b>, lalu muat ulang halaman. Di laptop, aktifkan
        Location di pengaturan sistem. Sinyal lemah di dalam ruangan — coba dekat jendela.
      </div>
    @endif
  </div>
</main>

@unless ($terverifikasi)
<script>
(function () {
    var form = document.getElementById('geoForm');
    var btn = document.getElementById('geoBtn');
    var btnText = document.getElementById('geoBtnText');
    var btnSpin = document.getElementById('geoBtnSpin');
    var statusBox = document.getElementById('geoStatus');
    var statusTitle = document.getElementById('geoStatusTitle');
    var statusText = document.getElementById('geoStatusText');
    var mengirim = false;
    var mencari = false;
    var targetAkurasi = {{ (int) $targetAkurasi }};
    var batasAkurasi = {{ (int) $maxAkurasi }};
    var best = null;
    var watchId = null;
    var timer = null;
    var selesai = false;
    var cobaKasar = false;

    function aturStatus(kelas, judul, teks) {
        statusBox.className = 'geo-status ' + kelas;
        statusTitle.textContent = judul;
        statusText.innerHTML = teks;
    }

    function proses() {
        if (mengirim || mencari) return;

        if (!('geolocation' in navigator)) {
            aturStatus('is-err', 'GPS tidak didukung',
                'Browser ini tidak menyediakan Geolocation API. Buka aplikasi lewat Chrome/Safari versi terbaru.');
            return;
        }

        btn.disabled = true;
        btnSpin.hidden = false;
        btnText.textContent = 'Membaca GPS…';
        aturStatus('is-wait', 'Memeriksa izin lokasi…', 'Pilih <b>Izinkan</b> bila browser menampilkan dialog lokasi.');
        cobaKasar = false;

        // Izin yang sudah diblokir tidak perlu ditunggu sampai timeout.
        if (navigator.permissions && navigator.permissions.query) {
            navigator.permissions.query({ name: 'geolocation' }).then(function (hasil) {
                if (hasil.state === 'denied') {
                    izinDitolak();
                    return;
                }
                mulaiPantau(true);
            }).catch(function () {
                mulaiPantau(true);
            });
            return;
        }

        mulaiPantau(true);
    }

    function izinDitolak() {
        btn.disabled = false;
        btnSpin.hidden = true;
        btnText.textContent = 'Coba Lagi';
        aturStatus('is-err', 'Izin lokasi diblokir',
            'Browser memblokir lokasi untuk situs ini, jadi GPS tidak bisa dibaca. ' +
            'Klik ikon gembok di address bar → <b>Location</b> → <b>Allow</b>, lalu muat ulang halaman.');
    }

    // Ambil beberapa sampel GPS berturut-turut, lalu pakai yang paling akurat.
    function mulaiPantau(akurasiTinggi) {
        mencari = true;
        best = null;
        selesai = false;
        aturStatus('is-wait', akurasiTinggi ? 'Meminta izin lokasi…' : 'Mencoba sinyal GPS cadangan…',
            'Pilih <b>Izinkan</b> pada dialog browser. Jangan tutup halaman ini.');

        watchId = navigator.geolocation.watchPosition(terima, salah, {
            enableHighAccuracy: akurasiTinggi !== false,
            timeout: akurasiTinggi === false ? 15000 : 20000,
            maximumAge: akurasiTinggi === false ? 60000 : 0
        });
        timer = setTimeout(akhiri, akurasiTinggi === false ? 12000 : 15000);
    }

    function terima(pos) {
        if (selesai) return;

        if (!best || pos.coords.accuracy < best.coords.accuracy) {
            best = pos;
        }

        var akurasi = Math.round(best.coords.accuracy);
        aturStatus('is-wait', 'Menstabilkan sinyal GPS…',
            'Akurasi terbaik ±' + akurasi + ' m. ' +
            (akurasi <= targetAkurasi
                ? 'Sinyal bagus, memverifikasi…'
                : 'Diam sejenak di area terbuka sampai akurasi ≤ ±' + targetAkurasi + ' m.'));

        if (akurasi <= targetAkurasi) akhiri();
    }

    // Kode 2/3 dari watchPosition sering hanya sementara; biarkan timer memutuskan.
    function salah(err) {
        if (err && err.code === 1) gagal(err);
    }

    function akhiri() {
        if (selesai) return;
        selesai = true;
        mencari = false;
        clearTimeout(timer);

        if (watchId !== null) {
            navigator.geolocation.clearWatch(watchId);
            watchId = null;
        }

        if (!best) {
            // GPS presisi tinggi sering timeout di laptop/dalam ruangan.
            // Coba sekali dengan akurasi kasar (berbasis jaringan) sebelum menyerah.
            if (!cobaKasar) {
                cobaKasar = true;
                mulaiPantau(false);
                return;
            }

            gagal({ code: 3 });
            return;
        }

        if (best.coords.accuracy > batasAkurasi) {
            btn.disabled = false;
            btnSpin.hidden = true;
            btnText.textContent = 'Coba Lagi';
            aturStatus('is-err', 'Akurasi GPS belum cukup',
                'Akurasi terbaik ±' + Math.round(best.coords.accuracy) + ' m, maksimal ±' + batasAkurasi + ' m. ' +
                'Nyalakan GPS presisi tinggi dan keluar ke area terbuka, lalu coba lagi.');
            return;
        }

        kirim(best);
    }

    function kirim(pos) {
        document.getElementById('geoLat').value = pos.coords.latitude.toFixed(7);
        document.getElementById('geoLng').value = pos.coords.longitude.toFixed(7);
        document.getElementById('geoAcc').value = Math.round(pos.coords.accuracy);

        aturStatus('is-wait', 'Lokasi terbaca, memverifikasi…',
            'Akurasi ±' + Math.round(pos.coords.accuracy) + ' m. Mohon tunggu sebentar.');
        btnText.textContent = 'Memverifikasi…';
        mengirim = true;
        form.submit();
    }

    function gagal(err) {
        selesai = true;
        mencari = false;
        clearTimeout(timer);

        if (watchId !== null) {
            navigator.geolocation.clearWatch(watchId);
            watchId = null;
        }

        btn.disabled = false;
        btnSpin.hidden = true;
        btnText.textContent = 'Coba Lagi';

        if (err.code === 1) {
            izinDitolak();
        } else if (err.code === 2) {
            aturStatus('is-err', 'Sinyal GPS tidak tersedia',
                'Perangkat melaporkan posisi tidak tersedia (kode 2). Nyalakan <b>Location</b> di pengaturan sistem ' +
                '(Windows: Settings → Privacy & security → Location), lalu tekan Coba Lagi.');
        } else {
            aturStatus('is-err', 'GPS timeout (kode 3)',
                'Sinyal tidak diterima dalam batas waktu, jadi posisi tidak bisa dipastikan. Periksa: izin lokasi ' +
                '<b>Allow</b>, Location Windows menyala, tidak memakai mode hemat baterai, lalu coba lagi dekat jendela.');
        }
    }

    btn.addEventListener('click', proses);

    // Langsung minta GPS saat halaman dibuka (memenuhi aturan "wajib GPS").
    if (document.readyState === 'complete') {
        setTimeout(proses, 350);
    } else {
        window.addEventListener('load', function () { setTimeout(proses, 350); });
    }
})();
</script>
@endunless
@endsection
