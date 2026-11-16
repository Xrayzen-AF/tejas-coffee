@extends('layouts.app')

@section('title', 'Meja ' . $meja->nomor_meja)

@php
    $aktif = 'beranda';
    $milikSendiri = $state['kode'] === 'milik_sendiri';
    $bisaKlaim = $state['bisa_klaim'];
    $tamu = ! auth()->check();
    $labelWarna = $state['status'] === 'kosong' ? '#4ADE80' : ($state['status'] === 'dipesan' ? '#FBBF24' : '#F87171');
@endphp

@section('content')
<style>
  .scan-wrap { width: 100%; max-width: 520px; margin: 0 auto; padding: 20px 16px 96px; font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; }
  .scan-back { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: #B8A8A1; text-decoration: none; margin-bottom: 18px; }
  .scan-back:hover { color: #F1DFD8; }
  .scan-card { background: #271D19; border: 1px solid #3D322E; border-radius: 12px; padding: 20px; box-shadow: 0 18px 40px -28px rgba(0,0,0,.9); }
  .scan-eyebrow { font-size: 10px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: #9D8E87; }
  .scan-table { font-family: 'Lora', ui-serif, Georgia, serif; font-size: 34px; line-height: 1.1; margin: 6px 0 2px; color: #F1DFD8; }
  .scan-meta { font-size: 13px; color: #B8A8A1; }
  .scan-badge { display: inline-flex; align-items: center; gap: 6px; margin-top: 14px; padding: 5px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
  .scan-dot { width: 6px; height: 6px; border-radius: 999px; }
  .scan-title { font-size: 18px; font-weight: 600; margin: 16px 0 6px; color: #F1DFD8; }
  .scan-text { font-size: 13.5px; line-height: 1.65; color: #B8A8A1; }
  .scan-actions { display: grid; gap: 10px; margin-top: 18px; }
  .scan-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 12px 16px; border-radius: 8px; font-size: 13px; font-weight: 700; text-decoration: none; border: 1px solid transparent; cursor: pointer; font-family: inherit; }
  .scan-btn-primary { background: #D97706; color: #241708; }
  .scan-btn-primary:hover { background: #F59E0B; }
  .scan-btn-ghost { background: transparent; border-color: #4A3B35; color: #D7C7BE; }
  .scan-btn-ghost:hover { border-color: #6B5548; color: #F1DFD8; }
  .scan-code { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 16px; padding: 12px 14px; border: 1px dashed #4A3B35; border-radius: 8px; background: #221A15; }
  .scan-code small { display: block; font-size: 10px; letter-spacing: .1em; text-transform: uppercase; color: #9D8E87; }
  .scan-code strong { font-size: 17px; letter-spacing: .18em; color: #F6C89A; }
  .scan-list { margin: 16px 0 0; padding: 0; list-style: none; display: grid; gap: 8px; }
  .scan-list a { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 12px; border: 1px solid #3D322E; border-radius: 8px; text-decoration: none; color: #F1DFD8; font-size: 13px; font-weight: 600; background: #221A15; }
  .scan-list a:hover { border-color: #6B5548; }
  .scan-list span { font-size: 11px; font-weight: 600; color: #86E0A4; }
  .scan-alert { margin-bottom: 14px; padding: 12px 14px; border-radius: 8px; border: 1px solid #4A2A28; background: rgba(74,42,40,.32); color: #FCA5A5; font-size: 13px; line-height: 1.6; }
  .scan-verify { margin-top: 20px; padding-top: 18px; border-top: 1px solid #3D322E; }
  .scan-input { width: 100%; margin-top: 10px; padding: 11px 14px; border-radius: 8px; border: 1px solid #3D322E; background: #1E1511; color: #F1DFD8; font-family: 'Inter', sans-serif; font-size: 16px; font-weight: 700; letter-spacing: .22em; text-transform: uppercase; outline: none; }
  .scan-input:focus { border-color: #D97706; box-shadow: 0 0 0 3px rgba(217,119,6,.16); }
  .scan-input::placeholder { color: #665A54; font-weight: 600; letter-spacing: .1em; }
  .scan-foot { margin-top: 16px; font-size: 11px; line-height: 1.6; color: #82746E; }
</style>

<div class="scan-wrap">
    <a href="{{ auth()->check() ? route('pelanggan.dashboard') : route('pelanggan.menu') }}" class="scan-back">
        <i data-lucide="arrow-left" class="h-4 w-4"></i> {{ auth()->check() ? 'Beranda' : 'Lihat Menu' }}
    </a>

    {{-- Perkenalan aplikasi: pengunjung baru tahu ini aplikasi apa sebelum login --}}
    <div class="scan-card" style="margin-bottom:14px;">
        <div class="scan-eyebrow">Tentang Aplikasi</div>
        <h2 class="scan-title" style="margin-top:6px;">Tejas Coffee — Pesan Langsung dari Meja</h2>
        <p class="scan-text" style="margin-top:6px;">
            Ini aplikasi pemesanan Tejas Coffee. Cukup pindai QR di meja untuk melihat status meja,
            memesan tanpa membuat akun, membayar dengan QRIS, lalu memantau pesanan sampai siap diambil.
        </p>
        <ul class="scan-list">
            <li>
                <a href="{{ route('pelanggan.menu') }}">Lihat menu &amp; pesan<span>Tanpa akun</span></a>
            </li>
            <li>
                <a href="{{ route('login') }}">Masuk sebagai member<span>Poin &amp; riwayat</span></a>
            </li>
        </ul>
    </div>

    @if (session('error'))
        <div class="scan-alert" role="alert">{{ session('error') }}</div>
    @endif

    @if (session('success'))
        <div class="scan-alert" role="status"
             style="border-color:#2F4A33;background:rgba(47,74,51,.32);color:#86E0A4;">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="scan-alert">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="scan-card">
        <div class="scan-eyebrow">Scan QR Meja</div>
        <h1 class="scan-table">Meja {{ $meja->nomor_meja }}</h1>
        <div class="scan-meta">{{ $meja->kapasitas }} kursi · Tejas Coffee</div>

        <span class="scan-badge" style="background: {{ $labelWarna }}1a; color: {{ $labelWarna }};">
            <span class="scan-dot" style="background: {{ $labelWarna }};"></span>
            {{ $state['label'] }}
        </span>

        @if ($kodeKasirDetik !== null)
            <div class="scan-alert" style="margin:14px 0 0;border-color:#2F4A33;background:rgba(47,74,51,.32);color:#86E0A4;">
                Kehadiran Anda di meja ini sudah terverifikasi lewat kode kasir.
                BERLAKU {{ (int) ceil($kodeKasirDetik / 60) }} menit lagi.
            </div>
        @endif

        @if ($bisaKlaim)
            <h2 class="scan-title">Meja ini kosong</h2>
            <p class="scan-text">
                Mau memakai meja ini? Setelah dipilih, meja tercatat atas nama akun Anda dan
                tidak bisa dipakai pelanggan lain. Sesi diperpanjang selama ada pesanan, lalu
                meja kembali kosong {{ \App\Models\ReservasiMeja::TENGGANG_MENIT }} menit setelah
                pesanan terakhir selesai.
            </p>

            <div class="scan-actions">
                @auth
                    <form method="POST" action="{{ route('pelanggan.meja.checkin', ['meja' => $meja->qr_token]) }}">
                        @csrf
                        <button type="submit" class="scan-btn scan-btn-primary">
                            <i data-lucide="armchair" class="h-4 w-4"></i> Ya, pakai meja ini
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="scan-btn scan-btn-primary">
                        <i data-lucide="log-in" class="h-4 w-4"></i> Masuk untuk memakai meja ini
                    </a>
                @endauth
                <a href="{{ route('pelanggan.menu') }}" class="scan-btn scan-btn-ghost">
                    <i data-lucide="utensils" class="h-4 w-4"></i> Tidak, lihat menu dulu
                </a>
            </div>

            <p class="scan-foot">
                Akun yang login dipakai sebagai bukti kepemilikan meja —
                tanpa password atau PIN tambahan.
            </p>
        @elseif ($milikSendiri)
            @if ($state['reservasi'])
                <h2 class="scan-title">Meja ini sudah dipesan atas nama Anda</h2>
                <p class="scan-text">
                    @if ($terverifikasi)
                        Kode booking Anda cocok dengan sesi meja ini. Masuk ke akun Anda
                        bila ingin memesan menu.
                    @else
                        Anda tidak perlu memasukkan password. Akun yang login sudah cocok dengan
                        reservasi meja ini.
                    @endif
                </p>

                <div class="scan-code">
                    <div>
                        <small>Kode booking</small>
                        <strong>{{ $state['reservasi']->kode_booking }}</strong>
                    </div>
                    <div style="text-align:right;">
                        <small>Berlaku sampai</small>
                        <strong style="font-size:13px;letter-spacing:normal;">
                            {{ $state['reservasi']->berakhir_pada->format('H:i') }}
                        </strong>
                    </div>
                </div>

                <div class="scan-actions">
                    @if ($tamu)
                        <a href="{{ route('login') }}" class="scan-btn scan-btn-primary">
                            <i data-lucide="log-in" class="h-4 w-4"></i> Masuk untuk pesan menu
                        </a>
                    @else
                        <a href="{{ route('pelanggan.menu') }}" class="scan-btn scan-btn-primary">
                            <i data-lucide="book-open" class="h-4 w-4"></i> Pesan menu
                        </a>
                        <form method="POST" action="{{ route('pelanggan.meja.lepas', ['meja' => $meja->qr_token]) }}"
                              data-confirm="Lepas meja {{ $meja->nomor_meja }}? Pastikan tidak ada pesanan yang masih diproses.">
                            @csrf
                            <button type="submit" class="scan-btn scan-btn-ghost">
                                <i data-lucide="log-out" class="h-4 w-4"></i> Selesai &amp; lepas meja
                            </button>
                        </form>
                    @endif
                </div>
            @else
                <h2 class="scan-title">Pesanan Anda sedang berjalan di meja ini</h2>
                <p class="scan-text">
                    Meja {{ $meja->nomor_meja }} tercatat atas akun Anda
                    @if ($state['pesanan'])
                        untuk pesanan <strong style="color:#F1DFD8;">{{ $state['pesanan']->nomor_pesanan }}</strong>.
                    @else
                        .
                    @endif
                </p>

                <div class="scan-actions">
                    @if ($state['pesanan'])
                        <a href="{{ route('pelanggan.status', $state['pesanan']->nomor_pesanan) }}" class="scan-btn scan-btn-primary">
                            <i data-lucide="receipt-text" class="h-4 w-4"></i> Lihat status pesanan
                        </a>
                    @endif
                    <a href="{{ route('pelanggan.menu') }}" class="scan-btn scan-btn-ghost">
                        <i data-lucide="plus" class="h-4 w-4"></i> Tambah pesanan
                    </a>
                </div>
            @endif
        @else
            <h2 class="scan-title">
                {{ $state['status'] === 'dipesan' ? 'Meja ini sudah dipesan' : 'Meja ini sedang dipakai' }}
            </h2>
            <p class="scan-text">
                Meja {{ $meja->nomor_meja }} bukan milik akun Anda. Silakan pilih meja lain yang masih kosong.
                Jika Anda merasa sudah memesan meja ini, pastikan login memakai akun yang sama
                atau tunjukkan kode booking Anda ke staf.
            </p>

            <div class="scan-actions">
                @auth
                    <a href="{{ route('pelanggan.dashboard') }}" class="scan-btn scan-btn-primary">
                        <i data-lucide="grid-3x3" class="h-4 w-4"></i> Lihat status semua meja
                    </a>
                @else
                    <a href="{{ route('login') }}" class="scan-btn scan-btn-primary">
                        <i data-lucide="log-in" class="h-4 w-4"></i> Masuk ke akun saya
                    </a>
                @endauth
            </div>

            @if ($state['bisa_verifikasi'])
                <div class="scan-verify">
                    <div class="scan-eyebrow">Tidak bisa login?</div>
                    <p class="scan-text" style="margin-top:6px;">
                        Masukkan kode booking 6 karakter yang Anda terima saat memesan meja ini.
                        Setelah cocok, meja akan dikenali sebagai milik Anda tanpa perlu login.
                    </p>
                    <form method="POST" action="{{ route('meja.verifikasi', ['meja' => $meja->qr_token]) }}">
                        @csrf
                        <label for="kode_booking" class="sr-only">Kode booking</label>
                        <input type="text" id="kode_booking" name="kode_booking"
                               class="scan-input" value="{{ old('kode_booking') }}"
                               maxlength="12" autocomplete="one-time-code" spellcheck="false"
                               placeholder="A1B2C3" required>
                        <button type="submit" class="scan-btn scan-btn-primary" style="margin-top:10px;">
                            <i data-lucide="key-round" class="h-4 w-4"></i> Verifikasi kode booking
                        </button>
                    </form>
                </div>
            @endif

            @if ($mejaKosong->isNotEmpty())
                <div class="scan-eyebrow" style="margin-top:22px;">Meja lain yang kosong</div>
                <ul class="scan-list">
                    @foreach ($mejaKosong as $alternatif)
                        <li>
                            <a href="{{ route('pelanggan.meja.scan', ['meja' => $alternatif->qr_token]) }}">
                                <span style="display:flex;align-items:center;gap:8px;">
                                    <i data-lucide="armchair" class="h-4 w-4" style="color:#E0A96D;"></i>
                                    Meja {{ $alternatif->nomor_meja }} · {{ $alternatif->kapasitas }} kursi
                                </span>
                                <span>Kosong</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="scan-foot">Belum ada meja kosong saat ini. Silakan hubungi staf kami.</p>
            @endif
        @endif

        <div class="scan-verify" data-pin
             data-minta-url="{{ route('meja.pin.minta', ['meja' => $meja->qr_token]) }}"
             data-status-url="{{ route('meja.pin.status', ['meja' => $meja->qr_token]) }}">
            <div class="scan-eyebrow">PIN dari Kasir</div>
            <p class="scan-text" style="margin-top:6px;">
                Tekan tombol di bawah, lalu kasir mengirim PIN 6 angka ke halaman ini.
                PIN hanya tampil <b>1 menit</b>; bila terlewat, tekan <b>Minta ulang</b>.
            </p>

            <div class="scan-actions">
                <button type="button" class="scan-btn scan-btn-primary" data-pin-minta>
                    <i data-lucide="bell-ring" class="h-4 w-4"></i> Minta PIN ke Kasir
                </button>
            </div>

            <div class="scan-code" data-pin-hasil hidden>
                <div>
                    <small>PIN meja Anda</small>
                    <strong data-pin-kode>------</strong>
                </div>
                <div style="text-align:right;">
                    <small>Berlaku</small>
                    <strong style="font-size:15px;letter-spacing:normal;" data-pin-sisa>1:00</strong>
                </div>
            </div>

            <p class="scan-foot" data-pin-pesan aria-live="polite" style="margin-top:10px;"></p>

            <div class="scan-actions" data-pin-ulang-wrap hidden>
                <button type="button" class="scan-btn scan-btn-ghost" data-pin-ulang>
                    <i data-lucide="refresh-cw" class="h-4 w-4"></i> Minta ulang PIN
                </button>
            </div>
        </div>

        <div class="scan-verify">
            <div class="scan-eyebrow">Sudah punya PIN?</div>
            <p class="scan-text" style="margin-top:6px;">
                Minta kode 6 angka ke kasir atau barista, lalu masukkan di sini.
                Kode berlaku {{ (int) round(\App\Services\MejaKodeService::TTL_DETIK / 60) }} menit,
                hanya bisa dipakai sekali, dan menjadi bukti kehadiran Anda tanpa perlu GPS.
            </p>

            {{-- Tamu tanpa akun juga boleh memakai PIN: PIN-lah bukti kehadirannya. --}}
            <form method="POST" action="{{ route('meja.kode.verifikasi', ['meja' => $meja->qr_token]) }}">
                @csrf
                <label for="kode_meja" class="sr-only">Kode dari kasir</label>
                <input type="text" id="kode_meja" name="kode_meja"
                       class="scan-input" value="{{ old('kode_meja') }}"
                       inputmode="numeric" maxlength="6" autocomplete="one-time-code"
                       placeholder="123456" required>
                <button type="submit" class="scan-btn scan-btn-primary" style="margin-top:10px;">
                    <i data-lucide="shield-check" class="h-4 w-4"></i> Verifikasi kode
                </button>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var box = document.querySelector('[data-pin]');
    if (!box) return;

    var mintaUrl = box.dataset.mintaUrl;
    var statusUrl = box.dataset.statusUrl;
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrf = csrfMeta ? csrfMeta.content : '';
    var btn = box.querySelector('[data-pin-minta]');
    var ulangWrap = box.querySelector('[data-pin-ulang-wrap]');
    var ulang = box.querySelector('[data-pin-ulang]');
    var hasil = box.querySelector('[data-pin-hasil]');
    var kodeEl = box.querySelector('[data-pin-kode]');
    var sisaEl = box.querySelector('[data-pin-sisa]');
    var pesan = box.querySelector('[data-pin-pesan]');
    var timer = null;
    var poller = null;
    var sisa = 0;

    function setPesan(teks) { pesan.textContent = teks; }

    function detik(teks) {
        sisaEl.textContent = Math.floor(teks / 60) + ':' + String(teks % 60).padStart(2, '0');
    }

    function mulaiHitung(detikAwal) {
        clearInterval(timer);
        sisa = Number(detikAwal) || 0;
        hasil.hidden = false;
        ulangWrap.hidden = true;
        detik(sisa);

        timer = setInterval(function () {
            sisa -= 1;

            if (sisa <= 0) {
                clearInterval(timer);
                hasil.hidden = true;
                ulangWrap.hidden = false;
                setPesan('PIN sudah kedaluwarsa. Tekan Minta ulang agar kasir mengirim PIN baru.');
                return;
            }

            detik(sisa);
        }, 1000);
    }

    function tampilkan(pin) {
        hentikanPolling();
        kodeEl.textContent = pin.kode;
        mulaiHitung(pin.sisa_detik);
        setPesan('PIN diterima. Masukkan PIN di kolom di bawah sebelum waktunya habis.');
    }

    function hentikanPolling() {
        if (poller) { clearInterval(poller); poller = null; }
    }

    function mulaiPolling() {
        if (poller) return;

        poller = setInterval(function () {
            fetch(statusUrl, { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    if (!data) return;

                    if (data.status === 'siap') {
                        tampilkan(data);
                    } else if (data.status === 'tidak_ada') {
                        hentikanPolling();
                        ulangWrap.hidden = false;
                        setPesan('Permintaan belum diproses kasir. Tekan Minta PIN lagi.');
                    }
                })
                .catch(function () { /* coba lagi di polling berikutnya */ });
        }, 2000);
    }

    function minta() {
        btn.disabled = true;
        ulang.disabled = true;
        setPesan('Menghubungi kasir…');

        fetch(mintaUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            cache: 'no-store',
        })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
            .then(function (res) {
                btn.disabled = false;
                ulang.disabled = false;

                if (!res.ok) {
                    setPesan(res.data.message || 'Gagal meminta PIN. Coba lagi.');
                    return;
                }

                ulangWrap.hidden = true;
                setPesan(res.data.message || 'Menunggu kasir mengirim PIN…');
                mulaiPolling();
            })
            .catch(function () {
                btn.disabled = false;
                ulang.disabled = false;
                setPesan('Jaringan bermasalah. Coba lagi.');
            });
    }

    btn.addEventListener('click', minta);
    ulang.addEventListener('click', minta);

    // Halaman dimuat ulang saat PIN masih berlaku: ambil lagi tanpa minta baru.
    fetch(statusUrl, { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) {
            if (!data) return;

            if (data.status === 'siap') {
                tampilkan(data);
            } else if (data.status === 'menunggu') {
                setPesan('Menunggu kasir mengirim PIN…');
                mulaiPolling();
            }
        })
        .catch(function () { /* diamkan */ });
})();
</script>

@include('pelanggan._nav')
@endsection
