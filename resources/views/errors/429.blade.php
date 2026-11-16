<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terlalu Banyak Percobaan — Tejas Coffee</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 24px; background: radial-gradient(ellipse at 50% -10%, #2F231D 0%, #1E1511 55%, #17100D 100%);
            color: #F1DFD8; font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
        }
        .box {
            width: 100%; max-width: 440px; background: #271D19; border: 1px solid #3D322E;
            border-radius: 16px; padding: 26px 22px; text-align: center;
            box-shadow: 0 20px 48px rgba(0,0,0,.55);
        }
        .code { font-size: 12px; font-weight: 700; letter-spacing: .22em; text-transform: uppercase; color: #9D8E87; }
        h1 { font-family: 'Lora', ui-serif, Georgia, serif; font-size: 22px; margin: 10px 0 8px; }
        p { font-size: 13.5px; line-height: 1.65; color: #B8A8A1; margin: 0 0 18px; }
        a, button {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; padding: 13px 16px; border-radius: 10px; border: 0; cursor: pointer;
            background: #D97706; color: #241708; font: inherit; font-size: 14px; font-weight: 700;
            text-decoration: none;
        }
        a:hover, button:hover { background: #F59E0B; }
    </style>
</head>
<body>
    <main class="box">
        <div class="code">429 · Terlalu Banyak Percobaan</div>
        <h1>Tunggu sebentar, ya</h1>
        <p>
            Terlalu banyak percobaan dalam waktu singkat. Demi keamanan, permintaan
            ditahan sementara. Tunggu sekitar satu menit, lalu coba lagi.
        </p>
        <a href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}">Kembali &amp; coba lagi</a>
    </main>
</body>
</html>
