@extends('layouts.admin', ['activeMenu' => 'kustomisasi'])

@section('title', 'Kustomisasi Menu — Tejas Coffee')
@section('header-title', 'Kustomisasi Menu')
@section('header-subtitle', 'Atur pilihan ukuran, es, topping, dan opsi lain — pasang per menu')

@section('content')
  @if (session('error'))
    <div class="alert-error">{{ session('error') }}</div>
  @endif

  @if ($errors->any())
    <div class="alert-error" style="margin-bottom:16px;">
      <ul style="margin:0;padding-left:18px;">
        @foreach ($errors->all() as $err)
          <li>{{ $err }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <section class="metrics-grid" style="grid-template-columns:repeat(3, minmax(0, 1fr));">
    <div class="metric-card">
      <div class="metric-header"><span>Grup Opsi</span><div class="metric-icon"><i data-lucide="layers" size="16"></i></div></div>
      <div class="metric-value">{{ $grup->count() }}</div>
      <div class="metric-sub">Kelompok pilihan kustomisasi</div>
    </div>
    <div class="metric-card">
      <div class="metric-header"><span>Total Pilihan</span><div class="metric-icon"><i data-lucide="list-checks" size="16"></i></div></div>
      <div class="metric-value">{{ $grup->sum(fn ($g) => $g->items->count()) }}</div>
      <div class="metric-sub">Item di semua grup</div>
    </div>
    <div class="metric-card">
      <div class="metric-header"><span>Pemasangan</span><div class="metric-icon"><i data-lucide="coffee" size="16"></i></div></div>
      <div class="metric-value">{{ $grup->sum('produk_count') }}</div>
      <div class="metric-sub">Relasi grup → produk</div>
    </div>
  </section>

  <section class="card" style="margin-top:20px;">
    <div class="card-title-row">
      <div>
        <h3>Tambah Grup Kustomisasi</h3>
        <p>Contoh: Pilihan Ukuran, Level Es, Pilihan Susu, Topping. Setelah dibuat, centang grup ini pada produk di halaman Menu.</p>
      </div>
    </div>
    <form method="POST" action="{{ route('admin.master.kustomisasi.store') }}">
      @csrf
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Nama grup</label>
          <input name="nama" class="form-input" placeholder="Pilihan Suhu" required value="{{ old('nama') }}">
        </div>
        <div class="form-group">
          <label class="form-label">Kode (unik, tanpa spasi)</label>
          <input name="kode" class="form-input" placeholder="suhu" required value="{{ old('kode') }}">
        </div>
        <div class="form-group">
          <label class="form-label">Tipe pilihan</label>
          <select name="tipe" class="form-select">
            @foreach ($daftarTipe as $nilai => $label)
              <option value="{{ $nilai }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Urutan tampil</label>
          <input name="urutan" type="number" min="0" class="form-input" value="{{ old('urutan', 0) }}">
        </div>
      </div>
      <label class="form-hint" style="display:flex;gap:8px;align-items:center;">
        <input type="checkbox" name="wajib" value="1" checked> Wajib dipilih pelanggan
      </label>
      <div class="action-btn-row">
        <button class="btn-primary" type="submit">Tambah Grup</button>
      </div>
    </form>
  </section>

  @forelse ($grup as $g)
    <section class="card" style="margin-top:20px;">
      <div class="card-title-row">
        <div>
          <h3>{{ $g->nama }} <span class="role-badge" style="margin-left:6px;">{{ $g->kode }}</span></h3>
          <p>
            {{ $daftarTipe[$g->tipe] ?? $g->tipe }} •
            {{ $g->items->count() }} pilihan •
            dipakai {{ $g->produk_count }} produk
            @unless ($g->aktif) • <span class="badge-critical">Nonaktif</span> @endunless
          </p>
        </div>
        <div class="row-actions">
          <form method="POST" action="{{ route('admin.master.kustomisasi.destroy', $g) }}"
                onsubmit="return confirm('Hapus grup {{ $g->nama }} beserta semua pilihannya?')">
            @csrf @method('DELETE')
            <button class="btn-sm danger" type="submit"><i data-lucide="trash-2" size="13"></i> Hapus Grup</button>
          </form>
        </div>
      </div>

      <button type="button" class="btn-sm" data-modal-open="edit-grup-{{ $g->id_opsi_grup }}" style="margin-bottom:12px;">
        <i data-lucide="sliders-horizontal" size="13"></i> Ubah pengaturan grup
      </button>

      <x-app-modal id="edit-grup-{{ $g->id_opsi_grup }}" title="Ubah Pengaturan Grup" :subtitle="$g->nama" width="720px">
        <form method="POST" action="{{ route('admin.master.kustomisasi.update', $g) }}" class="app-modal-form">
          @csrf @method('PUT')
          <div>
            <label class="form-label">Nama grup</label>
            <input name="nama" class="form-input" value="{{ $g->nama }}" required>
          </div>
          <div>
            <label class="form-label">Kode</label>
            <input name="kode" class="form-input" value="{{ $g->kode }}" required>
          </div>
          <div>
            <label class="form-label">Tipe</label>
            <select name="tipe" class="form-select">
              @foreach ($daftarTipe as $nilai => $label)
                <option value="{{ $nilai }}" @selected($g->tipe === $nilai)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="form-label">Urutan</label>
            <input name="urutan" type="number" min="0" class="form-input" value="{{ $g->urutan }}">
          </div>
          <div style="grid-column:1/-1;display:flex;gap:16px;flex-wrap:wrap;align-items:center;">
            <input type="hidden" name="wajib" value="0">
            <input type="hidden" name="aktif" value="0">
            <label class="form-hint" style="display:flex;gap:6px;align-items:center;">
              <input type="checkbox" name="wajib" value="1" @checked($g->wajib)> Wajib dipilih
            </label>
            <label class="form-hint" style="display:flex;gap:6px;align-items:center;">
              <input type="checkbox" name="aktif" value="1" @checked($g->aktif)> Aktif (tampil di pelanggan)
            </label>
            <button class="btn-primary" type="submit" style="margin-left:auto;padding:9px 16px;">Simpan Grup</button>
          </div>
        </form>
      </x-app-modal>

      <div class="table-wrap">
        <table class="ops-table">
          <thead>
            <tr>
              <th style="min-width:180px;">Pilihan</th>
              <th>Kode</th>
              <th>Tambahan Harga</th>
              <th>Default</th>
              <th>Status</th>
              <th style="text-align:right;">Aksi</th>
            </tr>
          </thead>
          <tbody>
          @forelse ($g->items as $item)
            <tr>
              <td>
                <div class="emp-name">{{ $item->label }}</div>
                <div class="emp-email">{{ $item->deskripsi ?: '—' }}</div>
              </td>
              <td><span class="role-badge">{{ $item->kode }}</span></td>
              <td style="white-space:nowrap;">Rp {{ number_format((float) $item->delta_harga, 0, ',', '.') }}</td>
              <td>{{ $item->is_default ? 'Ya' : '—' }}</td>
              <td>
                @if ($item->aktif)
                  <span class="badge-active">Aktif</span>
                @else
                  <span class="badge-critical">Nonaktif</span>
                @endif
              </td>
              <td>
                <div class="row-actions">
                  <form method="POST" action="{{ route('admin.master.kustomisasi.item.destroy', $item) }}"
                        onsubmit="return confirm('Hapus pilihan {{ $item->label }}?')">
                    @csrf @method('DELETE')
                    <button class="btn-sm danger" type="submit"><i data-lucide="trash-2" size="13"></i></button>
                  </form>
                </div>
                <button type="button" class="btn-sm" data-modal-open="edit-item-{{ $item->id_opsi_item }}" style="margin-top:8px;width:100%;">Ubah</button>

                <x-app-modal id="edit-item-{{ $item->id_opsi_item }}" title="Ubah Pilihan" :subtitle="$item->label" width="680px">
                  <form method="POST" action="{{ route('admin.master.kustomisasi.item.update', $item) }}" class="app-modal-form">
                    @csrf @method('PUT')
                    <div>
                      <label class="form-label">Label</label>
                      <input name="label" class="form-input" value="{{ $item->label }}" required>
                    </div>
                    <div>
                      <label class="form-label">Kode</label>
                      <input name="kode" class="form-input" value="{{ $item->kode }}" required>
                    </div>
                    <div>
                      <label class="form-label">Tambahan harga (Rp)</label>
                      <input name="delta_harga" type="number" min="0" step="0.01" class="form-input" value="{{ (float) $item->delta_harga }}">
                    </div>
                    <div>
                      <label class="form-label">Urutan</label>
                      <input name="urutan" type="number" min="0" class="form-input" value="{{ $item->urutan }}">
                    </div>
                    <div style="grid-column:1/-1;">
                      <label class="form-label">Deskripsi (opsional)</label>
                      <input name="deskripsi" class="form-input" value="{{ $item->deskripsi }}">
                    </div>
                    <div style="grid-column:1/-1;display:flex;gap:16px;flex-wrap:wrap;align-items:center;">
                      <input type="hidden" name="is_default" value="0">
                      <input type="hidden" name="aktif" value="0">
                      <label class="form-hint" style="display:flex;gap:6px;align-items:center;">
                        <input type="checkbox" name="is_default" value="1" @checked($item->is_default)> Pilihan default
                      </label>
                      <label class="form-hint" style="display:flex;gap:6px;align-items:center;">
                        <input type="checkbox" name="aktif" value="1" @checked($item->aktif)> Aktif
                      </label>
                      <button class="btn-primary" type="submit" style="margin-left:auto;padding:9px 16px;">Simpan Pilihan</button>
                    </div>
                  </form>
                </x-app-modal>
              </td>
            </tr>
          @empty
            <tr><td colspan="6"><div class="empty-state">Belum ada pilihan di grup ini.</div></td></tr>
          @endforelse
          </tbody>
        </table>
      </div>

      <form method="POST" action="{{ route('admin.master.kustomisasi.item.store', $g) }}"
            style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-top:14px;background:#1E1511;padding:12px;border-radius:10px;border:1px dashed #554336;">
        @csrf
        <div>
          <label class="form-label">Label pilihan baru</label>
          <input name="label" class="form-input" placeholder="Boba" required>
        </div>
        <div>
          <label class="form-label">Kode</label>
          <input name="kode" class="form-input" placeholder="boba" required>
        </div>
        <div>
          <label class="form-label">Tambahan harga (Rp)</label>
          <input name="delta_harga" type="number" min="0" step="0.01" class="form-input" value="0">
        </div>
        <div>
          <label class="form-label">Urutan</label>
          <input name="urutan" type="number" min="0" class="form-input" value="0">
        </div>
        <div style="grid-column:1/-1;">
          <label class="form-label">Deskripsi (opsional)</label>
          <input name="deskripsi" class="form-input" placeholder="Topping boba kenyal">
        </div>
        <div style="grid-column:1/-1;display:flex;gap:16px;flex-wrap:wrap;align-items:center;">
          <label class="form-hint" style="display:flex;gap:6px;align-items:center;">
            <input type="checkbox" name="is_default" value="1"> Jadikan default
          </label>
          <button class="btn-sm primary" type="submit" style="margin-left:auto;">Tambah Pilihan</button>
        </div>
      </form>
    </section>
  @empty
    <section class="card" style="margin-top:20px;">
      <div class="empty-state">Belum ada grup kustomisasi. Tambahkan grup pertama di atas.</div>
    </section>
  @endforelse
@endsection
