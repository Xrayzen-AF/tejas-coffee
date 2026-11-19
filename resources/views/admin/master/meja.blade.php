@extends('layouts.admin', ['activeMenu' => 'meja'])

@section('title', 'Master Meja — Tejas Coffee')
@section('header-title', 'Master Meja')
@section('header-subtitle', 'Data dasar meja dine-in — dipakai pesanan & sinkron status')

@section('header-action')
  <button type="button" class="btn-new-ticket" data-modal-open="tambah-meja">
    <i data-lucide="plus" size="14"></i> Tambah Meja
  </button>
@endsection

@section('content')
  @if (session('error'))
    <div class="alert-error">{{ session('error') }}</div>
  @endif

  <section class="metrics-grid" style="grid-template-columns: repeat(4, 1fr);">
    <div class="metric-card">
      <div class="metric-header"><span>Total Meja</span><div class="metric-icon"><i data-lucide="grid" size="16"></i></div></div>
      <div class="metric-value">{{ $meja->count() }}</div>
      <div class="metric-sub">Tanpa pagination</div>
    </div>
    @foreach (['kosong', 'dipesan', 'terisi'] as $s)
      <div class="metric-card">
        <div class="metric-header">
          <span>Meja {{ ucfirst($s) }}</span>
          <div class="metric-icon"><i data-lucide="{{ $s === 'terisi' ? 'user' : ($s === 'dipesan' ? 'clock' : 'check') }}" size="16"></i></div>
        </div>
        <div class="metric-value">{{ $meja->where('status', $s)->count() }}</div>
        <div class="metric-sub">Status terkini</div>
      </div>
    @endforeach
  </section>

  <section style="margin-top:20px;">
    <div class="card">
      <div class="card-title-row"><div><h3>Daftar Meja</h3><p>Klik Update untuk ubah nomor/kapasitas</p></div>
        <a href="{{ route('admin.master.meja.qr') }}" class="btn-sm primary"><i data-lucide="qr-code" size="13"></i> Cetak QR</a>
      </div>
      <div class="table-wrap">
        <table class="ops-table">
          <thead><tr><th>Meja</th><th>Kapasitas</th><th>Status</th><th>Pesanan aktif</th><th style="text-align:right;">Aksi</th></tr></thead>
          <tbody>
          @forelse ($meja as $m)
            <tr>
              <td><div class="emp-name">{{ $m->nomor_meja }}</div><div class="emp-email">ID #{{ $m->id_meja }}</div></td>
              <td>{{ $m->kapasitas }} orang</td>
              <td>@if($m->status === 'kosong')<span class="badge-active">Kosong</span>@elseif($m->status === 'dipesan')<span class="role-badge">Dipesan</span>@else<span class="badge-critical">Terisi</span>@endif</td>
              <td>{{ $m->pesanan_count }} aktif</td>
              <td>
                <div class="row-actions">
                  <form method="POST" action="{{ route('admin.master.meja.destroy', $m) }}" style="display:inline;" onsubmit="return confirm('Hapus meja {{ $m->nomor_meja }}?')">@csrf @method('DELETE')
                    <button class="btn-sm danger"><i data-lucide="trash-2" size="13"></i></button>
                  </form>
                </div>
                <button type="button" class="btn-sm" data-modal-open="edit-meja-{{ $m->id_meja }}" style="margin-top:8px;width:100%;">Update</button>

                <x-app-modal id="edit-meja-{{ $m->id_meja }}" title="Update Meja" :subtitle="$m->nomor_meja" width="460px">
                  <form method="POST" action="{{ route('admin.master.meja.update', $m) }}" class="app-modal-form">
                    @csrf @method('PUT')
                    <input name="nomor_meja" value="{{ old('nomor_meja', $m->nomor_meja) }}" class="form-input" required>
                    <input name="kapasitas" type="number" min="1" value="{{ old('kapasitas', $m->kapasitas) }}" class="form-input" required>
                    <button type="submit" class="btn-primary full" style="padding:10px;">Simpan</button>
                  </form>
                </x-app-modal>
              </td>
            </tr>
          @empty
            <tr><td colspan="5"><div class="empty-state">Belum ada meja. Tambah via form di kanan.</div></td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <x-app-modal id="tambah-meja" title="Tambah Meja" subtitle="Data master — bakal otomatis kosong" width="460px">
      <form method="POST" action="{{ route('admin.master.meja.store') }}" class="app-modal-form">
        @csrf
        <div class="form-group"><label class="form-label">Nomor / nama meja</label><input name="nomor_meja" class="form-input" placeholder="M-12" required></div>
        <div class="form-group"><label class="form-label">Kapasitas orang</label><input name="kapasitas" type="number" min="1" class="form-input" value="4" required></div>
        <button type="submit" class="btn-primary full" style="padding:10px;">Simpan Meja</button>
      </form>
    </x-app-modal>

    <div class="card">
      <div class="staff-summary">
        <span>Status meja dikelola otomatis dari pesanan (kosong ↔ terisi) — admin mengatur identitasnya saja.</span>
      </div>
    </div>
  </section>
@endsection
