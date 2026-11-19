/**
 * Tejas Coffee — animasi UI ringan untuk semua halaman.
 *
 * - Menandai kartu (.tejas-card) secara otomatis dari nama class yang sudah ada
 *   sehingga tidak perlu mengubah markup Blade satu per satu.
 * - Reveal bertahap saat kartu masuk viewport (IntersectionObserver),
 *   pengganti AOS tanpa dependensi tambahan.
 * - Konten yang sudah di layar langsung ditampilkan; ada jaring pengaman
 *   waktu agar tidak ada kartu yang tertinggal transparan bila observer
 *   tertunda (tab background, perangkat lambat, render diblokir).
 * - Menghormati prefers-reduced-motion dan halaman cetak.
 */

const AKAR_KARTU = ['card', 'panel', 'tile'];
const ABAIKAN = /(card|panel|tile)-(head|header|title|body|footer|grid|list|row|inner|content|wrap|actions)$/;
const INDUK_DIABAIKAN = 'nav, aside, table, .tejas-modal, .admin-notif-panel, [data-tejas-no-motion]';
const JARING_PENGAMAN_MS = 1200;

function tokenKartu(el) {
    if (!el.classList) return false;

    return Array.from(el.classList).some((kelas) => {
        if (ABAIKAN.test(kelas)) return false;

        return AKAR_KARTU.some((akar) => kelas === akar || kelas.endsWith(akar));
    });
}

function tampak(el) {
    const kotak = el.getBoundingClientRect();

    return kotak.width > 0 && kotak.height > 0;
}

function tandaiKartu() {
    const kartu = [];

    document.querySelectorAll('[class]').forEach((el) => {
        if (!tokenKartu(el) || el.closest(INDUK_DIABAIKAN) || el.closest('.tejas-card')) return;
        if (!tampak(el)) return;

        el.classList.add('tejas-card');
        kartu.push(el);
    });

    return kartu;
}

function reveal(kartu) {
    // Beri jeda bertahap per kelompok (induk yang sama) agar terasa mengalir.
    const hitungan = new Map();

    kartu.forEach((el) => {
        const induk = el.parentElement;
        const urutan = hitungan.get(induk) || 0;
        hitungan.set(induk, urutan + 1);

        el.style.setProperty('--tejas-delay', `${Math.min(urutan, 7) * 45}ms`);
        el.classList.add('tejas-reveal');
    });

    const tampilkan = (el) => el.classList.add('is-visible');
    const dalamLayar = (el) => {
        const kotak = el.getBoundingClientRect();

        return kotak.top < window.innerHeight && kotak.bottom > 0;
    };

    // Konten yang sudah terlihat TIDAK menunggu observer: opacity tidak boleh
    // tertinggal 0 bila callback observer tertunda.
    requestAnimationFrame(() => {
        kartu.filter(dalamLayar).forEach(tampilkan);
    });

    if ('IntersectionObserver' in window) {
        const pengamat = new IntersectionObserver((entri) => {
            entri.forEach((e) => {
                if (!e.isIntersecting) return;

                tampilkan(e.target);
                pengamat.unobserve(e.target);
            });
        }, { threshold: 0.08, rootMargin: '0px 0px -36px 0px' });

        kartu.forEach((el) => pengamat.observe(el));
    }

    // Jaring pengaman terakhir: tidak ada kartu yang boleh tetap transparan.
    window.setTimeout(() => kartu.forEach(tampilkan), JARING_PENGAMAN_MS);

    // Kembali ke tab: langsung tampilkan yang ada di layar.
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) kartu.filter(dalamLayar).forEach(tampilkan);
    });
}

function jalankan() {
    // Hindari pemasangan ganda saat HMR Vite memuat ulang modul.
    if (window.__tejasPolishReady) return;
    window.__tejasPolishReady = true;

    if (document.body.classList.contains('admin-body') && !document.querySelector('.admin-content')) {
        return;
    }

    const kurangiGerak = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (kurangiGerak) return;

    reveal(tandaiKartu());
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', jalankan);
} else {
    jalankan();
}
