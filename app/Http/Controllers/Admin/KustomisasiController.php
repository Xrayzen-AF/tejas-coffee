<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OpsiGrup;
use App\Models\OpsiItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Kustomisasi Menu — kelola grup opsi (mis. Pilihan Ukuran, Level Es,
 * Topping) beserta pilihannya. Grup dipasang per produk di halaman Menu
 * agar tiap menu hanya menampilkan opsi yang relevan (bukan semuanya kopi).
 */
class KustomisasiController extends Controller
{
    public const TIPE = [
        'pilih_satu' => 'Pilih satu (radio)',
        'pilih_banyak' => 'Bisa lebih dari satu (checkbox)',
    ];

    public function index(): View
    {
        return view('admin.master.kustomisasi', [
            'grup' => OpsiGrup::query()
                ->with('items')
                ->withCount('produk')
                ->orderBy('urutan')
                ->orderBy('id_opsi_grup')
                ->get(),
            'daftarTipe' => self::TIPE,
            'activeMenu' => 'kustomisasi',
        ]);
    }

    public function storeGrup(Request $request): RedirectResponse
    {
        $v = $this->validasiGrup($request);

        OpsiGrup::create($v);

        return back()->with('success', "Grup kustomisasi {$v['nama']} ditambahkan. Pilih produk yang memakainya di halaman Menu.");
    }

    public function updateGrup(Request $request, OpsiGrup $grup): RedirectResponse
    {
        $v = $this->validasiGrup($request, $grup);

        $grup->update($v);

        return back()->with('success', "Grup {$grup->nama} diperbarui.");
    }

    public function destroyGrup(OpsiGrup $grup): RedirectResponse
    {
        $nama = $grup->nama;
        $grup->delete();

        return back()->with('success', "Grup {$nama} dihapus beserta pilihannya.");
    }

    public function storeItem(Request $request, OpsiGrup $grup): RedirectResponse
    {
        $v = $this->validasiItem($request, $grup);

        $grup->items()->create($v);

        return back()->with('success', "Pilihan {$v['label']} ditambahkan ke {$grup->nama}.");
    }

    public function updateItem(Request $request, OpsiItem $item): RedirectResponse
    {
        $v = $this->validasiItem($request, $item->grup, $item);

        $item->update($v);

        return back()->with('success', "Pilihan {$item->label} diperbarui.");
    }

    public function destroyItem(OpsiItem $item): RedirectResponse
    {
        $label = $item->label;
        $item->delete();

        return back()->with('success', "Pilihan {$label} dihapus.");
    }

    private function validasiGrup(Request $request, ?OpsiGrup $grup = null): array
    {
        return $request->validate([
            'kode' => [
                'required', 'string', 'max:40', 'alpha_dash',
                Rule::unique('opsi_grup', 'kode')->ignore($grup?->id_opsi_grup, 'id_opsi_grup'),
            ],
            'nama' => ['required', 'string', 'max:255'],
            'tipe' => ['required', Rule::in(array_keys(self::TIPE))],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:999'],
            'wajib' => ['nullable', 'boolean'],
        ]) + [
            'wajib' => $request->boolean('wajib'),
            'urutan' => (int) $request->input('urutan', 0),
            // Form mengirim hidden 0; tanpa field sama sekali = grup baru (aktif).
            'aktif' => $request->has('aktif') ? $request->boolean('aktif') : true,
        ];
    }

    private function validasiItem(Request $request, OpsiGrup $grup, ?OpsiItem $item = null): array
    {
        return $request->validate([
            'kode' => [
                'required', 'string', 'max:40', 'alpha_dash',
                Rule::unique('opsi_item', 'kode')
                    ->where(fn ($q) => $q->where('id_opsi_grup', $grup->id_opsi_grup))
                    ->ignore($item?->id_opsi_item, 'id_opsi_item'),
            ],
            'label' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:255'],
            'delta_harga' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_default' => ['nullable', 'boolean'],
            'aktif' => ['nullable', 'boolean'],
        ]) + [
            'delta_harga' => (float) $request->input('delta_harga', 0),
            'urutan' => (int) $request->input('urutan', 0),
            'is_default' => $request->boolean('is_default'),
            'aktif' => $item ? $request->boolean('aktif') : true,
        ];
    }
}
