{{-- Komponen modal bersama. Pakai:
     <x-app-modal id="edit-x-1" title="Update X" :subtitle="$x->nama"> ...isi... </x-app-modal>
     Pemicu: <button type="button" data-modal-open="edit-x-1">Update</button>
     Tombol tutup di dalam badan modal: data-tutup-modal. --}}
@props([
    'id',
    'title' => null,
    'subtitle' => null,
    'width' => null,
])

<dialog id="{{ $id }}" class="app-modal" @if ($width) style="--app-modal-width: {{ $width }}" @endif>
    <div class="app-modal-head">
        <div>
            @if ($title)
                <h3>{{ $title }}</h3>
            @endif
            @if ($subtitle)
                <p class="app-modal-sub">{{ $subtitle }}</p>
            @endif
        </div>
        <button type="button" class="app-modal-x" data-tutup-modal aria-label="Tutup">
            <i data-lucide="x" size="14"></i>
        </button>
    </div>
    <div class="app-modal-body" data-lenis-prevent>
        {{ $slot }}
    </div>
</dialog>
