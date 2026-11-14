<?php

namespace Tests;

use App\Http\Controllers\Admin\SettingController;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    private ?string $storeSettingsBackup = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Setelan toko (termasuk geofence) disimpan di file, bukan database.
        // Tanpa isolasi, geofence yang aktif di mesin dev membuat test
        // halaman pelanggan terlempar ke halaman verifikasi lokasi.
        $path = storage_path('app/'.SettingController::FILE);
        $this->storeSettingsBackup = File::exists($path) ? File::get($path) : null;

        File::put($path, json_encode(
            array_merge(SettingController::load(), ['geofence_enabled' => false]),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        ));
    }

    protected function tearDown(): void
    {
        $path = storage_path('app/'.SettingController::FILE);

        if ($this->storeSettingsBackup === null) {
            File::delete($path);
        } else {
            File::put($path, $this->storeSettingsBackup);
        }

        parent::tearDown();
    }
}
