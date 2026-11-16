<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Login dengan "Ingat saya": Laravel menyimpan token di kolom ini dan
 * memakainya untuk masuk kembali lewat cookie jangka panjang, sehingga
 * sesi 419/kedaluwarsa tidak lagi memaksa mengetik password ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengguna', function (Blueprint $table) {
            $table->rememberToken();
        });
    }

    public function down(): void
    {
        Schema::table('pengguna', function (Blueprint $table) {
            $table->dropRememberToken();
        });
    }
};
