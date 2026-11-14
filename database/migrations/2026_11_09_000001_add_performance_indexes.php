<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S3-07: indeks untuk query laporan penjualan, papan meja, dan pencarian member.
 * Kolom foreign key (id_meja, id_pelanggan, id_pengguna) tidak diindeks ulang
 * karena MySQL sudah membuat indeks otomatis untuk constraint-nya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            $table->index(['status_pembayaran', 'waktu_pembayaran'], 'idx_transaksi_lunas_waktu');
        });

        Schema::table('pesanan', function (Blueprint $table) {
            $table->index('waktu_pesan', 'idx_pesanan_waktu');
            $table->index(['id_meja', 'status_pesanan'], 'idx_pesanan_meja_status');
        });

        Schema::table('pelanggan', function (Blueprint $table) {
            $table->index('nomor_telepon', 'idx_pelanggan_telepon');
        });

        Schema::table('pengguna', function (Blueprint $table) {
            $table->index('nomor_telepon', 'idx_pengguna_telepon');
        });
    }

    public function down(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            $table->dropIndex('idx_transaksi_lunas_waktu');
        });

        Schema::table('pesanan', function (Blueprint $table) {
            $table->dropIndex('idx_pesanan_waktu');
            $table->dropIndex('idx_pesanan_meja_status');
        });

        Schema::table('pelanggan', function (Blueprint $table) {
            $table->dropIndex('idx_pelanggan_telepon');
        });

        Schema::table('pengguna', function (Blueprint $table) {
            $table->dropIndex('idx_pengguna_telepon');
        });
    }
};
