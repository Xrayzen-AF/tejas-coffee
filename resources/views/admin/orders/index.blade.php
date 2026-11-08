@extends('layouts.admin', ['activeMenu' => 'orders'])

@section('title', 'Manajemen Pesanan — Tejas Coffee')
@section('header-title', 'Manajemen Pesanan')
@section('header-subtitle', 'Tabel transaksi — kelola status & batalkan, isi pesanan tidak diedit manual')

@section('content')
  @if (session('error'))
    <div class="alert-error">{{ session('error') }}</div>
  @endif

  <section class="metrics-grid" style="grid-template-columns: repeat(4, 1fr);">
    @foreach ($statuses as $s)
      <a href="{{ route('admin.orders.index', ['status' => $s]) }}" class="metric-card" style="text-decoration:none;color:inherit;">
        <div class="metric-header"><span>{{ ucfirst($s) }}</span><div class="metric-icon"><i data-lucide="{{ $s === 'baru' ? 'inbox' : ($s === 'diproses' ? 'flame' : ($s === 'selesai' ? 'check-circle-2' : 'x-circle')) }}" size="16"></i></div></div>
        <div class="metric-value" @if($s === 'dibatalkan') style="color:var(--accent-alert);" @endif>{{ $hitung[$s] }}</div>
        <div class="metric-sub">{{ $statusAktif === $s ? 'Filter aktif — klik untuk reset' : 'Klik untuk filter' }}</div>
      </a>
    @endforeach
  </section>

  <section class="card" style="margin-top:20px;">
    <div class="card-title-row">
      <div><h3>Daftar Pesanan</h3><p>State machine: baru → diproses → selesai (dibatalkan kapan saja selama belum lunas/selesai)</p></div>
      @if ($statusAktif)
        <a href="{{ route('admin.orders.index') }}" class="btn-sm"><i data-lucide="x" size="13"></i> Reset filter {{ $statusAktif }}</a>
      @endif
    </div>

    {{-- Desktop (≥768px): tabel lebar standar. Di layar kecil tabel
         disembunyikan dan diganti kartu bertumpuk di bawah. --}}
    <div class="table-wrap hidden md:block">
      <table class="ops-table">
        <thead><tr><th>Pesanan</th><th>Item</th><th>Meja</th><th>Total</th><th>Bayar</th><th>Status</th><th style="text-align:right;">Aksi</th></tr></thead>
        <tbody>
        @forelse ($pesanan as $p)
          <tr>
            <td>
              <div class="emp-name">{{ $p->nomor_pesanan }}</div>
              <div class="emp-email">{{ $p->waktu_pesan->translatedFormat('d M Y H:i') }} • {{ ucfirst($p->tipe_pesanan) }}</div>
            </td>
            <td style="max-width:240px;">
              <div class="emp-name">{{ $p->detailPesanan->count() }} item</div>
              <div class="emp-email">
                {{ $p->detailPesanan->map(fn ($d) => $d->jumlah . '× ' . ($d->produk->nama_produk ?? '#'.$d->id_produk))->implode(', ') }}
              </div>
            </td>
            <td>{{ $p->meja?->nomor_meja ?? '—' }}</td>
            <td style="white-space:nowrap;">
              <strong>Rp {{ number_format($p->total_harga, 0, ',', '.') }}</strong>
              @if ($p->diskon > 0)
                <div class="emp-email">diskon Rp {{ number_format($p->diskon, 0, ',', '.') }}</div>
              @endif
            </td>
            <td>
              @if ($p->transaksi && $p->transaksi->status_pembayaran === 'lunas')
                <span class="badge-active">Lunas</span>
              @elseif ($p->transaksi)
                <span class="role-badge">{{ ucfirst($p->transaksi->status_pembayaran) }}</span>
              @else
                <span class="emp-email">—</span>
              @endif
            </td>
            <td>
              @if ($p->status_pesanan === 'baru')
                <span class="badge-active">Baru</span>
              @elseif ($p->status_pesanan === 'diproses')
                <span class="role-badge">Diproses</span>
              @elseif ($p->status_pesanan === 'selesai')
                <span class="role-badge">✔ Selesai</span>
              @else
                <span class="badge-critical">Dibatalkan</span>
              @endif
            </td>
            <td>
              <div class="row-actions">
                @foreach ($transisi[$p->status_pesanan] as $tujuan)
                  @if ($tujuan !== 'dibatalkan')
                    <form method="POST" action="{{ route('admin.orders.status', $p) }}" style="display:inline;">@csrf
                      <input type="hidden" name="status_pesanan" value="{{ $tujuan }}">
                      <button class="btn-sm primary"><i data-lucide="arrow-right" size="13"></i> {{ ucfirst($tujuan) }}</button>
                    </form>
                  @endif
                @endforeach
                @if ($p->status_pesanan !== 'dibatalkan')
                  <form method="POST" action="{{ route('admin.orders.destroy', $p) }}" style="display:inline;" onsubmit="return confirm('Batalkan pesanan {{ $p->nomor_pesanan }}? Stok & poin dikembalikan.')">@csrf @method('DELETE')
                    <button class="btn-sm danger"><i data-lucide="ban" size="13"></i></button>
                  </form>
                @endif
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="7"><div class="empty-state">Tidak ada pesanan {{ $statusAktif ? "dengan status {$statusAktif}" : 'tercatat' }}.</div></td></tr>
        @endforelse
        </tbody>
      </table>
    </div>

    {{-- Mobile (<768px): setiap baris menjadi kartu vertikal agar tidak
         perlu scroll horizontal di layar 390px. --}}
    <div class="space-y-3 md:hidden">
      @forelse ($pesanan as $p)
        <article class="rounded-xl border border-[#3D322E] bg-[#271D19] p-4 text-xs">
          <header class="flex items-start justify-between gap-3 border-b border-[#3D322E] pb-2">
            <div class="min-w-0">
              <div class="font-mono font-bold text-[#E0A96D]">{{ $p->nomor_pesanan }}</div>
              <div class="mt-0.5 text-[11px] text-[#B8A8A1]">
                {{ $p->waktu_pesan->translatedFormat('d M Y H:i') }} • {{ ucfirst($p->tipe_pesanan) }}
              </div>
            </div>
            @if ($p->status_pesanan === 'baru')
              <span class="badge-active shrink-0">Baru</span>
            @elseif ($p->status_pesanan === 'diproses')
              <span class="role-badge shrink-0">Diproses</span>
            @elseif ($p->status_pesanan === 'selesai')
              <span class="role-badge shrink-0">✔ Selesai</span>
            @else
              <span class="badge-critical shrink-0">Dibatalkan</span>
            @endif
          </header>

          <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2">
            <div>
              <dt class="text-[10px] uppercase tracking-wider text-[#B8A8A1]">Meja</dt>
              <dd class="mt-0.5 font-semibold">{{ $p->meja?->nomor_meja ?? '—' }}</dd>
            </div>
            <div>
              <dt class="text-[10px] uppercase tracking-wider text-[#B8A8A1]">Pembayaran</dt>
              <dd class="mt-0.5">
                @if ($p->transaksi && $p->transaksi->status_pembayaran === 'lunas')
                  <span class="badge-active">Lunas</span>
                @elseif ($p->transaksi)
                  <span class="role-badge">{{ ucfirst($p->transaksi->status_pembayaran) }}</span>
                @else
                  <span class="text-[#B8A8A1]">—</span>
                @endif
              </dd>
            </div>
            <div class="col-span-2">
              <dt class="text-[10px] uppercase tracking-wider text-[#B8A8A1]">Item ({{ $p->detailPesanan->count() }})</dt>
              <dd class="mt-0.5 leading-relaxed text-[#B8A8A1]">
                {{ $p->detailPesanan->map(fn ($d) => $d->jumlah . '× ' . ($d->produk->nama_produk ?? '#'.$d->id_produk))->implode(', ') }}
              </dd>
            </div>
            <div class="col-span-2 flex items-baseline justify-between border-t border-[#3D322E] pt-2">
              <dt class="text-[10px] uppercase tracking-wider text-[#B8A8A1]">Total</dt>
              <dd class="font-mono text-sm font-bold">Rp {{ number_format($p->total_harga, 0, ',', '.') }}</dd>
            </div>
          </dl>

          <div class="mt-3 flex flex-wrap gap-2">
            @foreach ($transisi[$p->status_pesanan] as $tujuan)
              @if ($tujuan !== 'dibatalkan')
                <form method="POST" action="{{ route('admin.orders.status', $p) }}" class="flex-1">@csrf
                  <input type="hidden" name="status_pesanan" value="{{ $tujuan }}">
                  <button class="btn-sm primary w-full justify-center"><i data-lucide="arrow-right" size="13"></i> {{ ucfirst($tujuan) }}</button>
                </form>
              @endif
            @endforeach
            @if ($p->status_pesanan !== 'dibatalkan')
              <form method="POST" action="{{ route('admin.orders.destroy', $p) }}" class="flex-1" onsubmit="return confirm('Batalkan pesanan {{ $p->nomor_pesanan }}? Stok & poin dikembalikan.')">@csrf @method('DELETE')
                <button class="btn-sm danger w-full justify-center"><i data-lucide="ban" size="13"></i> Batalkan</button>
              </form>
            @endif
          </div>
        </article>
      @empty
        <div class="empty-state">Tidak ada pesanan {{ $statusAktif ? "dengan status {$statusAktif}" : 'tercatat' }}.</div>
      @endforelse
    </div>

    @if ($pesanan->hasPages())
      <div class="pagination-wrap">{{ $pesanan->links() }}</div>
    @endif

    <div class="staff-summary">
      <span>Pesanan adalah <strong>tabel transaksi</strong> — isinya dibuat oleh kasir/pelanggan, admin hanya menggerakkan status & pembatalan.</span>
    </div>
  </section>
@endsection
