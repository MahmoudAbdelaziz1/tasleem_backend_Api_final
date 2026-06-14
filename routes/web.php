<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Tasleem API is running',
        'status' => 'active'
    ]);
});

require __DIR__.'/auth.php';
