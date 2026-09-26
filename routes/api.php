<?php

use App\Http\Controllers\Vlf\ClientController;
use App\Http\Controllers\Vlf\DiaryController;
use App\Http\Controllers\Vlf\DocumentController;
use App\Http\Controllers\Vlf\FirmController;
use App\Http\Controllers\Vlf\IntakeController;
use App\Http\Controllers\Vlf\InvoiceController;
use App\Http\Controllers\VlfController;
use Illuminate\Support\Facades\Route;

Route::prefix('vlf')->group(function () {
    Route::get('state', [VlfController::class, 'state']);
    Route::get('notifications', [FirmController::class, 'notifications']);
    Route::get('clients/conflict', [ClientController::class, 'conflict']);
    Route::get('documents/{key}/file', [DocumentController::class, 'download']);

    Route::middleware('throttle:60,1')->group(function () {
        // Matters, tasks, time
        Route::post('intake', [IntakeController::class, 'store']);
        Route::put('matters/{ref}', [VlfController::class, 'updateMatter']);
        Route::post('tasks', [VlfController::class, 'storeTask']);
        Route::patch('tasks/{code}', [VlfController::class, 'updateTask']);
        Route::post('time-entries', [VlfController::class, 'storeTimeEntry']);

        // Communication
        Route::post('messages', [VlfController::class, 'storeMessage']);
        Route::post('comments', [VlfController::class, 'storeComment']);

        // Billing
        Route::post('invoices', [InvoiceController::class, 'store']);
        Route::put('invoices/{code}', [VlfController::class, 'updateInvoice']);
        Route::post('invoices/{code}/transition', [InvoiceController::class, 'transition']);

        // Documents
        Route::put('documents/{key}', [VlfController::class, 'updateDocument']);
        Route::post('documents/upload', [DocumentController::class, 'upload']);
        Route::post('documents/{key}/transition', [DocumentController::class, 'transition']);

        // Clients
        Route::post('clients', [ClientController::class, 'store']);
        Route::put('clients/{client}', [ClientController::class, 'update']);

        // Court diary and deadlines
        Route::post('events', [DiaryController::class, 'storeEvent']);
        Route::patch('events/{event}', [DiaryController::class, 'updateEvent']);
        Route::post('deadlines', [DiaryController::class, 'storeDeadline']);
        Route::patch('deadlines/{deadline}', [DiaryController::class, 'updateDeadline']);

        // Firm administration
        Route::post('staff', [FirmController::class, 'storeStaff']);
        Route::patch('staff/{staff}', [FirmController::class, 'updateStaff']);
        Route::put('settings/{key}', [FirmController::class, 'updateSetting']);
        Route::post('notifications', [FirmController::class, 'storeNotification']);
        Route::post('notifications/read-all', [FirmController::class, 'readAllNotifications']);
        Route::post('notifications/{notification}/read', [FirmController::class, 'readNotification']);
    });
});
