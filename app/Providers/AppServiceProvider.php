<?php

namespace App\Providers;

use App\Models\Pengguna;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Jika aplikasi disajikan lewat HTTPS (hosting, ngrok, Cloudflare Tunnel),
        // paksa URL aset & redirect memakai https agar tidak kena Mixed Content
        // dan geolocation tidak diblokir browser.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Badge sidebar Ops Console — tersedia otomatis di semua view admin.
        View::composer('layouts.admin', function ($view) {
            $view->with([
                'totalKasir' => Pengguna::where('peran', 'kasir')->count(),
                'totalBarista' => Pengguna::where('peran', 'barista')->count(),
                'totalPelanggan' => Pengguna::where('peran', 'pelanggan')->count(),
            ]);
        });
    }
}
