<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Vlf\ClientController;
use App\Http\Controllers\Vlf\DiaryController;
use App\Http\Controllers\Vlf\DocumentController;
use App\Http\Controllers\Vlf\FirmController;
use App\Http\Controllers\Vlf\IntakeController;
use App\Http\Controllers\Vlf\InvoiceController;
use App\Http\Controllers\VlfAppController;
use App\Http\Controllers\VlfController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/* ── Sign in ── */
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login']);
    Route::get('forgot-password', [AuthController::class, 'showForgot'])->name('password.request');
    Route::post('forgot-password', [AuthController::class, 'sendResetLink'])->middleware('throttle:6,1')->name('password.email');
    Route::get('reset-password/{token}', [AuthController::class, 'showReset'])->name('password.reset');
    Route::post('reset-password', [AuthController::class, 'reset'])->name('password.update');
});
Route::post('logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

/* ── The VLF app ── */
Route::middleware('auth')->group(function () {
    Route::get('app', [VlfAppController::class, 'show'])->name('app');
    Route::redirect('vlf-fixed.html', '/app');
});

/*
 * ── VLF API ──
 * Session-authenticated (same site as the app), CSRF-protected like any web form.
 * Who is acting always comes from the signed-in user, never from the request body.
 */
Route::prefix('api/vlf')->middleware('auth')->group(function () {
    // Anyone signed in: data is filtered to what that person may see.
    Route::get('state', [VlfController::class, 'state']);
    Route::get('notifications', [FirmController::class, 'notifications']);
    Route::get('documents/{key}/file', [DocumentController::class, 'download']);

    Route::middleware('throttle:60,1')->group(function () {
        Route::post('messages', [VlfController::class, 'storeMessage']);
        Route::post('notifications/read-all', [FirmController::class, 'readAllNotifications']);
        Route::post('notifications/{notification}/read', [FirmController::class, 'readNotification']);

        // Firm staff only
        Route::middleware('vlf.role:staff')->group(function () {
            Route::get('clients/conflict', [ClientController::class, 'conflict']);
            Route::post('intake', [IntakeController::class, 'store']);
            Route::put('matters/{ref}', [VlfController::class, 'updateMatter']);
            Route::post('tasks', [VlfController::class, 'storeTask']);
            Route::patch('tasks/{code}', [VlfController::class, 'updateTask']);
            Route::post('time-entries', [VlfController::class, 'storeTimeEntry']);
            Route::post('comments', [VlfController::class, 'storeComment']);

            Route::post('invoices', [InvoiceController::class, 'store']);
            Route::put('invoices/{code}', [VlfController::class, 'updateInvoice']);
            Route::post('invoices/{code}/transition', [InvoiceController::class, 'transition']);

            Route::put('documents/{key}', [VlfController::class, 'updateDocument']);
            Route::post('documents/upload', [DocumentController::class, 'upload']);
            Route::post('documents/{key}/transition', [DocumentController::class, 'transition']);

            Route::post('clients', [ClientController::class, 'store']);
            Route::put('clients/{client}', [ClientController::class, 'update']);
            Route::post('clients/{client}/invite', [ClientController::class, 'invite']);

            Route::post('events', [DiaryController::class, 'storeEvent']);
            Route::patch('events/{event}', [DiaryController::class, 'updateEvent']);
            Route::post('deadlines', [DiaryController::class, 'storeDeadline']);
            Route::patch('deadlines/{deadline}', [DiaryController::class, 'updateDeadline']);

            Route::post('notifications', [FirmController::class, 'storeNotification']);
        });

        // Partners and the firm administrator only
        Route::middleware('vlf.role:firm-admin')->group(function () {
            Route::post('staff', [FirmController::class, 'storeStaff']);
            Route::patch('staff/{staff}', [FirmController::class, 'updateStaff']);
            Route::post('staff/{staff}/invite', [FirmController::class, 'inviteStaff']);
            Route::put('settings/{key}', [FirmController::class, 'updateSetting']);
        });
    });
});
