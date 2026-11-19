/**
 * Tejas Coffee — smooth momentum scroll dengan Lenis.
 *
 * - Satu instance untuk semua halaman (admin maupun pelanggan). Lenis memakai
 *   scroll window, sehingga sidebar/topbar `position: sticky` tetap bekerja.
 * - Pengguna dengan preferensi "kurangi gerak" tidak diinisialisasi sama
 *   sekali: scroll tetap native dan CSS sudah mematikan smooth-nya.
 * - Elemen yang punya scroll sendiri (badan modal, daftar notifikasi) diberi
 *   atribut `data-lenis-prevent` di Blade agar gulirannya tidak diambil alih.
 * - Selama <dialog> terbuka, Lenis dihentikan supaya halaman di belakang tidak
 *   ikut bergulir; isi modal tetap bisa digulir berkat `data-lenis-prevent`.
 *   Status modal dipantau lewat atribut `open` (MutationObserver).
 * - Instance tersedia sebagai `window.lenis`; `window.tejasScrollTo(target)`
 *   untuk menggulir secara terprogram (offset topbar sudah diperhitungkan).
 */
import Lenis from 'lenis';

const OFFSET_ANCHOR = -84; // tinggi topbar lengket

function pantauDialog(lenis) {
    const sinkron = () => {
        if (document.querySelector('dialog[open]')) lenis.stop();
        else lenis.start();
    };

    // Mengamati atribut `open` (dipasang showModal/close) lebih andal daripada
    // event `toggle` yang baru dikirim asinkron, dan otomatis mencakup modal
    // yang disisipkan setelah halaman dimuat.
    new MutationObserver(sinkron).observe(document.body, {
        attributes: true,
        attributeFilter: ['open'],
        childList: true,
        subtree: true,
    });

    sinkron();
}

function jalankan() {
    // Hindari instance ganda saat HMR Vite memuat ulang modul.
    if (window.__tejasLenis) return;

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const lenis = new Lenis({
        autoRaf: true,
        duration: 1.05,
        smoothWheel: true,
        // Sentuhan memakai momentum bawaan perangkat, bukan disimulasikan.
        syncTouch: false,
        // Tautan anchor (#antrian dan sejenisnya) tetap mulus & tidak
        // tertutup topbar lengket.
        anchors: { offset: OFFSET_ANCHOR, duration: 0.9 },
        stopInertiaOnNavigate: true,
    });

    window.__tejasLenis = lenis;
    window.lenis = lenis;
    window.tejasScrollTo = (target, opsi = {}) =>
        lenis.scrollTo(target, { offset: OFFSET_ANCHOR, ...opsi });

    pantauDialog(lenis);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', jalankan);
} else {
    jalankan();
}
