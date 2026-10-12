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
        Route::get('offline/snapshot', [MobileDataController::class, 'offlineSnapshot'])->name('offline.snapshot');
        Route::post('offline/upload', [MobileDataController::class, 'offlineUpload'])->name('offline.upload');

        Route::prefix('app')->name('app.')->group(function () {
            Route::get('dashboard', [MobileDataController::class, 'dashboard'])->name('dashboard');
            Route::get('catalogs', [MobileDataController::class, 'catalogs'])->name('catalogs');
        });

        Route::get('orders', [MobileDataController::class, 'orders'])
            ->middleware('permission:ordenes_trabajo.view')->name('orders.index');
        Route::get('orders/form-options', [MobileDataController::class, 'orderFormOptions'])
            ->middleware('permission:ordenes_trabajo.create')->name('orders.form-options');
        Route::get('orders/{orderId}/edit-form-options', [MobileDataController::class, 'orderEditFormOptions'])
            ->middleware('permission:ordenes_trabajo.edit')->name('orders.edit-form-options');
        Route::put('orders/{orderId}', [MobileDataController::class, 'updateOrder'])
            ->middleware('permission:ordenes_trabajo.edit')->name('orders.update');
        Route::post('orders', [MobileDataController::class, 'storeOrder'])
            ->middleware('permission:ordenes_trabajo.create')->name('orders.store');
        Route::get('orders/{orderId}/process-form-options', [MobileDataController::class, 'orderProcessFormOptions'])
            ->middleware('permission:procesos_orden.create')->name('orders.process-form-options');
        Route::post('orders/{orderId}/processes', [MobileDataController::class, 'storeOrderProcess'])
            ->middleware('permission:procesos_orden.create')->name('orders.processes.store');
        Route::get('orders/{orderId}/processes/{processId}/employee-work-options', [MobileDataController::class, 'employeeWorkOptions'])
            ->middleware('permission:trabajos_empleado.create')->name('orders.processes.employee-work-options');
        Route::get('orders/{orderId}/processes/{processId}/employee-work-rate', [MobileDataController::class, 'employeeWorkRate'])
            ->middleware('permission:trabajos_empleado.create')->name('orders.processes.employee-work-rate');
        Route::post('orders/{orderId}/processes/{processId}/employee-works', [MobileDataController::class, 'storeEmployeeWork'])
            ->middleware('permission:trabajos_empleado.create')->name('orders.processes.employee-works.store');
        Route::get('orders/{orderId}/processes/{processId}/equipment-options', [MobileDataController::class, 'processEquipmentOptions'])
            ->middleware('permission:proceso_equipo.create')->name('orders.processes.equipment-options');
        Route::get('orders/{orderId}/processes/{processId}/equipment-depreciation', [MobileDataController::class, 'processEquipmentDepreciation'])
            ->middleware('permission:proceso_equipo.create')->name('orders.processes.equipment-depreciation');
        Route::post('orders/{orderId}/processes/{processId}/equipment', [MobileDataController::class, 'storeProcessEquipment'])
            ->middleware('permission:proceso_equipo.create')->name('orders.processes.equipment.store');
        Route::get('orders/{orderId}', [MobileDataController::class, 'order'])
            ->middleware('permission:ordenes_trabajo.view')->name('orders.show');
        Route::post('orders/{orderId}/finalize', [MobileDataController::class, 'finalizeOrder'])
            ->middleware('permission:ordenes_trabajo.edit')->name('orders.finalize');
        Route::get('clients', [MobileDataController::class, 'clients'])
            ->middleware('permission:clientes.view')->name('clients.index');
        Route::get('employees', [MobileDataController::class, 'employees'])
            ->middleware('permission:empleados.view')->name('employees.index');

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
