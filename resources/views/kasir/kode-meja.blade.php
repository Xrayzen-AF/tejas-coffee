@extends('layouts.app')

@section('title', 'Kode & PIN Meja — Tejas Coffee')

@section('content')
<style>
  .km-wrap { width: 100%; max-width: 1040px; margin: 0 auto; padding: 20px 16px 96px; }
  .km-head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 12px; margin-bottom: 18px; }
  .km-eyebrow { font-size: 10px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: #9D8E87; }
  .km-title { font-family: 'Lora', ui-serif, Georgia, serif; font-size: 26px; color: #F1DFD8; margin: 4px 0 0; }
  .km-sub { font-size: 13px; line-height: 1.65; color: #B8A8A1; max-width: 620px; margin-top: 6px; }
  .km-back { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: #B8A8A1; text-decoration: none; }
  .km-back:hover { color: #F1DFD8; }
  .km-summary { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 18px; }
  .km-chip { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border: 1px solid #3D322E; border-radius: 999px; background: #221A15; font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #B8A8A1; }
  .km-chip b { color: #F1DFD8; }
  .km-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 12px; }
  .km-card { background: #271D19; border: 1px solid #3D322E; border-radius: 12px; padding: 16px; display: flex; flex-direction: column; }
  .km-card.has-kode { border-color: #6B4A1F; }
  .km-card-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; }
  .km-no { font-family: 'Lora', ui-serif, Georgia, serif; font-size: 26px; color: #F1DFD8; line-height: 1.1; }
  .km-status { display: inline-flex; align-items: center; gap: 5px; margin-top: 6px; padding: 3px 8px; border-radius: 999px; font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; background: #2F231D; color: #B8A8A1; }
  .km-status[data-status="kosong"] { background: rgba(134, 224, 164, .12); color: #86E0A4; }
  .km-status[data-status="terisi"] { background: rgba(217, 119, 6, .16); color: #F59E0B; }
  .km-status[data-status="dipesan"] { background: rgba(96, 165, 250, .14); color: #93C5FD; }
  .km-meta { font-size: 11px; color: #82746E; margin-top: 4px; }
  .km-pin-req { display: inline-flex; align-items: center; gap: 4px; margin-top: 6px; padding: 3px 8px; border-radius: 999px; font-size: 10px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; background: rgba(217,119,6,.2); color: #F6C89A; animation: kmpulse 1.6s ease-in-out infinite; }
  .km-pin-req[hidden] { display: none; }
  @keyframes kmpulse { 0%, 100% { opacity: 1; } 50% { opacity: .45; } }
  .km-out { margin-top: 14px; padding: 12px; border: 1px dashed #4A3B35; border-radius: 8px; background: #221A15; }
  .km-out small { display: block; font-size: 10px; letter-spacing: .1em; text-transform: uppercase; color: #9D8E87; }
  .km-kode { font-family: 'Lora', ui-serif, Georgia, serif; font-size: 30px; letter-spacing: .18em; color: #F6C89A; font-variant-numeric: tabular-nums; min-height: 38px; line-height: 1.2; }
  .km-kode.muted { color: #5B4E48; letter-spacing: .12em; }
  .km-sisa { font-size: 12px; font-weight: 700; color: #86E0A4; font-variant-numeric: tabular-nums; }
  .km-sisa.muted { color: #82746E; font-weight: 600; }
  .km-actions { display: flex; gap: 8px; margin-top: 12px; }
  .km-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; flex: 1; padding: 10px 12px; border-radius: 8px; border: 1px solid transparent; background: #D97706; color: #241708; font-family: inherit; font-size: 13px; font-weight: 700; cursor: pointer; transition: .15s; }
  .km-btn:hover { background: #F59E0B; }
  .km-btn:disabled { opacity: .55; cursor: progress; }
  .km-btn.ghost { flex: 0 0 auto; background: transparent; border-color: #4A3B35; color: #B8A8A1; }
  .km-btn.ghost:hover { border-color: #6B4A1F; color: #F1DFD8; }
  .km-btn.ghost:disabled { opacity: .4; }
  .km-err { display: none; margin-top: 10px; font-size: 12px; color: #FCA5A5; }
  .km-err.show { display: block; }
  .km-note { margin-top: 20px; font-size: 11px; line-height: 1.7; color: #82746E; max-width: 760px; }
</style>

<div class="km-wrap" data-km-root data-status-url="{{ route('meja-kode.status') }}">
    <div class="km-head">
        <div>
            <div class="km-eyebrow">Verifikasi Kehadiran</div>
            <h1 class="km-title">PIN &amp; Kode Sekali Pakai per Meja</h1>
            <p class="km-sub">
                Pilih meja lalu tekan <strong>Buat PIN</strong>. PIN 6 angka tampil di layar ini dan
                dibacakan ke tamu di meja tersebut. PIN berlaku {{ (int) round($ttlDetik / 60) }} menit,
                sekali pakai, dan maksimal {{ $maksPercobaan }} percobaan. Membuat PIN baru otomatis
                membatalkan PIN sebelumnya.
            </p>
        </div>
        <a href="{{ route('kasir.dashboard') }}" class="km-back">
            <i data-lucide="arrow-left" class="h-4 w-4"></i> Kembali ke Kasir
        </a>
    </div>

    <div class="km-summary">
        <span class="km-chip">Meja <b data-sum-total>{{ $daftarMeja->count() }}</b></span>
        <span class="km-chip">Kosong <b data-sum-kosong>{{ $daftarMeja->where('status', 'kosong')->count() }}</b></span>
        <span class="km-chip">PIN aktif <b data-sum-aktif>{{ $kodeAktif->filter()->count() }}</b></span>
    </div>

    @if ($errors->any())
        <div class="km-sub" style="color:#FCA5A5;">{{ $errors->first() }}</div>
    @endif

    @if ($daftarMeja->isEmpty())
        <p class="km-sub">Belum ada meja terdaftar. Tambahkan meja di Admin → Master → Meja.</p>
    @else
        <div class="km-grid">
            @foreach ($daftarMeja as $meja)
                @php($sisa = $kodeAktif[$meja->id_meja] ?? null)
                <div class="km-card {{ $sisa ? 'has-kode' : '' }}"
                     data-meja-card
                     data-id="{{ $meja->id_meja }}"
                     data-sisa="{{ $sisa ?? 0 }}"
                     data-terbitkan-url="{{ route('meja-kode.terbitkan', ['meja' => $meja->id_meja]) }}"
                     data-batalkan-url="{{ route('meja-kode.batalkan', ['meja' => $meja->id_meja]) }}">
                    <div class="km-card-top">
                        <div>
                            <div class="km-no">Meja {{ $meja->nomor_meja }}</div>
                            <span class="km-status" data-status="{{ $meja->status }}">{{ ucfirst($meja->status) }}</span>
                            <div class="km-meta">{{ $meja->kapasitas }} kursi</div>
                            <div><span class="km-pin-req" data-pin-req hidden>Minta PIN</span></div>
                        </div>
                    </div>

                    <div class="km-out">
                        <small>PIN untuk tamu</small>
                        <div class="km-kode muted" data-kode>&bull;&bull;&bull;&bull;&bull;&bull;</div>
                        <div class="km-sisa {{ $sisa ? '' : 'muted' }}" data-sisa>{{ $sisa ? 'PIN aktif &middot; '.gmdate('i:s', $sisa) : 'Belum ada PIN aktif' }}</div>
                    </div>

                    <div class="km-actions">
                        <button type="button" class="km-btn" data-terbitkan>
                            <i data-lucide="key-round" class="h-4 w-4"></i> <span data-terbitkan-label>Buat PIN</span>
                        </button>
                        <button type="button" class="km-btn ghost" data-batalkan
                                title="Batalkan PIN meja ini" {{ $sisa ? '' : 'disabled' }}>
                            <i data-lucide="x" class="h-4 w-4"></i>
                        </button>
                        <button type="button" class="km-btn ghost" data-copy
                                title="Salin PIN" disabled>
                            <i data-lucide="copy" class="h-4 w-4"></i>
                        </button>
                    </div>

                    <div class="km-err" data-err></div>
                </div>
            @endforeach
        </div>
    @endif

    <p class="km-note">
        PIN hanya bisa dilihat sekali saat dibuat — demi keamanan, server hanya menyimpan hash-nya.
        Kalau PIN terlanjur hilang, tekan <strong>Buat PIN</strong> lagi untuk menerbitkan yang baru.
        Tamu memindai QR di meja, memasukkan PIN, lalu bisa memesan tanpa izin lokasi.
    </p>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const root = document.querySelector('[data-km-root]');
    if (! root) { return; }

    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const statusUrl = root.dataset.statusUrl;
    const cards = Array.from(document.querySelectorAll('[data-meja-card]'));

    const sumKosong = root.querySelector('[data-sum-kosong]');
    const sumAktif = root.querySelector('[data-sum-aktif]');

    function mmss(detik) {
        const m = Math.floor(detik / 60);
        const s = detik % 60;
        return m + ':' + String(s).padStart(2, '0');
    }

    function setStatus(card, status) {
        const el = card.querySelector('[data-status]');
        const normalized = String(status || '').toLowerCase();
        el.dataset.status = normalized;
        el.textContent = normalized.charAt(0).toUpperCase() + normalized.slice(1);
    }

    function refreshSummary() {
        if (sumKosong) {
            sumKosong.textContent = String(cards.filter(function (c) {
                return c.querySelector('[data-status]').dataset.status === 'kosong';
            }).length);
        }
        if (sumAktif) {
            sumAktif.textContent = String(cards.filter(function (c) {
                return Number(c.dataset.sisa) > 0;
            }).length);
        }
    }

    cards.forEach(function (card) {
        const btn = card.querySelector('[data-terbitkan]');
        const btnLabel = card.querySelector('[data-terbitkan-label]');
        const btnBatal = card.querySelector('[data-batalkan]');
        const btnCopy = card.querySelector('[data-copy]');
        const kodeEl = card.querySelector('[data-kode]');
        const sisaEl = card.querySelector('[data-sisa]');
        const errEl = card.querySelector('[data-err]');
        let timer = null;

        function stop() {
            if (timer) { clearInterval(timer); timer = null; }
        }

        function showError(message) {
            errEl.textContent = message;
            errEl.classList.add('show');
        }

        function clearError() {
            errEl.textContent = '';
            errEl.classList.remove('show');
        }

        function applySisa(seconds) {
            card.dataset.sisa = String(seconds);
            if (seconds > 0) {
                card.classList.add('has-kode');
                btnBatal.disabled = false;
                sisaEl.classList.remove('muted');
                sisaEl.textContent = 'PIN aktif · ' + mmss(seconds);
            } else {
                card.classList.remove('has-kode');
                btnBatal.disabled = true;
                sisaEl.classList.add('muted');
                sisaEl.textContent = 'Belum ada PIN aktif';
            }
        }

        function startCountdown(seconds) {
            stop();
            applySisa(seconds);

            timer = setInterval(function () {
                seconds -= 1;

                if (seconds <= 0) {
                    stop();
                    applySisa(0);
                    kodeEl.textContent = '••••••';
                    kodeEl.classList.add('muted');
                    btnCopy.disabled = true;
                    btnLabel.textContent = 'Buat PIN';
                    refreshSummary();
                    return;
                }

                applySisa(seconds);
            }, 1000);
        }

        btn.addEventListener('click', async function () {
            stop();
            clearError();
            btn.disabled = true;
            btnLabel.textContent = 'Membuat…';

            try {
                const res = await fetch(card.dataset.terbitkanUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                });

                if (! res.ok) {
                    throw new Error(res.status === 419
                        ? 'Sesi kedaluwarsa, muat ulang halaman.'
                        : 'Gagal membuat PIN. Coba lagi.');
                }

                const data = await res.json();
                kodeEl.textContent = data.kode;
                kodeEl.classList.remove('muted');
                btnCopy.disabled = false;
                btnLabel.textContent = 'Buat ulang';
                startCountdown(Number(data.kedaluwarsa_detik) || 0);
                refreshSummary();
            } catch (err) {
                showError(err.message);
                btnLabel.textContent = 'Buat PIN';
            } finally {
                btn.disabled = false;
            }
        });

        btnBatal.addEventListener('click', async function () {
            clearError();
            btnBatal.disabled = true;

            try {
                const res = await fetch(card.dataset.batalkanUrl, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                });

                if (! res.ok) {
                    throw new Error(res.status === 419
                        ? 'Sesi kedaluwarsa, muat ulang halaman.'
                        : 'Gagal membatalkan PIN.');
                }

                stop();
                applySisa(0);
                kodeEl.textContent = '••••••';
                kodeEl.classList.add('muted');
                btnCopy.disabled = true;
                btnLabel.textContent = 'Buat PIN';
                refreshSummary();
            } catch (err) {
                showError(err.message);
                btnBatal.disabled = false;
            }
        });

        btnCopy.addEventListener('click', async function () {
            const kode = kodeEl.textContent.trim();

            if (! /^\d{6}$/.test(kode)) { return; }

            try {
                await navigator.clipboard.writeText(kode);
                const old = btnCopy.innerHTML;
                btnCopy.innerHTML = '<i data-lucide="check" class="h-4 w-4"></i>';
                setTimeout(function () { btnCopy.innerHTML = old; }, 1200);
            } catch (err) {
                showError('Tidak bisa menyalin otomatis. Bacakan PIN-nya langsung.');
            }
        });
    });

    async function poll() {
        if (document.hidden) { return; }

        try {
            const res = await fetch(statusUrl, { headers: { 'Accept': 'application/json' } });
            if (! res.ok) { return; }

            const data = await res.json();
            const byId = {};

            (data.tables || []).forEach(function (t) { byId[t.id_meja] = t; });

            cards.forEach(function (card) {
                const data = byId[card.dataset.id];
                if (! data) { return; }

                setStatus(card, data.status);

                // Penanda pelanggan yang menunggu PIN untuk meja ini.
                const req = card.querySelector('[data-pin-req]');
                if (req) {
                    const detik = data.permintaan_detik;

                    if (detik !== null && detik !== undefined) {
                        req.hidden = false;
                        req.textContent = 'Minta PIN · ' + detik + 's';

                        const kodeTampil = card.querySelector('[data-kode]');
                        if (kodeTampil && kodeTampil.classList.contains('muted')) {
                            const label = card.querySelector('[data-terbitkan-label]');
                            if (label) label.textContent = 'Kirim PIN';
                        }
                    } else {
                        req.hidden = true;
                    }
                }

                // Jangan ganggu PIN yang sedang ditampilkan di kartu ini.
                if (card.querySelector('[data-kode]').classList.contains('muted')) {
                    applySisa(Number(data.sisa_detik) || 0);
                }
            });

            refreshSummary();
        } catch (err) {
            // Diamkan: polling berikutnya akan mencoba lagi.
        }
    }

    setInterval(poll, 20000);
})();
</script>
@endpush
