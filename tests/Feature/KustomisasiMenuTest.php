<?php

namespace Tests\Feature;

use App\Models\Kategori;
use App\Models\OpsiGrup;
use App\Models\OpsiItem;
use App\Models\Pengguna;
use App\Models\Produk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kustomisasi menu kini data-driven: admin membuat grup & pilihan, lalu
 * memasangnya per produk agar tidak semua menu memakai opsi kopi.
 */
class KustomisasiMenuTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Pengguna
    {
        return Pengguna::factory()->create(['peran' => 'admin']);
    }

    private function produk(int $stok = 20): Produk
    {
        $kategori = Kategori::firstOrCreate(['nama_kategori' => 'Teh']);

        return Produk::create([
            'id_kategori' => $kategori->id_kategori,
            'kode_produk' => 'TEH-01',
            'nama_produk' => 'Teh Melati',
            'harga' => 18000,
            'stok' => $stok,
            'status_tersedia' => true,
        ]);
    }

    private function grupSuhu(): OpsiGrup
    {
        $this->actingAs($this->admin())
            ->post(route('admin.master.kustomisasi.store'), [
                'nama' => 'Pilihan Suhu',
                'kode' => 'suhu',
                'tipe' => 'pilih_satu',
                'wajib' => 1,
                'urutan' => 1,
            ])
            ->assertRedirect();

        $grup = OpsiGrup::where('kode', 'suhu')->firstOrFail();

        $this->post(route('admin.master.kustomisasi.item.store', $grup), [
            'label' => 'Panas', 'kode' => 'panas', 'delta_harga' => 0, 'is_default' => 1,
        ])->assertRedirect();
        $this->post(route('admin.master.kustomisasi.item.store', $grup), [
            'label' => 'Dingin', 'kode' => 'dingin', 'delta_harga' => 2000,
        ])->assertRedirect();

        return $grup->fresh('items');
    }

    public function test_admin_membuat_grup_dan_pilihan_kustomisasi(): void
    {
        $grup = $this->grupSuhu();

        $this->assertCount(2, $grup->items);
        $this->assertDatabaseHas('opsi_item', ['kode' => 'dingin', 'delta_harga' => 2000]);
    }

    public function test_menu_hanya_menampilkan_grup_yang_dipasang_admin(): void
    {
        $grup = $this->grupSuhu();
        $produk = $this->produk();
        $produk->opsiGrup()->sync([$grup->id_opsi_grup]);

        $pelanggan = Pengguna::factory()->create(['peran' => 'pelanggan']);

        $this->actingAs($pelanggan)
            ->get(route('pelanggan.menu.kustom', $produk->id_produk))
            ->assertOk()
            ->assertSee('Pilihan Suhu', false)
            ->assertSee('Dingin', false)
            // Grup bawaan kopi tidak boleh muncul karena tidak dipasang.
            ->assertDontSee('Pilihan Susu', false);
    }

    public function test_pilihan_tambahan_harga_dihitung_di_server(): void
    {
        $grup = $this->grupSuhu();
        $produk = $this->produk();
        $produk->opsiGrup()->sync([$grup->id_opsi_grup]);

        $pelanggan = Pengguna::factory()->create(['peran' => 'pelanggan']);

        $this->actingAs($pelanggan)
            ->post(route('pelanggan.keranjang.tambah', $produk->id_produk), [
                'jumlah' => 1,
                'opsi' => ['suhu' => 'dingin'],
            ])
            ->assertRedirect(route('pelanggan.keranjang.index'));

        $baris = array_values(session('keranjang'))[0];

        $this->assertSame(20000.0, (float) $baris['harga']);
        $this->assertStringContainsString('Dingin', $baris['opsi_text']);
    }

    public function test_admin_memasang_grup_dari_form_produk(): void
    {
        $grup = $this->grupSuhu();
        $produk = $this->produk();

        $this->actingAs($this->admin())
            ->put(route('admin.master.produk.update', $produk), [
                'id_kategori' => $produk->id_kategori,
                'kode_produk' => $produk->kode_produk,
                'nama_produk' => $produk->nama_produk,
                'harga' => 18000,
                'stok' => 20,
                'status_tersedia' => 1,
                'grup_ids' => [$grup->id_opsi_grup],
            ])
            ->assertRedirect();

        $this->assertSame(
            [$grup->id_opsi_grup],
            $produk->fresh()->opsiGrup->pluck('id_opsi_grup')->all()
        );
    }

    public function test_admin_melepas_semua_centang_kustomisasi_tersimpan(): void
    {
        $grup = $this->grupSuhu();
        $produk = $this->produk();
        $produk->opsiGrup()->sync([$grup->id_opsi_grup]);

        $this->actingAs($this->admin())
            ->put(route('admin.master.produk.update', $produk), [
                'id_kategori' => $produk->id_kategori,
                'kode_produk' => $produk->kode_produk,
                'nama_produk' => $produk->nama_produk,
                'harga' => 18000,
                'stok' => 20,
                'status_tersedia' => 1,
                // grup_ids sengaja tidak dikirim: semua centang dilepas.
            ])
            ->assertRedirect();

        $this->assertSame([], $produk->fresh()->opsiGrup->pluck('id_opsi_grup')->all());
    }

    public function test_produk_tanpa_grup_tidak_menampilkan_kustomisasi(): void
    {
        // Grup aktif tetap ada di database, tetapi tidak dipasang ke produk ini.
        $this->grupSuhu();
        $produk = $this->produk();
        $pelanggan = Pengguna::factory()->create(['peran' => 'pelanggan']);

        $this->actingAs($pelanggan)
            ->get(route('pelanggan.menu.kustom', $produk->id_produk))
            ->assertOk()
            ->assertSee('tidak memakai pilihan kustomisasi', false)
            // Tidak ada input opsi milik grup tersebut di form.
            ->assertDontSee('name="opsi[suhu]"', false);
    }

    public function test_hapus_grup_menghapus_pilihannya(): void
    {
        $grup = $this->grupSuhu();

        $this->actingAs($this->admin())
            ->delete(route('admin.master.kustomisasi.destroy', $grup))
            ->assertRedirect();

        $this->assertDatabaseMissing('opsi_grup', ['id_opsi_grup' => $grup->id_opsi_grup]);
        $this->assertSame(0, OpsiItem::where('id_opsi_grup', $grup->id_opsi_grup)->count());
    }
}
