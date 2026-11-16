<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Tests\TestCase;

/**
 * Login tidak boleh terasa "expired": tersedia cookie "Ingat saya",
 * sesi berlaku minimal sehari, dan 419 diarahkan kembali dengan pesan.
 */
class LoginPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private function kasir(): Pengguna
    {
        return Pengguna::factory()->create([
            'peran' => 'kasir',
            'status_aktif' => true,
        ]);
    }

    public function test_login_dengan_ingat_saya_menyimpan_remember_token(): void
    {
        $pengguna = $this->kasir();
        $this->assertNull($pengguna->remember_token);

        $this->post('/login', [
            'email' => $pengguna->email,
            'password' => 'password',
            'remember' => '1',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($pengguna->fresh());
        $this->assertNotNull($pengguna->fresh()->remember_token);
    }

    public function test_login_tanpa_ingat_saya_tidak_menyimpan_remember_token(): void
    {
        $pengguna = $this->kasir();

        $this->post('/login', [
            'email' => $pengguna->email,
            'password' => 'password',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($pengguna->fresh());
        $this->assertNull($pengguna->fresh()->remember_token);
    }

    public function test_sesi_kedaluwarsa_diarahkan_ke_login_dengan_pesan(): void
    {
        $request = Request::create('/login', 'POST');
        $request->setLaravelSession($this->app['session']->driver());

        $response = app(ExceptionHandler::class)->render($request, new TokenMismatchException);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(route('login'), $response->headers->get('Location'));
        $this->assertSame('Sesi login sudah kedaluwarsa. Silakan coba lagi.', session('error'));
    }

    public function test_permintaan_json_mendapat_419_json_bukan_halaman_error(): void
    {
        $request = Request::create('/pelanggan/pesanan/1/bayar-qris', 'POST');
        $request->headers->set('Accept', 'application/json');

        $response = app(ExceptionHandler::class)->render($request, new TokenMismatchException);

        $this->assertSame(419, $response->getStatusCode());
        $this->assertSame('session_expired', json_decode($response->getContent(), true)['code']);
    }

    public function test_sesi_login_berlaku_minimal_sehari(): void
    {
        $this->assertGreaterThanOrEqual(1440, config('session.lifetime'));
    }
}
