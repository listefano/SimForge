<?php

use App\Domain\Simulation\Http\Controllers\Admin\SimcController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:admin')->group(function () {
    Route::get('status', [SimcController::class, 'status'])->name('admin.simc.status');
    Route::post('install', [SimcController::class, 'install'])->name('admin.simc.install');
});
