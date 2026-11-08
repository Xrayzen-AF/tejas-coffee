<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Penjualan {{ $dari->format('d/m/Y') }} – {{ $sampai->format('d/m/Y') }} — {{ $toko['store_name'] ?? 'Tejas Coffee' }}</title>
    <style>
        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            background: #ECE7E1;
            color: #1F1A17;
            font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Toolbar layar (tidak ikut tercetak) ── */
        .toolbar {
            position: sticky;
            top: 0;
            z-index: 5;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            background: #1F1A17;
            border-bottom: 1px solid #3D322E;
            color: #F1DFD8;
        }
        .toolbar .spacer { flex: 1 1 auto; }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid #4A3C33;
            background: #2B221C;
            color: #F1DFD8;
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            white-space: nowrap;
            transition: background .15s, border-color .15s;
        }
        .btn:hover { background: #3A2E26; border-color: #5D4B3E; }
        .btn.primary { background: #C87A32; border-color: #C87A32; color: #1F1105; }
        .btn.primary:hover { background: #B26A28; border-color: #B26A28; }

        .periode {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            font-size: 11.5px;
            color: #CDBDB2;
        }
        .periode label { display: inline-flex; align-items: center; gap: 6px; }
        .periode input[type="date"], .periode select {
            border: 1px solid #4A3C33;
            background: #2B221C;
            color: #F1DFD8;
            border-radius: 6px;
            padding: 6px 8px;
            font: inherit;
            color-scheme: dark;
        }
        .quick { display: flex; flex-wrap: wrap; gap: 6px; }
        .quick a {
            font-size: 11px;
            color: #D8C7BA;
            text-decoration: none;
            border: 1px solid #4A3C33;
            border-radius: 999px;
            padding: 6px 10px;
        }
        .quick a:hover { background: #2B221C; color: #F1DFD8; }

        /* ── Kertas A4 ── */
        .paper {
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto 40px;
            padding: 14mm 15mm 16mm;
            background: #fff;
            color: #1F1A17;
            box-shadow: 0 14px 40px rgba(31, 26, 23, .28);
            font-size: 11px;
            line-height: 1.45;
            font-variant-numeric: tabular-nums;
        }

        .doc-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding-bottom: 10px;
            border-bottom: 2px solid #1F1A17;
        }
        .brand { display: flex; align-items: flex-start; gap: 10px; }
        .brand .logo { width: 42px; height: auto; display: block; filter: brightness(0); }
        .brand h1 { margin: 0; font-size: 15px; letter-spacing: .01em; }
        .brand p { margin: 1px 0 0; font-size: 10px; color: #5F544C; line-height: 1.4; }
        .doc-meta { text-align: right; }
        .doc-meta .label {
            display: block;
            font-size: 9.5px;
            letter-spacing: .18em;
            text-transform: uppercase;
            color: #8A6A4F;
        }
        .doc-meta strong { display: block; font-size: 13px; margin-top: 2px; }
        .doc-meta .muted { display: block; font-size: 9.5px; color: #6B5F57; margin-top: 3px; }

        .notice {
            margin-top: 10px;
            padding: 7px 10px;
            border: 1px solid #E3C08A;
            background: #FBF3E5;
            color: #7A5217;
            border-radius: 4px;
            font-size: 10.5px;
        }

        /* ── Ringkasan ── */
        .summary {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-top: 12px;
        }
        .stat {
            border: 1px solid #E2DAD2;
            border-radius: 5px;
            padding: 8px 10px;
            background: #FCFAF8;
            break-inside: avoid;
        }
        .stat .k { font-size: 9px; text-transform: uppercase; letter-spacing: .09em; color: #7C6C61; }
        .stat .v { font-size: 14px; font-weight: 700; margin-top: 3px; line-height: 1.2; }
        .stat .v small { font-size: 9.5px; font-weight: 500; color: #6B5F57; }
        .stat.accent { background: #1F1A17; border-color: #1F1A17; }
        .stat.accent .k { color: #C9B6A8; }
        .stat.accent .v { color: #fff; }

        h2 {
            font-size: 11px;
            letter-spacing: .08em;
            text-transform: uppercase;
            margin: 18px 0 6px;
            padding-bottom: 4px;
            border-bottom: 1px solid #1F1A17;
        }
        h2 span { float: right; font-size: 9px; letter-spacing: 0; text-transform: none; color: #6B5F57; font-weight: 500; }

        table { width: 100%; border-collapse: collapse; font-size: 10.5px; }
        thead th {
            text-align: left;
            font-size: 9px;
            letter-spacing: .07em;
            text-transform: uppercase;
            color: #6B5F57;
            padding: 4px 6px;
            border-bottom: 1px solid #C9BFB6;
            background: #F6F2EE;
        }
        tbody td { padding: 4px 6px; border-bottom: 1px solid #EDE7E1; vertical-align: top; }
        tbody tr:nth-child(even) td { background: #FBF9F7; }
        tfoot td {
            padding: 5px 6px;
            border-top: 1.5px solid #1F1A17;
            font-weight: 700;
            background: #F6F2EE;
        }
        .num { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .muted { color: #6B5F57; }
        .mono { font-family: ui-monospace, 'Courier New', monospace; font-size: 9.5px; }
        .tag {
            display: inline-block;
            border: 1px solid #DCD3CB;
            border-radius: 3px;
            padding: 0 4px;
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #5F544C;
        }
        .tag.cancel { border-color: #E0B4AE; color: #9B3B30; }
        .empty { padding: 10px 6px; color: #7C6C61; font-style: italic; }

        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

        /* ── Tanda tangan ── */
        .sign-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 26px;
            break-inside: avoid;
        }
        .sign { font-size: 10px; color: #5F544C; }
        .sign .line { height: 34px; }
        .sign .name {
            border-top: 1px solid #1F1A17;
            padding-top: 4px;
            color: #1F1A17;
            font-weight: 600;
        }
        .sign .role { font-size: 9px; color: #7C6C61; }

        .doc-foot {
            margin-top: 18px;
            padding-top: 8px;
            border-top: 1px solid #E2DAD2;
            font-size: 9px;
            color: #8A7D74;
            text-align: center;
        }

        /* ── Cetak ── */
        @page { size: A4 portrait; margin: 10mm 12mm; }
        @media print {
            html, body { background: #fff; }
            .no-print { display: none !important; }
            .paper {
                width: auto;
                min-height: 0;
                margin: 0;
                padding: 0;
                box-shadow: none;
                font-size: 10px;
            }
            thead { display: table-header-group; }
            tr, .stat, .sign-row { break-inside: avoid; page-break-inside: avoid; }
            .summary { grid-template-columns: repeat(4, 1fr); }
        }
        @media (max-width: 720px) {
            .paper { width: 100%; min-height: 0; margin: 0; box-shadow: none; padding: 16px 14px 28px; }
            .summary { grid-template-columns: repeat(2, 1fr); }
            .two-col { grid-template-columns: 1fr; }
            .doc-head { flex-direction: column; }
            .doc-meta { text-align: left; }
        }
    </style>
</head>
<body>
@php
    $idr = fn ($n) => number_format((float) $n, 0, ',', '.');
    $omzet = (float) ($ringkasan->omzet ?? 0);
    $jumlahTransaksi = (int) ($ringkasan->transaksi ?? 0);
    $rataRata = $jumlahTransaksi > 0 ? $omzet / $jumlahTransaksi : 0;
    $pct = fn ($n) => $omzet > 0 ? number_format(((float) $n / $omzet) * 100, 1, ',', '.') . '%' : '0%';
    $omzetProduk = (float) $perProduk->sum('pendapatan');
    $pctProduk = fn ($n) => $omzetProduk > 0 ? number_format(((float) $n / $omzetProduk) * 100, 1, ',', '.') . '%' : '0%';
    $tipeLabel = fn ($t) => match ($t) {
        'dine-in' => 'Dine-in',
        'takeaway' => 'Takeaway',
        default => 'Delivery',
    };
    $filterAktif = array_filter(['tipe' => $tipe, 'metode' => $metode]);
    $labelMetode = $metode ? ($daftarMetode->firstWhere('id_metode', $metode)?->nama_metode ?? '-') : null;
@endphp

<div class="toolbar no-print">
    <a href="{{ route('admin.sales.index') }}" class="btn">&larr; Kembali</a>

    <form class="periode" method="GET" action="{{ route('admin.sales.cetak') }}">
        <label>Dari <input type="date" name="dari" value="{{ $dari->toDateString() }}"></label>
        <label>Sampai <input type="date" name="sampai" value="{{ $sampai->toDateString() }}"></label>
        <label>Tipe
            <select name="tipe">
                <option value="" {{ $tipe ? '' : 'selected' }}>Semua tipe</option>
                @foreach (['dine-in' => 'Dine-in', 'takeaway' => 'Takeaway', 'delivery' => 'Delivery'] as $nilai => $label)
                    <option value="{{ $nilai }}" {{ $tipe === $nilai ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label>Metode
            <select name="metode">
                <option value="" {{ $metode ? '' : 'selected' }}>Semua metode</option>
                @foreach ($daftarMetode as $m)
                    <option value="{{ $m->id_metode }}" {{ (int) $metode === (int) $m->id_metode ? 'selected' : '' }}>{{ $m->nama_metode }}</option>
                @endforeach
            </select>
        </label>
        <button type="submit" class="btn">Terapkan</button>
    </form>

    <div class="quick">
        <a href="{{ route('admin.sales.cetak', array_merge(['dari' => today()->toDateString(), 'sampai' => today()->toDateString()], $filterAktif)) }}">Hari Ini</a>
        <a href="{{ route('admin.sales.cetak', array_merge(['dari' => now()->subDays(6)->toDateString(), 'sampai' => today()->toDateString()], $filterAktif)) }}">7 Hari</a>
        <a href="{{ route('admin.sales.cetak', array_merge(['dari' => now()->subDays(29)->toDateString(), 'sampai' => today()->toDateString()], $filterAktif)) }}">30 Hari</a>
        <a href="{{ route('admin.sales.cetak', array_merge(['dari' => now()->startOfMonth()->toDateString(), 'sampai' => today()->toDateString()], $filterAktif)) }}">Bulan Ini</a>
    </div>

    <span class="spacer"></span>
    <a class="btn" href="{{ route('admin.sales.cetak.csv', array_merge(['dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString()], $filterAktif)) }}">Ekspor CSV</a>
    <button type="button" class="btn primary" onclick="window.print()">Cetak / Simpan PDF</button>
</div>

<main class="paper">
    <header class="doc-head">
        <div class="brand">
            <img class="logo" src="{{ asset('images/logo-tejas.png') }}" alt="{{ $toko['store_name'] ?? 'Tejas Coffee' }}">
            <div>
                <h1>{{ $toko['store_name'] ?? 'Tejas Coffee' }}</h1>
                <p>{{ $toko['branch'] ?? '' }}</p>
                <p>{{ $toko['address'] ?? '' }}</p>
                @if (! empty($toko['phone']))
                    <p>Tel. {{ $toko['phone'] }}</p>
                @endif
            </div>
        </div>
        <div class="doc-meta">
            <span class="label">Laporan Penjualan</span>
            <strong>{{ $dari->translatedFormat('d M Y') }} — {{ $sampai->translatedFormat('d M Y') }}</strong>
            @if ($filterAktif)
                <span class="muted">
                    Filter: {{ $tipe ? $tipeLabel($tipe) : 'Semua tipe' }} · {{ $labelMetode ?? 'Semua metode' }}
                </span>
            @endif
            <span class="muted">Dicetak {{ $dicetakPada->format('d/m/Y H:i') }} · {{ $dicetakOleh }}</span>
        </div>
    </header>

    @if ($catatan)
        <div class="notice">{{ $catatan }}</div>
    @endif

    <section class="summary">
        <div class="stat accent">
            <div class="k">Total Omzet</div>
            <div class="v">Rp {{ $idr($omzet) }}</div>
        </div>
        <div class="stat">
            <div class="k">Transaksi Lunas</div>
            <div class="v">{{ number_format($jumlahTransaksi, 0, ',', '.') }} <small>transaksi</small></div>
        </div>
        <div class="stat">
            <div class="k">Item Terjual</div>
            <div class="v">{{ number_format($itemTerjual, 0, ',', '.') }} <small>item</small></div>
        </div>
        <div class="stat">
            <div class="k">Rata-rata / Transaksi</div>
            <div class="v">Rp {{ $idr($rataRata) }}</div>
        </div>
        <div class="stat">
            <div class="k">Total Diskon</div>
            <div class="v">Rp {{ $idr($ringkasanPesanan->diskon ?? 0) }}</div>
        </div>
        <div class="stat">
            <div class="k">Biaya Layanan</div>
            <div class="v">Rp {{ $idr($ringkasanPesanan->layanan ?? 0) }}</div>
        </div>
        <div class="stat">
            <div class="k">Uang Diterima</div>
            <div class="v">Rp {{ $idr($ringkasan->diterima ?? 0) }} <small>/ kembali Rp {{ $idr($ringkasan->kembalian ?? 0) }}</small></div>
        </div>
        <div class="stat">
            <div class="k">Pesanan Dibatalkan</div>
            <div class="v">{{ number_format((int) ($batal->jumlah ?? 0), 0, ',', '.') }} <small>Rp {{ $idr($batal->nilai ?? 0) }}</small></div>
        </div>
    </section>

    <section class="two-col">
        <div>
            <h2>Rekap Metode Pembayaran</h2>
            <table>
                <thead>
                    <tr>
                        <th>Metode</th>
                        <th class="num">Trx</th>
                        <th class="num">Omzet</th>
                        <th class="num">Porsi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($perMetode as $m)
                        <tr>
                            <td>{{ $m->nama_metode }}</td>
                            <td class="num">{{ $m->transaksi }}</td>
                            <td class="num">{{ $idr($m->omzet) }}</td>
                            <td class="num muted">{{ $pct($m->omzet) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty">Belum ada transaksi lunas pada periode ini.</td></tr>
                    @endforelse
                </tbody>
                @if ($perMetode->isNotEmpty())
                    <tfoot>
                        <tr>
                            <td>Total</td>
                            <td class="num">{{ number_format($jumlahTransaksi, 0, ',', '.') }}</td>
                            <td class="num">{{ $idr($omzet) }}</td>
                            <td class="num">100%</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        <div>
            <h2>Rekap Harian</h2>
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th class="num">Trx</th>
                        <th class="num">Omzet</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($perHari as $h)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($h->tanggal)->translatedFormat('d M Y') }}</td>
                            <td class="num">{{ $h->transaksi }}</td>
                            <td class="num">{{ $idr($h->omzet) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty">Belum ada transaksi lunas pada periode ini.</td></tr>
                    @endforelse
                </tbody>
                @if ($perHari->isNotEmpty())
                    <tfoot>
                        <tr>
                            <td>Total</td>
                            <td class="num">{{ number_format($perHari->sum('transaksi'), 0, ',', '.') }}</td>
                            <td class="num">{{ $idr($perHari->sum('omzet')) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </section>

    <h2>Produk Terjual <span>{{ $perProduk->count() }} produk · diurutkan dari omzet terbesar</span></h2>
    <table>
        <thead>
            <tr>
                <th class="center" style="width:26px;">#</th>
                <th>Produk</th>
                <th class="num">Qty</th>
                <th class="num">Omzet</th>
                <th class="num">Porsi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($perProduk as $i => $p)
                <tr>
                    <td class="center muted">{{ $i + 1 }}</td>
                    <td>{{ $p->nama_produk }}</td>
                    <td class="num">{{ number_format((int) $p->cups, 0, ',', '.') }}</td>
                    <td class="num">{{ $idr($p->pendapatan) }}</td>
                    <td class="num muted">{{ $pctProduk($p->pendapatan) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">Belum ada produk terjual pada periode ini.</td></tr>
            @endforelse
        </tbody>
        @if ($perProduk->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="2">Total</td>
                    <td class="num">{{ number_format($itemTerjual, 0, ',', '.') }}</td>
                    <td class="num">{{ $idr($perProduk->sum('pendapatan')) }}</td>
                    <td class="num">100%</td>
                </tr>
            </tfoot>
        @endif
    </table>

    <h2>Rincian Transaksi <span>{{ number_format($daftarTransaksi->count(), 0, ',', '.') }} transaksi lunas</span></h2>
    <table>
        <thead>
            <tr>
                <th class="center" style="width:26px;">#</th>
                <th style="width:64px;">Waktu</th>
                <th>No. Pesanan</th>
                <th style="width:58px;">Tipe</th>
                <th class="center" style="width:40px;">Meja</th>
                <th>Kasir / Pelayan</th>
                <th>Metode</th>
                <th class="num" style="width:72px;">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($daftarTransaksi as $i => $t)
                @php
                    $pelayan = ($t->pesanan?->pengguna?->peran ?? null) === 'pelanggan'
                        ? 'Self Order'
                        : ($t->pesanan?->pengguna?->nama_lengkap ?? '-');
                @endphp
                <tr>
                    <td class="center muted">{{ $i + 1 }}</td>
                    <td class="mono">{{ $t->waktu_pembayaran?->format('d/m H:i') ?? '-' }}</td>
                    <td class="mono">{{ $t->pesanan?->nomor_pesanan ?? '-' }}</td>
                    <td>{{ $tipeLabel($t->pesanan?->tipe_pesanan ?? '-') }}</td>
                    <td class="center">{{ $t->pesanan?->meja?->nomor_meja ?? '—' }}</td>
                    <td>{{ $pelayan }}</td>
                    <td>{{ $t->metodePembayaran?->nama_metode ?? '-' }}</td>
                    <td class="num">{{ $idr($t->subtotal) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">Tidak ada transaksi lunas yang cocok dengan periode/filter ini.</td></tr>
            @endforelse
        </tbody>
        @if ($daftarTransaksi->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="7">Total omzet (nilai tagihan)</td>
                    <td class="num">{{ $idr($daftarTransaksi->sum('subtotal')) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    <div class="sign-row">
        <div class="sign">
            <div class="line"></div>
            <div class="name">{{ $dicetakOleh }}</div>
            <div class="role">Dibuat oleh</div>
        </div>
        <div class="sign">
            <div class="line"></div>
            <div class="name">&nbsp;</div>
            <div class="role">Disetujui oleh</div>
        </div>
    </div>

    <p class="doc-foot">
        Sumber: transaksi berstatus lunas · Omzet dihitung dari nilai tagihan (subtotal), di luar kembalian tunai.
        @if (! empty($toko['store_name']))
            · {{ $toko['store_name'] }}
        @endif
        · Dicetak otomatis oleh sistem, tidak memerlukan tanda tangan basah.
    </p>
</main>
</body>
</html>
