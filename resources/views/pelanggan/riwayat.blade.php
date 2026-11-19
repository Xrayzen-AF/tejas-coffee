@extends('layouts.app')

@section('title', 'Riwayat Pesanan')

@section('content')
<style>
  .rw { font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; background: #1E1511; color: #F1DFD8; }
  .rw .grotesk { font-family: 'Lora', ui-serif, Georgia, serif; }
  .rw-card { background: #271D19; border: 1px solid #3D322E; border-radius: var(--radius-surface); box-shadow: 0 1px 2px rgba(0,0,0,.45); }
  .rw-wrap { width: 100%; max-width: 640px; margin-inline: auto; }
  .rw-input { width: 100%; background: #221A15; border: 1px solid #3D322E; border-radius: var(--radius-control); padding: 8px 10px; font-size: 12px; color: #F1DFD8; outline: none; transition: .15s; }
  .rw-input:focus { background: #2A1F19; border-color: #D97706; box-shadow: 0 0 0 3px rgba(217,119,6,.18); }
  .rw-input::placeholder { color: #82746E; }
  .rw-label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #B8A8A1; margin-bottom: 5px; }
  @media (max-width: 639.98px) { .rw-input { font-size: 16px; } }
</style>

<div class="rw min-h-screen pb-32">
    <div class="rw-wrap px-4 pt-6">
        {{-- ═══ HEADER ═══ --}}
        <div class="flex items-center gap-3">
            <a href="{{ route('pelanggan.dashboard') }}" aria-label="Kembali ke beranda"
               class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-[#3D322E] bg-[#271D19] text-[#F1DFD8] transition hover:border-[#554336]">
                <i data-lucide="arrow-left" class="h-5 w-5"></i>
            </a>
            <div class="min-w-0">
                <p class="text-xs text-[#B8A8A1]">Velvet Club &bull; {{ $member?->nama_pelanggan ?? auth()->user()->nama_lengkap }}</p>
                <h1 class="grotesk text-xl sm:text-2xl font-bold text-[#F1DFD8]">Riwayat Pesanan</h1>
            </div>
        </div>

        {{-- ═══ FILTER ═══ --}}
        <form method="GET" action="{{ route('pelanggan.riwayat') }}" class="rw-card mt-4 p-3.5">
            <div class="flex items-center justify-between gap-2">
                <p class="text-[11px] font-bold uppercase tracking-wider text-[#B8A8A1]">Filter riwayat</p>
                @if ($adaFilter)
                    <a href="{{ route('pelanggan.riwayat') }}" class="text-[11px] font-semibold text-[#D97706] hover:underline">Reset</a>
                @endif
            </div>

            <div class="mt-2.5 grid grid-cols-2 gap-2">
                <div class="col-span-2">
                    <label class="rw-label" for="rw-cari">Cari nomor pesanan</label>
                    <input type="search" id="rw-cari" name="cari" value="{{ $filter['cari'] }}"
                           placeholder="Contoh: PSN-20261117" class="rw-input">
                </div>
                <div>
                    <label class="rw-label" for="rw-status">Status</label>
                    <select id="rw-status" name="status" class="rw-input">
                        <option value="">Semua status</option>
                        @foreach ($statuses as $s)
                            <option value="{{ $s }}" @selected($filter['status'] === $s)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="rw-label" for="rw-tipe">Tipe</label>
                    <select id="rw-tipe" name="tipe" class="rw-input">
                        <option value="">Semua tipe</option>
                        @foreach ($tipes as $t)
                            <option value="{{ $t }}" @selected($filter['tipe'] === $t)>{{ ucfirst($t) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="rw-label" for="rw-dari">Dari tanggal</label>
                    <input type="date" id="rw-dari" name="dari" value="{{ $filter['dari'] }}" class="rw-input">
                </div>
                <div>
                    <label class="rw-label" for="rw-sampai">Sampai tanggal</label>
                    <input type="date" id="rw-sampai" name="sampai" value="{{ $filter['sampai'] }}" class="rw-input">
                </div>
            </div>

            <button type="submit" class="mt-2.5 w-full rounded-md bg-[#D97706] py-2 text-xs font-bold text-[#241708] transition hover:bg-[#B56100]">
                Terapkan filter
            </button>
        </form>

        {{-- ═══ RINGKASAN HASIL FILTER ═══ --}}
        <div class="rw-card mt-3 flex items-center justify-between gap-3 px-3.5 py-3">
            <div class="min-w-0">
                <p class="text-[10px] uppercase tracking-wider text-[#B8A8A1]">{{ $adaFilter ? 'Hasil filter' : 'Semua pesanan' }}</p>
                <p class="mt-0.5 text-[13px] font-semibold text-[#F1DFD8]">{{ $jumlahTerfilter }} pesanan</p>
            </div>
            <div class="shrink-0 text-right">
                <p class="text-[10px] uppercase tracking-wider text-[#B8A8A1]">Total belanja</p>
                <p class="grotesk mt-0.5 text-[15px] font-bold text-[#D97706]">Rp {{ number_format($totalTerfilter, 0, ',', '.') }}</p>
            </div>
        </div>
        <p class="mt-1.5 text-[10px] text-[#9D8E87]">Total belanja tidak menghitung pesanan yang dibatalkan.</p>

        {{-- ═══ DAFTAR PESANAN ═══ --}}
        <div class="mt-3 space-y-2.5">
            @forelse ($riwayatPesanan as $p)
                <div class="rw-card p-3.5 transition hover:shadow-md">
                    <a href="{{ route('pelanggan.status', $p->nomor_pesanan) }}"
                       class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="grotesk truncate text-[13px] font-bold text-[#F1DFD8]">#{{ $p->nomor_pesanan }}</p>
                            <p class="mt-0.5 text-[11px] text-[#B8A8A1]">
                                {{ $p->waktu_pesan?->format('d M Y H:i') }} • {{ $p->detailPesanan->sum('jumlah') }} item
                                @if ($p->meja)
                                    • Meja {{ $p->meja->nomor_meja }}
                                @endif
                            </p>
                            <p class="mt-0.5 text-[10.5px] text-[#9D8E87]">
                                {{ ucfirst($p->tipe_pesanan) }}
                                @if ($p->transaksi)
                                    • {{ $p->transaksi->status_pembayaran === 'lunas' ? 'Lunas' : ucfirst($p->transaksi->status_pembayaran) }}
                                @endif
                            </p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="grotesk text-[13px] font-bold text-[#F1DFD8]">Rp {{ number_format($p->total_harga, 0, ',', '.') }}</p>
                            <span class="mt-1 inline-block rounded px-1.5 py-0.5 text-[10px] font-bold uppercase {{ $p->status_pesanan === 'selesai' ? 'bg-[#352A22] text-[#F1DFD8]' : ($p->status_pesanan === 'dibatalkan' ? 'bg-[#BA1A1A]/10 text-[#F87171]' : 'bg-[#D97706] text-[#241708]') }}">
                                {{ $p->labelStatus() }}
                            </span>
                        </div>
                    </a>

                    <div class="mt-2.5 flex items-center justify-end gap-2 border-t border-dashed border-[#3D322E] pt-2.5">
                        @if ($p->status_pesanan !== 'dibatalkan')
                            <form method="POST" action="{{ route('pelanggan.riwayat.pesan-lagi', $p->nomor_pesanan) }}">
                                @csrf
                                <button type="submit"
                                        class="inline-flex items-center gap-1.5 rounded-md border border-[#D97706]/60 bg-[#D97706]/12 px-2.5 py-1.5 text-[11px] font-semibold text-[#F1DFD8] transition hover:bg-[#D97706]/25">
                                    <i data-lucide="rotate-ccw" class="h-3.5 w-3.5"></i>
                                    Pesan lagi
                                </button>
                            </form>
                        @endif
                        <button type="button" data-struk-url="{{ route('struk.show', $p) }}"
                           class="inline-flex items-center gap-1.5 rounded-md border border-[#3D322E] bg-[#2F231D] px-2.5 py-1.5 text-[11px] font-semibold text-[#F1DFD8] transition hover:border-[#554336] hover:bg-[#352A22]">
                            <i data-lucide="receipt-text" class="h-3.5 w-3.5"></i>
                            Lihat struk
                        </button>
                    </div>
                </div>
            @empty
                <div class="rw-card p-8 text-center">
                    @if ($adaFilter)
                        <p class="text-sm text-[#B8A8A1]">Tidak ada pesanan yang cocok dengan filter.</p>
                        <a href="{{ route('pelanggan.riwayat') }}"
                           class="mt-3 inline-flex items-center gap-1.5 rounded-md border border-[#3D322E] bg-[#2F231D] px-4 py-2 text-xs font-bold text-[#F1DFD8] transition hover:border-[#554336]">
                            <i data-lucide="x" class="h-4 w-4"></i> Hapus filter
                        </a>
                    @else
                        <p class="text-sm text-[#B8A8A1]">Belum ada pesanan. Yuk pesan menu pertama!</p>
                        <a href="{{ route('pelanggan.menu') }}"
                           class="mt-3 inline-flex items-center gap-1.5 rounded-md bg-[#D97706] px-4 py-2 text-xs font-bold text-[#241708] transition hover:bg-[#B56100]">
                            <i data-lucide="coffee" class="h-4 w-4"></i> Lihat Menu
                        </a>
                    @endif
                </div>
            @endforelse
        </div>

        {{-- ═══ PAGINATION ═══ --}}
        @if ($riwayatPesanan->hasPages())
            <div class="rw-card mt-4 flex items-center justify-between gap-3 px-4 py-3 text-xs text-[#B8A8A1]">
                @if ($riwayatPesanan->previousPageUrl())
                    <a href="{{ $riwayatPesanan->previousPageUrl() }}" class="font-semibold text-[#F1DFD8] hover:underline">&larr; Sebelumnya</a>
                @else
                    <span class="opacity-40">&larr; Sebelumnya</span>
                @endif
                <span>Halaman {{ $riwayatPesanan->currentPage() }} / {{ $riwayatPesanan->lastPage() }}</span>
                @if ($riwayatPesanan->nextPageUrl())
                    <a href="{{ $riwayatPesanan->nextPageUrl() }}" class="font-semibold text-[#F1DFD8] hover:underline">Berikutnya &rarr;</a>
                @else
                    <span class="opacity-40">Berikutnya &rarr;</span>
                @endif
            </div>
        @endif
    </div>

    {{-- ═══ BOTTOM NAV ═══ --}}
    @include('pelanggan._nav', ['aktif' => 'riwayat'])
</div>
@endsection
