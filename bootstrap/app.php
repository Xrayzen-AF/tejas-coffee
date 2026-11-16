<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureWithinCafeGeofence;
use App\Http\Middleware\PreventBackAfterLogout;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'geofence' => EnsureWithinCafeGeofence::class,
        ]);

        // Halaman yang diakses user login tidak dicache browser,
        // agar tombol Back tidak membuka dashboard setelah logout.
        $middleware->web(append: [
            PreventBackAfterLogout::class,
        ]);

        // Pengguna yang sudah login membuka /login atau /register
        // langsung diarahkan ke dashboard sesuai role-nya.
        $middleware->redirectUsersTo(function () {
            $user = auth()->user();

            return match ($user?->peran) {
                'admin' => route('admin.dashboard'),
                'kasir' => route('kasir.dashboard'),
                'barista' => route('barista.dashboard'),
                'pelanggan' => route('pelanggan.dashboard'),
                default => '/',
            };
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Sesi/CSRF kedaluwarsa (419) tidak ditampilkan sebagai halaman error
        // mentah: pengguna diarahkan kembali ke login / halaman asalnya.
        // Catatan: Laravel mengubah TokenMismatchException menjadi
        // HttpException(419) sebelum callback dipanggil, jadi statusnya yang dicek.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'code' => 'session_expired',
                    'message' => 'Sesi sudah kedaluwarsa. Muat ulang halaman lalu coba lagi.',
                ], 419);
            }

            $pesan = 'Sesi login sudah kedaluwarsa. Silakan coba lagi.';

            if ($request->user()) {
                return redirect()->back()->with('error', $pesan);
            }

            return redirect()->route('login')->with('error', $pesan);
        });

        // Throttle (429) jangan tampil sebagai halaman error mentah: beri pesan
        // yang jelas + lama menunggu, karena ini sering terjadi saat pelanggan
        // salah memasukkan PIN beberapa kali.
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            $tunggu = max(1, (int) ($e->getHeaders()['Retry-After'] ?? 60));
            $pesan = 'Terlalu banyak percobaan. Tunggu '.$tunggu.' detik lalu coba lagi.';

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'code' => 'too_many_requests',
                    'retry_after' => $tunggu,
                    'message' => $pesan,
                ], 429, $e->getHeaders());
            }

            return back()->with('error', $pesan);
        });
    })->create();
