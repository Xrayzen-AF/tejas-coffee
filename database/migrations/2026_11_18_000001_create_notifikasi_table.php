<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Notifikasi internal untuk admin (mis. stok menu/bahan habis).
        // Dibaca lewat lonceng di panel admin; dedupe dilakukan di NotifikasiStok.
        Schema::create('notifikasi', function (Blueprint $table) {
            $table->id('id_notifikasi');
            $table->string('tipe', 40);
            $table->unsignedBigInteger('id_produk')->nullable();
            $table->unsignedBigInteger('id_stok_biji')->nullable();
            $table->string('judul');
            $table->string('pesan', 500);
            $table->string('url')->nullable();
            $table->boolean('dibaca')->default(false);
            $table->timestamps();

            $table->index(['dibaca', 'created_at']);
            $table->index(['tipe', 'id_produk']);

            $table->foreign('id_produk')
                ->references('id_produk')->on('produk')
                ->nullOnDelete();
            $table->foreign('id_stok_biji')
                ->references('id_stok_biji')->on('stok_biji')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifikasi');
    }
};
