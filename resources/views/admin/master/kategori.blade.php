@extends('layouts.admin', ['activeMenu' => 'kategori'])

@section('title', 'Master Kategori — Tejas Coffee')
@section('header-title', 'Master Kategori')
@section('header-subtitle', 'Data dasar jenis menu — induk dari tabel produk')

@section('header-action')
  <button type="button" class="btn-new-ticket" data-modal-open="tambah-kategori">
    <i data-lucide="plus" size="14"></i> Tambah Kategori
  </button>
@endsection

@section('content')
  @if (session('error'))
    <div class="alert-error">{{ session('error') }}</div>
  @endif

  <section class="metrics-grid" style="grid-template-columns: repeat(3, 1fr);">
    <div class="metric-card">
      <div class="metric-header"><span>Total Kategori</span><div class="metric-icon"><i data-lucide="folder" size="16"></i></div></div>
      <div class="metric-value">{{ $kategori->count() }}</div>
      <div class="metric-sub">Tanpa pagination</div>
    </div>
    <div class="metric-card">
      <div class="metric-header"><span>Aktif</span><div class="metric-icon"><i data-lucide="check-circle-2" size="16"></i></div></div>
      <div class="metric-value">{{ $kategori->where('status_aktif', true)->count() }}</div>
      <div class="metric-sub">Tampak di menu pelanggan</div>
    </div>
    <div class="metric-card">
      <div class="metric-header"><span>Total Produk</span><div class="metric-icon"><i data-lucide="package" size="16"></i></div></div>
      <div class="metric-value">{{ $kategori->sum('produk_count') }}</div>
      <div class="metric-sub">Seluruh produk terkategorikan</div>
    </div>
  </section>

  <section>
    <div class="card">
      <div class="card-title-row"><div><h3>Daftar Kategori</h3><p>Klik Update untuk ubah data</p></div></div>
      <div class="table-wrap">
        <table class="ops-table">
          <thead><tr><th>Kategori</th><th>Produk</th><th>Status</th><th style="text-align:right;">Aksi</th></tr></thead>
          <tbody>
          @forelse ($kategori as $k)
            <tr>
              <td><div class="emp-name">{{ $k->nama_kategori }}</div><div class="emp-email">{{ $k->deskripsi ?? '—' }}</div></td>
              <td>{{ $k->produk_count }} produk</td>
              <td>@if($k->status_aktif)<span class="badge-active">Aktif</span>@else<span class="badge-critical">Nonaktif</span>@endif</td>
              <td>
                <div class="row-actions">
                  <form method="POST" action="{{ route('admin.master.kategori.destroy', $k) }}" style="display:inline;" onsubmit="return confirm('Hapus kategori {{ $k->nama_kategori }}?')">@csrf @method('DELETE')
                    <button class="btn-sm danger"><i data-lucide="trash-2" size="13"></i></button>
                  </form>
                </div>
                <button type="button" class="btn-sm" data-modal-open="edit-kategori-{{ $k->id_kategori }}" style="margin-top:8px;width:100%;">Update</button>

                <x-app-modal id="edit-kategori-{{ $k->id_kategori }}" title="Update Kategori" :subtitle="$k->nama_kategori" width="460px">
                  <form method="POST" action="{{ route('admin.master.kategori.update', $k) }}" class="app-modal-form">
                    @csrf @method('PUT')
                    <input name="nama_kategori" value="{{ old('nama_kategori', $k->nama_kategori) }}" class="form-input" required>
                    <input name="deskripsi" value="{{ old('deskripsi', $k->deskripsi) }}" class="form-input" placeholder="Deskripsi">
                    <label class="form-hint" style="display:flex;gap:8px;align-items:center;"><input type="checkbox" name="status_aktif" value="1" @checked($k->status_aktif)> Status aktif</label>
                    <button type="submit" class="btn-primary full" style="padding:10px;">Simpan</button>
                  </form>
                </x-app-modal>
              </td>
            </tr>
          @empty
            <tr><td colspan="4"><div class="empty-state">Belum ada kategori.</div></td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
      @if ($kategori->hasPages())
        <div class="pagination-wrap">{{ $kategori->links() }}</div>
      @endif
    </div>

    <x-app-modal id="tambah-kategori" title="Tambah Kategori" subtitle="Data master — dibaca produk & menu" width="460px">
      <form method="POST" action="{{ route('admin.master.kategori.store') }}" class="app-modal-form">
        @csrf
        <div class="form-group"><label class="form-label">Nama kategori</label><input name="nama_kategori" class="form-input" placeholder="Kopi Non-Espresso" required></div>
        <div class="form-group"><label class="form-label">Deskripsi</label><input name="deskripsi" class="form-input" placeholder="Bukan espresso"></div>
        <div class="form-group">
          <label class="form-hint" style="display:flex;gap:8px;align-items:center;">
            <input type="checkbox" name="status_aktif" value="1" checked> Status aktif (tampak di menu pelanggan)
          </label>
        </div>
        <button type="submit" class="btn-primary full" style="padding:10px;">Simpan Kategori</button>
      </form>
    </x-app-modal>
  </section>
@endsection
