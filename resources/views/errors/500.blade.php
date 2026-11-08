{{--
    S3-02: Halaman error kustom (fail-safe error display).
    Dipakai otomatis oleh Laravel saat APP_DEBUG=false dan terjadi error 500,
    sehingga stack trace tidak pernah tampil ke pengguna umum.

    Sengaja tanpa @vite dan tanpa CDN agar tetap tampil walau server aset
    bermasalah. Semua gaya ditulis inline di bawah ini.
--}}
@php
    // Kode insiden dipakai pengguna saat melapor; detail teknisnya ada di storage/logs/laravel.log.
    $kodeInsiden = 'TJS-ERR-'.now()->format('YmdHis');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>500 — Terjadi Kendala Sistem | Tejas Coffee</title>
    <style>
        :root {
            --canvas: #1E1511;
            --card: #271D19;
            --line: #3D322E;
            --ink: #F1DFD8;
            --ink-muted: #B8A8A1;
            --primary: #D97706;
            --primary-ink: #241708;
            --danger: #F87171;
            --danger-soft: rgba(248, 113, 113, .12);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: var(--canvas);
            color: var(--ink);
            font-family: Inter, ui-sans-serif, system-ui, "Segoe UI", sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .panel {
            width: 100%;
            max-width: 440px;
            padding: 32px 28px;
            text-align: center;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 16px;
            box-shadow: 0 30px 60px -30px rgba(0, 0, 0, .65);
        }

        .icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--danger-soft);
            border: 1px solid rgba(248, 113, 113, .35);
            color: var(--danger);
        }

        .icon svg { width: 30px; height: 30px; }

        h1 {
            margin: 0;
            font-family: Lora, ui-serif, Georgia, serif;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: .01em;
        }

        .subtitle {
            margin: 8px 0 0;
            font-size: 13px;
            line-height: 1.7;
            color: var(--ink-muted);
        }

        .incident {
            margin: 20px 0 0;
            padding: 12px 14px;
            text-align: left;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 11px;
            line-height: 1.8;
            color: var(--ink-muted);
            background: rgba(0, 0, 0, .22);
            border: 1px solid var(--line);
            border-radius: 10px;
            word-break: break-word;
        }

        .incident strong { color: var(--ink); font-weight: 600; }

        .actions {
            margin-top: 22px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .btn {
            display: block;
            padding: 11px 16px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            border-radius: 8px;
            cursor: pointer;
            transition: filter .16s ease, border-color .16s ease;
        }

        .btn-primary {
            background: var(--primary);
            color: var(--primary-ink);
        }

        .btn-primary:hover { filter: brightness(1.06); }

        .btn-ghost {
            background: transparent;
            color: var(--ink);
            border: 1px solid var(--line);
        }

        .btn-ghost:hover { border-color: #5f4b44; }

        .footnote {
            margin: 18px 0 0;
            font-size: 11px;
            color: #9D8E87;
        }
    </style>
</head>
<body>
    <main class="panel" role="alert">
        <div class="icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
        </div>

        <h1>Mohon Maaf, Terjadi Kendala</h1>
        <p class="subtitle">
            Sistem kami sedang mengalami gangguan sementara. Tim teknis telah menerima
            laporan ini secara otomatis. Silakan coba beberapa saat lagi.
        </p>

        <div class="incident">
            Kode Insiden: <strong>{{ $kodeInsiden }}</strong><br>
            Status: Terisolasi Aman — data pesanan Anda tidak hilang
        </div>

        <div class="actions">
            <a href="{{ url('/') }}" class="btn btn-primary">Kembali ke Beranda</a>
            <a href="{{ url()->current() }}" class="btn btn-ghost">Muat Ulang Halaman</a>
        </div>

        <p class="footnote">Tejas Coffee &middot; Kode status 500</p>
    </main>
</body>
</html>
