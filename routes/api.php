<?php

use App\Http\Controllers\VlfController;
use Illuminate\Support\Facades\Route;

Route::prefix('vlf')->group(function () {
    Route::get('state', [VlfController::class, 'state']);

    Route::middleware('throttle:60,1')->group(function () {
        Route::post('tasks', [VlfController::class, 'storeTask']);
        Route::post('time-entries', [VlfController::class, 'storeTimeEntry']);
        Route::post('messages', [VlfController::class, 'storeMessage']);
        Route::post('comments', [VlfController::class, 'storeComment']);
        Route::put('invoices/{code}', [VlfController::class, 'updateInvoice']);
        Route::put('matters/{ref}', [VlfController::class, 'updateMatter']);
        Route::put('documents/{key}', [VlfController::class, 'updateDocument']);
    });
});
