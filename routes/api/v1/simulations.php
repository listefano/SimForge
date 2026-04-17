<?php

use App\Domain\Simulation\Http\Controllers\SimulationBatchController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('simulations')->group(function () {
    Route::get('batches', [SimulationBatchController::class, 'index'])
        ->name('simulations.batches.index');

    Route::get('batches/{batch}', [SimulationBatchController::class, 'show'])
        ->name('simulations.batches.show');

    Route::get('batches/{batch}/simulations', [SimulationBatchController::class, 'simulations'])
        ->name('simulations.batches.simulations.index');
});
