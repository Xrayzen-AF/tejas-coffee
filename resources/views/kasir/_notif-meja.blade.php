{{--
    Notifikasi meja untuk layar kasir.
    Polling ringan tiap 5 detik ke /kode-meja/status, lalu:
    - bunyi + toast saat ada pelanggan menekan "Minta PIN" (menyebut nomor meja);
    - bunyi + toast saat meja dilepas pelanggan (menyebut nomor meja).
    Data status juga diteruskan lewat event `tejas:meja-status` agar halaman
    Kode Meja bisa memperbarui kartunya tanpa polling kedua.
--}}
<div class="tmn-stack" id="tmn-stack" aria-live="polite"></div>

<style>
  .tmn-stack { position: fixed; top: 76px; right: 16px; z-index: 90; display: flex; flex-direction: column; gap: 8px; width: 320px; max-width: calc(100vw - 24px); pointer-events: none; }
  .tmn-toast { pointer-events: auto; display: flex; align-items: flex-start; gap: 10px; padding: 12px 12px 12px 11px; border-radius: 10px; border: 1px solid #3D322E; background: #271D19; color: #F1DFD8; box-shadow: 0 18px 40px -18px rgba(0,0,0,.85); animation: tmn-in .2s ease-out; }
  .tmn-toast--req { border-color: #6B4A1F; background: #2E2218; }
  .tmn-toast--lepas { border-color: #33503C; background: #1F2A22; }
  .tmn-ikon { display: flex; align-items: center; justify-content: center; width: 30px; height: 30px; flex: 0 0 30px; border-radius: 8px; background: rgba(217,119,6,.2); color: #F6C89A; }
  .tmn-toast--lepas .tmn-ikon { background: rgba(134,224,164,.14); color: #86E0A4; }
  .tmn-isi { flex: 1; min-width: 0; }
  .tmn-judul { display: block; font-size: 13px; font-weight: 700; line-height: 1.35; }
  .tmn-pesan { display: block; margin-top: 2px; font-size: 11.5px; line-height: 1.5; color: #B8A8A1; }
  .tmn-tutup { border: 0; background: transparent; color: #9D8E87; cursor: pointer; padding: 0; line-height: 1; }
  .tmn-tutup:hover { color: #F1DFD8; }
  @keyframes tmn-in { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
  @media (max-width: 639.98px) { .tmn-stack { top: 68px; right: 12px; left: 12px; width: auto; } }
</style>

<script>
(function () {
    var stack = document.getElementById('tmn-stack');
    if (! stack) { return; }

    var statusUrl = @json(route('meja-kode.status'));
    var sudahReq = {};
    var sudahLepas = {};
    var siap = false;
    var sedangMuat = false;
    var audioCtx = null;

    function siapkanAudio() {
        if (! audioCtx) {
            var Ctx = window.AudioContext || window.webkitAudioContext;
            if (! Ctx) { return null; }
            audioCtx = new Ctx();
        }
        if (audioCtx.state === 'suspended') {
            audioCtx.resume().catch(function () {});
        }
        return audioCtx;
    }

    // Nada naik untuk permintaan PIN, nada turun untuk meja dilepas.
    function bunyikan(jenis) {
        window.__tejasMejaSuara = (window.__tejasMejaSuara || 0) + 1;

        var ctx = siapkanAudio();
        if (! ctx || ctx.state !== 'running') { return; }

        var mulai = ctx.currentTime;
        var nada = jenis === 'lepas'
            ? [[659.25, 0, 0.14], [440, 0.16, 0.26]]
            : [[880, 0, 0.14], [1174.66, 0.16, 0.26]];

        nada.forEach(function (n) {
            var osc = ctx.createOscillator();
            var gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.value = n[0];
            gain.gain.setValueAtTime(0.0001, mulai + n[1]);
            gain.gain.exponentialRampToValueAtTime(0.16, mulai + n[1] + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, mulai + n[1] + n[2]);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(mulai + n[1]);
            osc.stop(mulai + n[1] + n[2] + 0.02);
        });
    }

    // Browser baru mengizinkan audio setelah interaksi pertama pengguna.
    ['pointerdown', 'keydown'].forEach(function (peristiwa) {
        document.addEventListener(peristiwa, function () { siapkanAudio(); }, { once: true, capture: true });
    });

    window.tejasBunyiMeja = bunyikan;

    function tampilkan(jenis, judul, pesan) {
        var toast = document.createElement('div');
        toast.className = 'tmn-toast tmn-toast--' + jenis;

        var ikon = document.createElement('span');
        ikon.className = 'tmn-ikon';
        ikon.innerHTML = '<i data-lucide="' + (jenis === 'lepas' ? 'log-out' : 'key-round') + '" class="h-4 w-4"></i>';

        var isi = document.createElement('div');
        isi.className = 'tmn-isi';
        var judulEl = document.createElement('strong');
        judulEl.className = 'tmn-judul';
        judulEl.textContent = judul;
        var pesanEl = document.createElement('span');
        pesanEl.className = 'tmn-pesan';
        pesanEl.textContent = pesan;
        isi.appendChild(judulEl);
        isi.appendChild(pesanEl);

        var tutup = document.createElement('button');
        tutup.type = 'button';
        tutup.className = 'tmn-tutup';
        tutup.setAttribute('aria-label', 'Tutup notifikasi');
        tutup.innerHTML = '&times;';

        function hapus() {
            if (toast.parentNode) { toast.parentNode.removeChild(toast); }
        }
        tutup.addEventListener('click', hapus);

        toast.appendChild(ikon);
        toast.appendChild(isi);
        toast.appendChild(tutup);
        stack.appendChild(toast);

        while (stack.children.length > 4) {
            stack.removeChild(stack.firstElementChild);
        }

        if (window.lucide) { window.lucide.createIcons(); }

        setTimeout(hapus, jenis === 'lepas' ? 8000 : 12000);
    }

    function periksa(tables) {
        tables.forEach(function (t) {
            var id = String(t.id_meja);
            var nomor = t.nomor_meja || id;
            var idReq = t.permintaan_id || null;
            var idLepas = t.lepas_id || null;

            if (! siap) {
                if (idReq) { sudahReq[id] = idReq; }
                if (idLepas) { sudahLepas[id] = idLepas; }
                return;
            }

            // Permintaan PIN baru.
            if (idReq && sudahReq[id] !== idReq) {
                sudahReq[id] = idReq;

                // Abaikan kejadian lama (mis. tab sempat ditinggal) agar tidak
                // memunculkan bunyi untuk permintaan yang sudah basi.
                if (Number(t.permintaan_detik) <= 30) {
                    bunyikan('req');
                    tampilkan('req', 'Meja ' + nomor + ' meminta PIN', 'Pelanggan menunggu PIN dari kasir. Tekan Kirim PIN pada kartu meja ini.');
                }
            } else if (! idReq) {
                delete sudahReq[id];
            }

            // Meja baru saja dilepas pelanggan.
            if (idLepas && sudahLepas[id] !== idLepas) {
                sudahLepas[id] = idLepas;

                if (Number(t.lepas_detik) <= 30) {
                    bunyikan('lepas');
                    tampilkan('lepas', 'Meja ' + nomor + ' sudah dilepas', 'Pelanggan selesai memakai meja. Meja kembali kosong.');
                }
            }
        });
    }

    async function poll() {
        if (sedangMuat) { return; }

        sedangMuat = true;

        try {
            var res = await fetch(statusUrl, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });

            if (! res.ok) { return; }

            var data = await res.json();
            var tables = Array.isArray(data.tables) ? data.tables : [];

            periksa(tables);

            document.dispatchEvent(new CustomEvent('tejas:meja-status', { detail: data }));
        } catch (err) {
            // Diamkan: polling berikutnya mencoba lagi.
        } finally {
            sedangMuat = false;
            siap = true;
        }
    }

    // Halaman Kode Meja bisa meminta data terbaru setelah mendaftarkan listener.
    window.tejasMejaStatus = { refresh: poll };

    poll();
    setInterval(poll, 5000);
})();
</script>
