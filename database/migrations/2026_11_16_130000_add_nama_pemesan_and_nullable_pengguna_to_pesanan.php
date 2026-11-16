<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alur pelanggan hybrid: selain member yang login, tamu tanpa akun juga
 * boleh memesan. Pesanan tamu tidak punya akun pembuat, jadi `id_pengguna`
 * dibuat nullable dan identitasnya disimpan di `nama_pemesan`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pesanan', function (Blueprint $table) {
            $table->foreignId('id_pengguna')->nullable()->change();
        });

        Schema::table('pesanan', function (Blueprint $table) {
            $table->string('nama_pemesan', 100)->nullable()->after('id_pelanggan');
        });
    }

    public function down(): void
    {
        Schema::table('pesanan', function (Blueprint $table) {
            $table->dropColumn('nama_pemesan');
        });

        Schema::table('pesanan', function (Blueprint $table) {
            $table->foreignId('id_pengguna')->nullable(false)->change();
        });
    }
};
