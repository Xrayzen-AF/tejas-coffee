{{-- Modal struk bersama: menampilkan struk tanpa membuka tab baru.
     Isi struk dimuat di iframe agar CSS kertas 80 mm tetap terisolasi dari tema
     halaman, dan tombol cetak tetap mencetak dokumen struk saja (bukan modalnya).
     Dipakai oleh tombol/tautan dengan atribut data-struk-url. --}}
<div id="tejas-struk" class="tejas-modal-backdrop" aria-hidden="true">
    <div class="tejas-modal tejas-struk-modal" role="dialog" aria-modal="true" aria-labelledby="tejas-struk-title">
        <div class="tejas-modal-header tejas-struk-head">
            <h3 id="tejas-struk-title" class="tejas-modal-title">Struk Pesanan</h3>
            <button type="button" id="tejas-struk-x" class="tejas-btn tejas-btn-ghost tejas-struk-x" aria-label="Tutup struk">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>

        <div class="tejas-modal-body tejas-struk-body">
            <div class="tejas-struk-viewport">
                <iframe id="tejas-struk-frame" title="Isi struk pesanan" src="about:blank"></iframe>
                <div id="tejas-struk-loading" class="tejas-struk-loading">Memuat struk…</div>
            </div>
        </div>

        <div class="tejas-modal-footer">
            <button type="button" id="tejas-struk-print" class="tejas-btn tejas-btn-primary">
                <i data-lucide="printer" class="h-4 w-4"></i> Cetak Struk
            </button>
            <button type="button" id="tejas-struk-close" class="tejas-btn tejas-btn-ghost">Tutup</button>
        </div>
    </div>
</div>

<style>
    .tejas-struk-modal { max-width: 420px; }
    .tejas-struk-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
    .tejas-struk-x { padding: 6px 10px; }
    .tejas-struk-body { padding: 12px 22px 14px 22px; }
    .tejas-struk-viewport {
        position: relative;
        height: min(62dvh, 520px);
        background: #17110E;
        border: 1px solid #3D322E;
        border-radius: var(--radius-surface, 8px);
        overflow: hidden;
    }
    .tejas-struk-viewport iframe {
        display: block;
        width: 100%;
        height: 100%;
        border: 0;
        background: #17110E;
    }
    .tejas-struk-loading {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12.5px;
        color: #9D8E87;
        background: #17110E;
    }
    .tejas-struk-loading[hidden] { display: none; }
</style>

<script>
    (function () {
        var backdrop = document.getElementById('tejas-struk');
        if (!backdrop) return;

        var frame = document.getElementById('tejas-struk-frame');
        var loading = document.getElementById('tejas-struk-loading');
        var btnPrint = document.getElementById('tejas-struk-print');
        var btnClose = document.getElementById('tejas-struk-close');
        var btnX = document.getElementById('tejas-struk-x');
        var lastTrigger = null;

        function tutup() {
            backdrop.classList.remove('is-open');
            backdrop.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            // Kosongkan iframe agar struk dengan print=1 tidak tercetak ulang saat ditutup.
            frame.setAttribute('src', 'about:blank');
            if (lastTrigger && typeof lastTrigger.focus === 'function') lastTrigger.focus();
            lastTrigger = null;
        }

        function buka(url, trigger) {
            lastTrigger = trigger || null;
            loading.hidden = false;
            frame.setAttribute('src', url);
            backdrop.classList.add('is-open');
            backdrop.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            try { btnPrint.focus(); } catch (e) {}
        }

        frame.addEventListener('load', function () {
            if (frame.getAttribute('src') === 'about:blank') return;
            loading.hidden = true;
        });

        document.addEventListener('click', function (e) {
            var trigger = e.target.closest('[data-struk-url]');
            if (!trigger) return;
            e.preventDefault();
            buka(trigger.getAttribute('data-struk-url'), trigger);
        });

        btnPrint.addEventListener('click', function () {
            if (frame.getAttribute('src') === 'about:blank') return;
            try {
                frame.contentWindow.focus();
                frame.contentWindow.print();
            } catch (e) {}
        });

        btnClose.addEventListener('click', tutup);
        btnX.addEventListener('click', tutup);
        backdrop.addEventListener('click', function (e) { if (e.target === backdrop) tutup(); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && backdrop.classList.contains('is-open')) tutup();
        });
    })();
</script>
