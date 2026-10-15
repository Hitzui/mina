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
        Route::get('agreement-options', [MobileDataController::class, 'agreementOptions'])
            ->middleware('permission:acuerdos_orden.create|acuerdos_orden.edit')->name('agreements.options');
        Route::post('orders/{orderId}/agreements', [MobileDataController::class, 'storeAgreement'])
            ->middleware('permission:acuerdos_orden.create')->name('orders.agreements.store');
        Route::put('orders/{orderId}/agreements/{agreementId}', [MobileDataController::class, 'updateAgreement'])
            ->middleware('permission:acuerdos_orden.edit')->name('orders.agreements.update');
        Route::delete('orders/{orderId}/agreements/{agreementId}', [MobileDataController::class, 'deleteAgreement'])
            ->middleware('permission:acuerdos_orden.delete')->name('orders.agreements.destroy');
        Route::get('orders/{orderId}/liquidation-options', [MobileDataController::class, 'liquidationOptions'])
            ->middleware('permission:liquidaciones.create')->name('orders.liquidations.options');
        Route::post('orders/{orderId}/liquidations', [MobileDataController::class, 'storeLiquidation'])
            ->middleware('permission:liquidaciones.create')->name('orders.liquidations.store');
        Route::put('orders/{orderId}/liquidations/{liquidationId}', [MobileDataController::class, 'updateLiquidation'])
            ->middleware('permission:liquidaciones.edit')->name('orders.liquidations.update');
        Route::delete('orders/{orderId}/liquidations/{liquidationId}', [MobileDataController::class, 'deleteLiquidation'])
            ->middleware('permission:liquidaciones.delete')->name('orders.liquidations.destroy');
        Route::get('orders/{orderId}/income-options', [MobileDataController::class, 'incomeOptions'])
            ->middleware('permission:ingresos.create|ingresos.edit')->name('orders.incomes.options');
        Route::post('orders/{orderId}/incomes', [MobileDataController::class, 'storeIncome'])
            ->middleware('permission:ingresos.create')->name('orders.incomes.store');
        Route::put('orders/{orderId}/incomes/{incomeId}', [MobileDataController::class, 'updateIncome'])
            ->middleware('permission:ingresos.edit')->name('orders.incomes.update');
        Route::delete('orders/{orderId}/incomes/{incomeId}', [MobileDataController::class, 'deleteIncome'])
            ->middleware('permission:ingresos.delete')->name('orders.incomes.destroy');
        Route::get('orders/{orderId}/cost-options', [MobileDataController::class, 'orderCostOptions'])
            ->middleware('permission:movimientos_costo.create|movimientos_costo.edit')->name('orders.costs.options');
        Route::get('orders/{orderId}/costs', [MobileDataController::class, 'orderCosts'])
            ->middleware('permission:movimientos_costo.view|movimientos_costo.create|movimientos_costo.edit')->name('orders.costs.index');
        Route::post('orders/{orderId}/costs', [MobileDataController::class, 'storeOrderCost'])
            ->middleware('permission:movimientos_costo.create')->name('orders.costs.store');
        Route::put('orders/{orderId}/costs/{costId}', [MobileDataController::class, 'updateOrderCost'])
            ->middleware('permission:movimientos_costo.edit')->name('orders.costs.update');
        Route::delete('orders/{orderId}/costs/{costId}', [MobileDataController::class, 'deleteOrderCost'])
            ->middleware('permission:movimientos_costo.delete')->name('orders.costs.destroy');
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
        Route::get('orders/{orderId}/processes/{processId}/costs', [MobileDataController::class, 'processCosts'])
            ->middleware('permission:movimientos_costo.view|movimientos_costo.create|movimientos_costo.edit')->name('orders.processes.costs.index');
        Route::get('orders/{orderId}/processes/{processId}/cost-options', [MobileDataController::class, 'processCostOptions'])
            ->middleware('permission:movimientos_costo.create|movimientos_costo.edit')->name('orders.processes.cost-options');
        Route::post('orders/{orderId}/processes/{processId}/costs', [MobileDataController::class, 'storeProcessCost'])
            ->middleware('permission:movimientos_costo.create')->name('orders.processes.costs.store');
        Route::put('orders/{orderId}/processes/{processId}/costs/{costId}', [MobileDataController::class, 'updateProcessCost'])
            ->middleware('permission:movimientos_costo.edit')->name('orders.processes.costs.update');
        Route::delete('orders/{orderId}/processes/{processId}/costs/{costId}', [MobileDataController::class, 'deleteProcessCost'])
            ->middleware('permission:movimientos_costo.delete')->name('orders.processes.costs.destroy');
        Route::get('orders/{orderId}/processes/{processId}/materials', [MobileDataController::class, 'processMaterials'])
            ->middleware('permission:movimientos_inventario.view|movimientos_inventario.create|movimientos_inventario.delete')->name('orders.processes.materials.index');
        Route::get('orders/{orderId}/processes/{processId}/material-options', [MobileDataController::class, 'processMaterialOptions'])
            ->middleware('permission:movimientos_inventario.create')->name('orders.processes.material-options');
        Route::post('orders/{orderId}/processes/{processId}/materials', [MobileDataController::class, 'storeProcessMaterial'])
            ->middleware('permission:movimientos_inventario.create')->name('orders.processes.materials.store');
        Route::delete('orders/{orderId}/processes/{processId}/materials/{movementId}', [MobileDataController::class, 'deleteProcessMaterial'])
            ->middleware('permission:movimientos_inventario.delete')->name('orders.processes.materials.destroy');
        Route::get('orders/{orderId}', [MobileDataController::class, 'order'])
            ->middleware('permission:ordenes_trabajo.view')->name('orders.show');
        Route::get('orders/{orderId}/processes/{processId}/equipment/{assignmentId}/usage', [MobileDataController::class, 'equipmentUsage'])
            ->middleware('permission:proceso_equipo.view');
        Route::put('orders/{orderId}/processes/{processId}/equipment/{assignmentId}/usage', [MobileDataController::class, 'updateEquipmentUsage'])
            ->middleware('permission:proceso_equipo.edit');
        Route::post('orders/{orderId}/processes/{processId}/equipment/{assignmentId}/supplies', [MobileDataController::class, 'storeEquipmentSupply'])
            ->middleware(['permission:proceso_equipo.edit', 'permission:movimientos_inventario.create']);
        Route::delete('orders/{orderId}/processes/{processId}/equipment/{assignmentId}/consumptions/{consumptionId}', [MobileDataController::class, 'deleteEquipmentConsumption'])
            ->middleware('permission:proceso_equipo.edit');
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
