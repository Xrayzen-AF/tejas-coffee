@extends('layouts.admin', ['activeMenu' => 'settings'])

@section('title', 'Store Settings — Tejas Coffee')
@section('header-title', 'Store Settings')
@section('header-subtitle', 'Profil gerai, default operasional & info sistem')

@section('content')
  <section class="middle-grid">
    <div class="card">
      <div class="card-title-row"><div><h3>Profil Toko</h3><p>Tampil di struk, header POS & Ops Console</p></div><i data-lucide="store" size="18" style="color:var(--accent-caramel);"></i></div>
      <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf @method('PUT')
        {{-- kirim ulang default operasional agar validasi tetap lolos --}}
        @foreach (['threshold_default_kg','safety_cap_default_kg','poin_per_10k','biaya_layanan'] as $f)
          <input type="hidden" name="{{ $f }}" value="{{ $settings[$f] }}">
        @endforeach
        <div class="form-grid">
          <div class="form-group"><label class="form-label">Nama toko</label><input name="store_name" value="{{ old('store_name', $settings['store_name']) }}" class="form-input" required></div>
          <div class="form-group"><label class="form-label">Cabang</label><input name="branch" value="{{ old('branch', $settings['branch']) }}" class="form-input" required></div>
        </div>
        <div class="form-group"><label class="form-label">Alamat</label><input name="address" value="{{ old('address', $settings['address']) }}" class="form-input" required></div>
        <div class="form-grid">
          <div class="form-group"><label class="form-label">Telepon</label><input name="phone" value="{{ old('phone', $settings['phone']) }}" class="form-input" required></div>
          <div class="form-group"><label class="form-label">Destinasi roastery default</label><input name="roastery_destination" value="{{ old('roastery_destination', $settings['roastery_destination']) }}" class="form-input" required></div>
          <div class="form-group"><label class="form-label">Jam buka</label><input name="open_hour" type="time" value="{{ old('open_hour', $settings['open_hour']) }}" class="form-input" required></div>
          <div class="form-group"><label class="form-label">Jam tutup</label><input name="close_hour" type="time" value="{{ old('close_hour', $settings['close_hour']) }}" class="form-input" required></div>
        </div>
        <button class="btn-primary" style="width:100%;padding:10px;">Simpan Profil</button>
      </form>
    </div>

    <div class="card">
      <div class="card-title-row"><div><h3>Default Operasional</h3><p> dipakai saat tambah biji & hitung loyalitas</p></div><i data-lucide="sliders-horizontal" size="18" style="color:var(--accent-caramel);"></i></div>
      <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf @method('PUT')
        {{-- kirim ulang profil agar validasi profil tetap lolos --}}
        @foreach (['store_name','branch','address','phone','open_hour','close_hour','roastery_destination'] as $f)
          <input type="hidden" name="{{ $f }}" value="{{ $settings[$f] }}">
        @endforeach
        <div class="form-grid">
          <div class="form-group"><label class="form-label">Threshold biji default (kg)</label><input name="threshold_default_kg" type="number" step="0.1" value="{{ old('threshold_default_kg', $settings['threshold_default_kg']) }}" class="form-input" required><span class="form-hint">Alert kritis jika sisa di bawah ini</span></div>
          <div class="form-group"><label class="form-label">Safety cap default (kg)</label><input name="safety_cap_default_kg" type="number" step="0.1" value="{{ old('safety_cap_default_kg', $settings['safety_cap_default_kg']) }}" class="form-input" required><span class="form-hint">Target qty reorder otomatis</span></div>
        </div>
        <div class="form-grid">
          <div class="form-group"><label class="form-label">Poin per Rp 10.000</label><input name="poin_per_10k" type="number" min="0" value="{{ old('poin_per_10k', $settings['poin_per_10k']) }}" class="form-input" required></div>
          <div class="form-group"><label class="form-label">Biaya layanan per pesanan (Rp)</label><input name="biaya_layanan" type="number" min="0" step="100" value="{{ old('biaya_layanan', $settings['biaya_layanan']) }}" class="form-input" required><span class="form-hint">Ditambahkan ke total & tampil di struk</span></div>
        </div>
        <button class="btn-primary" style="width:100%;padding:10px;">Simpan Default</button>
      </form>
      <div class="staff-summary"><span>Jam operasional: <strong>{{ $settings['open_hour'] }} – {{ $settings['close_hour'] }}</strong></span></div>
    </div>
  </section>

  <section class="bottom-grid">
    <div class="card">
      <div class="card-title-row"><div><h3>Metode Pembayaran</h3><p> dipakai kasir saat tandai lunas</p></div></div>
      <div class="top-selling-list" style="margin-bottom:12px;">
        @forelse ($metode as $m)
          <div class="item-row"><div class="item-details"><h4>{{ $m->nama_metode }}</h4><p>{{ $m->kode_metode }} • {{ $m->ada_kembalian ? 'ada kembalian' : 'tanpa kembalian' }}</p></div></div>
        @empty
          <div class="empty-state">Belum ada metode.</div>
        @endforelse
      </div>
      <form method="POST" action="{{ route('admin.settings.metode') }}" style="display:flex;gap:8px;">
        @csrf
        <input name="kode_metode" class="form-input" placeholder="kode: qris" required style="max-width:140px;">
        <input name="nama_metode" class="form-input" placeholder="Nama: QRIS" required>
        <button class="btn-sm primary">Tambah</button>
      </form>
    </div>

    <div class="card">
      <div class="card-title-row"><div><h3>Info Sistem</h3><p>Environment & data master</p></div><i data-lucide="info" size="18" style="color:var(--accent-caramel);"></i></div>
      <div class="top-selling-list">
        @foreach ($info as $k => $v)
          <div class="item-row"><div class="item-details"><h4>{{ $k }}</h4></div><div class="item-stats"><div class="cups" style="font-weight:500;font-size:12px;">{{ $v }}</div></div></div>
        @endforeach
      </div>
      <div class="staff-summary"><span>Meja terdaftar: <strong>{{ $meja->count() }}</strong> ditampilkan (maks 20)</span></div>
    </div>
  </section>

  <section class="bottom-grid">
    <div class="card">
      <div class="card-title-row">
        <div><h3>Midtrans Payment Gateway</h3><p>QRIS dinamis &amp; virtual account untuk pembayaran kasir</p></div>
        <span class="{{ $midtransInfo['configured'] ? 'badge-active' : 'badge-critical' }}">{{ $midtransInfo['configured'] ? 'Terhubung' : 'Belum diatur' }}</span>
      </div>

      @if ($errors->any())
        <div class="alert-error"><strong>Periksa input:</strong><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
      @endif

      <form method="POST" action="{{ route('admin.settings.midtrans') }}">
        @csrf @method('PUT')
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Server Key</label>
            <input name="server_key" type="password" class="form-input" autocomplete="new-password"
                   placeholder="{{ $midtransInfo['server_masked'] ?? 'SB-Mid-server-...' }}">
            <span class="form-hint">Kosongkan jika tidak ingin mengubah key tersimpan.</span>
          </div>
          <div class="form-group">
            <label class="form-label">Client Key</label>
            <input name="client_key" type="password" class="form-input" autocomplete="new-password"
                   placeholder="{{ $midtransInfo['client_masked'] ?? 'SB-Mid-client-...' }}">
            <span class="form-hint">Diperlukan untuk transaksi Snap; QRIS/VA memakai Server Key.</span>
          </div>
        </div>
        <label class="form-hint" style="display:flex;align-items:center;gap:8px;margin:0 0 14px;cursor:pointer;">
          <input type="checkbox" name="is_production" value="1" @checked($midtrans?->is_production)>
          Mode production (nonaktifkan untuk sandbox)
        </label>
        <button class="btn-primary" style="width:100%;padding:10px;">Simpan Kredensial</button>
      </form>

      <div class="staff-summary"><span>Mode: <strong>{{ $midtransInfo['mode'] }}</strong> • Server Key: <strong>{{ $midtransInfo['server_masked'] ?? 'belum diisi' }}</strong></span></div>
    </div>

    <div class="card">
      <div class="card-title-row"><div><h3>Webhook &amp; Aktivasi</h3><p>Supaya status lunas masuk otomatis</p></div><i data-lucide="webhook" size="18" style="color:var(--accent-caramel);"></i></div>
      <p class="form-hint" style="margin-top:0;">Pasang URL berikut di dashboard Midtrans → Settings → Payment Notification URL:</p>
      <div class="password-box" style="margin-bottom:12px;word-break:break-all;"><code style="font-size:11.5px;color:var(--text-main);">{{ $midtransInfo['webhook_url'] }}</code></div>
      <div class="top-selling-list">
        <div class="item-row"><div class="item-details"><h4>QRIS</h4><p>Scan QR dinamis yang dibuat per tagihan</p></div><span class="badge-active">Aktif</span></div>
        <div class="item-row"><div class="item-details"><h4>Virtual Account</h4><p>BCA, BNI, BRI, Mandiri, Permata</p></div><span class="badge-active">Aktif</span></div>
      </div>
      <p class="form-hint">Pastikan channel QRIS &amp; VA sudah diaktifkan di akun Midtrans. Di localhost, kasir tetap bisa mengecek status lewat polling tanpa webhook.</p>
    </div>
  </section>

  @php
    $geoAktif = filter_var($settings['geofence_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $geoLat = $settings['cafe_latitude'] ?? null;
    $geoLng = $settings['cafe_longitude'] ?? null;
  @endphp
  <section class="middle-grid" style="grid-template-columns:1fr;">
    <div class="card">
      <div class="card-title-row">
        <div><h3>Geofence Kedatangan</h3><p>Pelanggan wajib mengaktifkan GPS di dalam radius cafe sebelum bisa memesan</p></div>
        <i data-lucide="map-pin" size="18" style="color:var(--accent-caramel);"></i>
      </div>

      <p class="form-hint" style="margin-top:0;">
        Titik cafe dipakai sebagai pusat radius. Jarak dihitung di server (haversine), jadi
        pesanan yang dibuat dari luar area tetap ditolak walau GPS dipalsukan lewat browser.
      </p>

      <form method="POST" action="{{ route('admin.settings.geofence') }}">
        @csrf @method('PUT')

        <label class="form-hint" style="display:flex;align-items:center;gap:8px;margin:0 0 14px;cursor:pointer;">
          <input type="checkbox" name="geofence_enabled" value="1" @checked(old('geofence_enabled', $geoAktif))>
          Aktifkan geofence (pelanggan harus berada di dalam radius)
        </label>

        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Latitude titik cafe</label>
            <input name="cafe_latitude" id="geoLatInput" type="text" inputmode="decimal"
                   value="{{ old('cafe_latitude', $geoLat) }}" class="form-input" placeholder="-6.2000000">
          </div>
          <div class="form-group">
            <label class="form-label">Longitude titik cafe</label>
            <input name="cafe_longitude" id="geoLngInput" type="text" inputmode="decimal"
                   value="{{ old('cafe_longitude', $geoLng) }}" class="form-input" placeholder="106.8166667">
          </div>
          <div class="form-group">
            <label class="form-label">Radius izin (meter)</label>
            <input name="radius_meter" id="geoRadiusInput" type="number" min="10" max="10000"
                   value="{{ old('radius_meter', $settings['radius_meter'] ?? 150) }}" class="form-input" required>
            <span class="form-hint">Contoh 150 m untuk area cafe + parkir.</span>
          </div>
          <div class="form-group">
            <label class="form-label">Akurasi GPS maksimal (meter)</label>
            <input name="max_accuracy_meter" type="number" min="10" max="1000"
                   value="{{ old('max_accuracy_meter', $settings['max_accuracy_meter'] ?? null) }}"
                   class="form-input" placeholder="otomatis">
            <span class="form-hint">
              Kosongkan untuk otomatis (35% radius, dibatasi 25–60 m → saat ini
              ±{{ (int) round(max(25, min(60, (float) ($settings['radius_meter'] ?? 150) * 0.35))) }} m).
              Naikkan bila perangkat hanya mendapat lokasi berbasis WiFi (akurasi 50–200 m).
            </span>
          </div>
        </div>

        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin:4px 0 10px;">
          <button type="button" id="geoPakaiLokasi" class="btn-secondary" style="padding:9px 14px;">Gunakan lokasi saya sekarang</button>
          <button type="button" id="geoHapusTitik" class="btn-secondary" style="padding:9px 14px;">Hapus titik</button>
          <span class="form-hint" id="geoStatusAdmin" style="margin:0;">Belum ada titik tersimpan.</span>
        </div>

        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <div style="border:1px solid var(--border-line,#3D322E);border-radius:12px;overflow:hidden;margin-bottom:6px;background:#221A15;position:relative;">
          <div id="geoMap" style="height:290px;width:100%;z-index:0;"></div>
          <div id="geoMapFallback" class="form-hint" style="display:none;padding:14px;margin:0;">
            Peta tidak bisa dimuat (butuh koneksi internet). Kamu tetap bisa mengisi latitude/longitude manual di atas.
          </div>
        </div>
        <p class="form-hint" style="margin:0 0 14px;">
          Klik peta untuk menaruh titik cafe. Lingkaran oranye adalah area radius yang diizinkan.
        </p>

        <button class="btn-primary" style="width:100%;padding:10px;">Simpan Geofence</button>
      </form>

      <div class="staff-summary">
        <span>Status:
          @if ($geoAktif && filled($geoLat) && filled($geoLng))
            <strong>Aktif</strong> — radius {{ (int) ($settings['radius_meter'] ?? 150) }} m
          @elseif ($geoAktif)
            <strong>Menunggu titik cafe</strong> — isi latitude/longitude lalu simpan
          @else
            <strong>Nonaktif</strong> — pelanggan bisa membuka aplikasi dari mana saja
          @endif
        </span>
      </div>
    </div>
  </section>

  <script>
  (function () {
      var latEl = document.getElementById('geoLatInput');
      var lngEl = document.getElementById('geoLngInput');
      var radiusEl = document.getElementById('geoRadiusInput');
      var mapEl = document.getElementById('geoMap');
      var fallback = document.getElementById('geoMapFallback');
      var status = document.getElementById('geoStatusAdmin');
      var tombolGps = document.getElementById('geoPakaiLokasi');
      var tombolHapus = document.getElementById('geoHapusTitik');

      var map = null;
      var marker = null;
      var circle = null;

      function angka(v) {
          var n = parseFloat(v);
          return isFinite(n) ? n : null;
      }

      function radius() {
          return Math.max(10, parseInt(radiusEl.value, 10) || 150);
      }

      function tulisInput(posisi) {
          latEl.value = posisi.lat.toFixed(7);
          lngEl.value = posisi.lng.toFixed(7);
      }

      function perbaruiStatus(posisi) {
          status.textContent = 'Titik: ' + posisi.lat.toFixed(6) + ', ' + posisi.lng.toFixed(6) +
                               ' • radius ' + radius() + ' m';
      }

      function perbaruiLingkaran() {
          if (!marker || !circle) return;
          var posisi = marker.getLatLng();
          circle.setLatLng(posisi);
          circle.setRadius(radius());
          perbaruiStatus(posisi);
      }

      function pasangTitik(lat, lng, zoom) {
          if (!map) return;
          var posisi = L.latLng(lat, lng);

          if (!marker) {
              marker = L.circleMarker(posisi, {
                  radius: 8, color: '#1E1511', weight: 2,
                  fillColor: '#D97706', fillOpacity: 1
              }).addTo(map);
              circle = L.circle(posisi, {
                  radius: radius(), color: '#D97706', weight: 1.5,
                  fillColor: '#D97706', fillOpacity: 0.15
              }).addTo(map);
          } else {
              marker.setLatLng(posisi);
              circle.setLatLng(posisi);
              circle.setRadius(radius());
          }

          tulisInput(posisi);
          perbaruiStatus(posisi);

          if (zoom) {
              map.setView(posisi, zoom);
          }
      }

      function hapusTitik() {
          if (map && marker) { map.removeLayer(marker); marker = null; }
          if (map && circle) { map.removeLayer(circle); circle = null; }
          latEl.value = '';
          lngEl.value = '';
          status.textContent = 'Belum ada titik dipilih. Klik peta untuk menentukan titik cafe.';
      }

      function initPeta() {
          if (typeof L === 'undefined') {
              mapEl.style.display = 'none';
              fallback.style.display = 'block';
              status.textContent = 'Peta tidak tersedia. Isi latitude/longitude manual.';
              return;
          }

          map = L.map(mapEl, { scrollWheelZoom: true }).setView([-2.5, 118], 5);

          L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
              maxZoom: 19,
              attribution: '&copy; OpenStreetMap'
          }).addTo(map);

          map.on('click', function (e) {
              pasangTitik(e.latlng.lat, e.latlng.lng, 17);
          });

          var lat = angka(latEl.value);
          var lng = angka(lngEl.value);

          if (lat !== null && lng !== null && Math.abs(lat) <= 90 && Math.abs(lng) <= 180) {
              pasangTitik(lat, lng, 17);
          } else {
              status.textContent = 'Klik peta untuk memilih titik cafe.';
          }

          setTimeout(function () { map.invalidateSize(); }, 250);
      }

      [latEl, lngEl].forEach(function (el) {
          el.addEventListener('input', function () {
              var lat = angka(latEl.value);
              var lng = angka(lngEl.value);
              if (lat === null || lng === null || Math.abs(lat) > 90 || Math.abs(lng) > 180) return;
              pasangTitik(lat, lng);
              map.setView(L.latLng(lat, lng));
          });
      });

      radiusEl.addEventListener('input', perbaruiLingkaran);

      tombolGps.addEventListener('click', function () {
          if (!('geolocation' in navigator)) {
              status.textContent = 'Browser ini tidak mendukung GPS.';
              return;
          }
          status.textContent = 'Membaca lokasi perangkat…';
          navigator.geolocation.getCurrentPosition(function (pos) {
              pasangTitik(pos.coords.latitude, pos.coords.longitude, 17);
              status.textContent = 'Lokasi terbaca (akurasi ±' + Math.round(pos.coords.accuracy) + ' m). Cek peta lalu simpan.';
          }, function (err) {
              status.textContent = err.code === 1
                  ? 'Izin lokasi ditolak browser. Klik peta untuk memilih titik manual.'
                  : 'Gagal membaca GPS. Klik peta untuk memilih titik manual.';
          }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
      });

      tombolHapus.addEventListener('click', hapusTitik);

      initPeta();
  })();
  </script>
@endsection
