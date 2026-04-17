<?php

use App\Domain\Simulation\Models\SimulationBatch;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('admin.simc', function ($user) {
    return $user->is_admin;
});

// Per-batch channel: accessible to the user who owns the batch.
Broadcast::channel('batch.{batchId}', function ($user, int $batchId) {
    return SimulationBatch::where('id', $batchId)
        ->where('user_id', $user->id)
        ->exists();
});

// Per-user channel: mirrors the framework default but under a shorter name.
Broadcast::channel('user.{userId}', function ($user, int $userId) {
    return (int) $user->id === $userId;
});
