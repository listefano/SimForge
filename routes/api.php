<?php

use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok', 'service' => 'SimForge API']));

// Domain routes will be registered here
// Route::prefix('v1')->group(function () {
//     require __DIR__.'/api/v1/characters.php';
//     require __DIR__.'/api/v1/items.php';
//     require __DIR__.'/api/v1/simulations.php';
// });
