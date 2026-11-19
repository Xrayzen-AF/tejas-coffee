{{-- Kontrol modal bersama: buka lewat data-modal-open="id", tutup lewat
     data-tutup-modal, klik backdrop, atau Esc (perilaku bawaan <dialog>).
     Disertakan sekali di tiap layout, bukan di setiap view. --}}
<script>
    (function () {
        document.addEventListener('click', function (e) {
            var pemicu = e.target.closest('[data-modal-open]');
            if (pemicu) {
                var dialog = document.getElementById(pemicu.getAttribute('data-modal-open'));
                if (dialog && !dialog.open) {
                    if (typeof dialog.showModal === 'function') dialog.showModal();
                    else dialog.setAttribute('open', '');
                }
                return;
            }

            var tutup = e.target.closest('[data-tutup-modal]');
            if (tutup) {
                var d = tutup.closest('dialog');
                if (d) d.close();
            }
        });

        // Klik area gelap (backdrop) menutup modal.
        document.querySelectorAll('dialog.app-modal').forEach(function (dialog) {
            dialog.addEventListener('click', function (e) {
                if (e.target === dialog) dialog.close();
            });
        });
    })();
</script>
