<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Dipakai controller untuk $this->authorize(...) (Policy otorisasi).
    use AuthorizesRequests;
}
