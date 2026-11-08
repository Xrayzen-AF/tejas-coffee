<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * S3-02: memastikan error tak terduga tidak membocorkan stack trace
 * dan selalu menampilkan halaman 500 kustom saat APP_DEBUG=false.
 */
class ErrorPageTest extends TestCase
{
    public function test_error_500_menampilkan_halaman_kustom_tanpa_stack_trace(): void
    {
        Route::middleware('web')->get('/uji-error-500', function () {
            throw new RuntimeException('Koneksi database terputus (detail internal).');
        });

        config(['app.debug' => false]);

        $response = $this->get('/uji-error-500');

        $response->assertStatus(500)
            ->assertSee('Mohon Maaf, Terjadi Kendala')
            ->assertSee('Kode Insiden:')
            ->assertSee('TJS-ERR-', false)
            ->assertDontSee('Koneksi database terputus')
            ->assertDontSee('RuntimeException');
    }
}
