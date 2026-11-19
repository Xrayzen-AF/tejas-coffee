<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Grup kustomisasi menu (mis. Pilihan Ukuran, Level Es, Topping).
        Schema::create('opsi_grup', function (Blueprint $table) {
            $table->id('id_opsi_grup');
            $table->string('kode', 40)->unique();
            $table->string('nama');
            $table->enum('tipe', ['pilih_satu', 'pilih_banyak'])->default('pilih_satu');
            $table->boolean('wajib')->default(true);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        // Pilihan di dalam satu grup (mis. Regular 350 ml, Oat Milk, Boba).
        Schema::create('opsi_item', function (Blueprint $table) {
            $table->id('id_opsi_item');
            $table->foreignId('id_opsi_grup')
                ->constrained('opsi_grup', 'id_opsi_grup')
                ->cascadeOnDelete();
            $table->string('kode', 40);
            $table->string('label');
            $table->string('deskripsi')->nullable();
            $table->decimal('delta_harga', 10, 2)->default(0);
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->unique(['id_opsi_grup', 'kode']);
        });

        // Grup mana saja yang dipakai oleh sebuah produk.
        Schema::create('produk_opsi_grup', function (Blueprint $table) {
            $table->foreignId('id_produk')
                ->constrained('produk', 'id_produk')
                ->cascadeOnDelete();
            $table->foreignId('id_opsi_grup')
                ->constrained('opsi_grup', 'id_opsi_grup')
                ->cascadeOnDelete();

            $table->primary(['id_produk', 'id_opsi_grup']);
        });

        $this->seedBawaan();
    }

    /**
     * Isi awal: salin opsi yang sebelumnya ditulis mati di CartController
     * agar perilaku menu yang sudah ada tidak berubah. Admin bebas mengubah.
     */
    private function seedBawaan(): void
    {
        $grup = [
            [
                'kode' => 'ukuran',
                'nama' => 'Pilihan Ukuran',
                'tipe' => 'pilih_satu',
                'wajib' => true,
                'urutan' => 1,
                'items' => [
                    ['kode' => 'regular', 'label' => '350 ml Regular', 'deskripsi' => 'Default', 'delta_harga' => 0, 'is_default' => true, 'urutan' => 1],
                    ['kode' => 'large', 'label' => '500 ml Large', 'deskripsi' => null, 'delta_harga' => 6000, 'is_default' => false, 'urutan' => 2],
                ],
            ],
            [
                'kode' => 'susu',
                'nama' => 'Pilihan Susu',
                'tipe' => 'pilih_satu',
                'wajib' => true,
                'urutan' => 2,
                'items' => [
                    ['kode' => 'fresh', 'label' => 'Fresh Milk', 'deskripsi' => 'Standar Barista House Blend', 'delta_harga' => 0, 'is_default' => true, 'urutan' => 1],
                    ['kode' => 'oat', 'label' => 'Oat Milk', 'deskripsi' => 'Plant-based • Creamy', 'delta_harga' => 8000, 'is_default' => false, 'urutan' => 2],
                    ['kode' => 'almond', 'label' => 'Almond Milk', 'deskripsi' => 'Plant-based • Nutty & Light', 'delta_harga' => 8000, 'is_default' => false, 'urutan' => 3],
                    ['kode' => 'skim', 'label' => 'Skim Milk', 'deskripsi' => 'Low Fat Dairy', 'delta_harga' => 0, 'is_default' => false, 'urutan' => 4],
                ],
            ],
            [
                'kode' => 'es',
                'nama' => 'Level Es',
                'tipe' => 'pilih_satu',
                'wajib' => true,
                'urutan' => 3,
                'items' => [
                    ['kode' => 'normal', 'label' => 'Normal Ice', 'deskripsi' => null, 'delta_harga' => 0, 'is_default' => true, 'urutan' => 1],
                    ['kode' => 'less', 'label' => 'Less Ice (50%)', 'deskripsi' => null, 'delta_harga' => 0, 'is_default' => false, 'urutan' => 2],
                    ['kode' => 'no', 'label' => 'No Ice', 'deskripsi' => null, 'delta_harga' => 0, 'is_default' => false, 'urutan' => 3],
                ],
            ],
            [
                'kode' => 'manis',
                'nama' => 'Level Manis',
                'tipe' => 'pilih_satu',
                'wajib' => true,
                'urutan' => 4,
                'items' => [
                    ['kode' => 'normal', 'label' => 'Normal (100%)', 'deskripsi' => null, 'delta_harga' => 0, 'is_default' => true, 'urutan' => 1],
                    ['kode' => 'less', 'label' => 'Less (50%)', 'deskripsi' => null, 'delta_harga' => 0, 'is_default' => false, 'urutan' => 2],
                    ['kode' => 'no', 'label' => 'No Sugar (0%)', 'deskripsi' => null, 'delta_harga' => 0, 'is_default' => false, 'urutan' => 3],
                ],
            ],
            [
                'kode' => 'addon',
                'nama' => 'Tambahan / Add-ons',
                'tipe' => 'pilih_banyak',
                'wajib' => false,
                'urutan' => 5,
                'items' => [
                    ['kode' => 'shot', 'label' => 'Extra Espresso Shot', 'deskripsi' => '+1 Single origin ristretto', 'delta_harga' => 7000, 'is_default' => false, 'urutan' => 1],
                    ['kode' => 'foam', 'label' => 'Vanilla Sweet Cream Foam', 'deskripsi' => 'Cold foam topping velvet texture', 'delta_harga' => 6000, 'is_default' => false, 'urutan' => 2],
                ],
            ],
        ];

        $sekarang = now();
        $pasangan = [];

        foreach ($grup as $g) {
            $idGrup = DB::table('opsi_grup')->insertGetId([
                'kode' => $g['kode'],
                'nama' => $g['nama'],
                'tipe' => $g['tipe'],
                'wajib' => $g['wajib'],
                'urutan' => $g['urutan'],
                'aktif' => true,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ]);

            foreach ($g['items'] as $item) {
                DB::table('opsi_item')->insert([
                    'id_opsi_grup' => $idGrup,
                    'kode' => $item['kode'],
                    'label' => $item['label'],
                    'deskripsi' => $item['deskripsi'],
                    'delta_harga' => $item['delta_harga'],
                    'is_default' => $item['is_default'],
                    'urutan' => $item['urutan'],
                    'aktif' => true,
                    'created_at' => $sekarang,
                    'updated_at' => $sekarang,
                ]);
            }

            $pasangan[] = $idGrup;
        }

        // Produk yang sudah ada tetap memakai semua opsi seperti sebelumnya.
        foreach (DB::table('produk')->pluck('id_produk') as $idProduk) {
            foreach ($pasangan as $idGrup) {
                DB::table('produk_opsi_grup')->insert([
                    'id_produk' => $idProduk,
                    'id_opsi_grup' => $idGrup,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('produk_opsi_grup');
        Schema::dropIfExists('opsi_item');
        Schema::dropIfExists('opsi_grup');
    }
};
