<?php

use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok', 'service' => 'SimForge API']));

Route::prefix('v1')->group(function () {
    // require __DIR__.'/api/v1/characters.php';
    // require __DIR__.'/api/v1/items.php';
    // require __DIR__.'/api/v1/simulations.php';

    Route::prefix('admin/simc')->group(base_path('routes/api/v1/admin/simc.php'));
});
