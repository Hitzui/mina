<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\MobileDataController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:api-login')
        ->name('auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::delete('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::delete('auth/tokens', [AuthController::class, 'logoutAll'])->name('auth.logout-all');
        Route::get('bootstrap', [AuthController::class, 'bootstrap'])->name('bootstrap');

        Route::prefix('app')->name('app.')->group(function () {
            Route::get('dashboard', [MobileDataController::class, 'dashboard'])->name('dashboard');
            Route::get('catalogs', [MobileDataController::class, 'catalogs'])->name('catalogs');
        });

        Route::get('orders', [MobileDataController::class, 'orders'])
            ->middleware('permission:ordenes_trabajo.view')->name('orders.index');
        Route::get('orders/{orderId}', [MobileDataController::class, 'order'])
            ->middleware('permission:ordenes_trabajo.view')->name('orders.show');
        Route::get('clients', [MobileDataController::class, 'clients'])
            ->middleware('permission:clientes.view')->name('clients.index');

        Route::get('cash/summary', [MobileDataController::class, 'cashSummary'])
            ->middleware('permission:movimientos_caja.view')->name('cash.summary');
        Route::get('cash/movements', [MobileDataController::class, 'cashMovements'])
            ->middleware('permission:movimientos_caja.view')->name('cash.movements');
        Route::get('cash/collections', [MobileDataController::class, 'collections'])
            ->middleware('permission:cobros.view')->name('cash.collections');
        Route::get('cash/collections/{collectionId}', [MobileDataController::class, 'collection'])
            ->middleware('permission:cobros.view')->name('cash.collections.show');

        Route::get('inventory/products', [MobileDataController::class, 'inventoryProducts'])
            ->middleware('permission:productos.view')->name('inventory.products');
        Route::get('inventory/movements', [MobileDataController::class, 'inventoryMovements'])
            ->middleware('permission:movimientos_inventario.view')->name('inventory.movements');

        Route::get('quotes', [MobileDataController::class, 'quotes'])
            ->middleware('permission:cotizaciones.view')->name('quotes.index');
        Route::get('quotes/{quoteId}', [MobileDataController::class, 'quote'])
            ->middleware('permission:cotizaciones.view')->name('quotes.show');
    });
});
