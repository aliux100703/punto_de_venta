<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleReturnController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');
Route::middleware('auth')->group(function () {
    Route::get('/shift/start', [ShiftController::class, 'start'])->name('shift.start');
    Route::post('/shift/decline', [ShiftController::class, 'decline'])->name('shift.decline');
    Route::get('/shift/end', [ShiftController::class, 'end'])->name('shift.end');
    Route::post('/shift/end', [CashRegisterController::class, 'close'])->middleware('can:operate-pos')->name('shift.finish');
    Route::get('/audit', [AuditLogController::class, 'index'])->middleware('can:view-audit')->name('audit.index');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::middleware('can:operate-pos')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::resource('products', ProductController::class)->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'can:manage-products');
        Route::resource('customers', CustomerController::class)->except('show');
        Route::get('/sale-returns', [SaleReturnController::class, 'index'])->middleware('can:manage-sales')->name('sale-returns.index');
        Route::post('/sales/{sale}/cancel', [SaleReturnController::class, 'cancel'])->middleware('can:manage-sales')->name('sales.cancel');
        Route::post('/sales/{sale}/returns', [SaleReturnController::class, 'store'])->middleware('can:manage-sales')->name('sales.returns.store');
        Route::get('/sales/{sale}/returns/{saleReturn}', [SaleReturnController::class, 'show'])->name('sale-returns.show');
        Route::resource('sales', SaleController::class)->only(['index', 'show']);
        Route::resource('quotes', QuoteController::class)->only(['index', 'create', 'store', 'show', 'update']);
        Route::post('/quotes/{quote}/convert', [QuoteController::class, 'convert'])->name('quotes.convert');
        Route::resource('expenses', ExpenseController::class)->only(['index', 'store'])->middleware('can:manage-expenses');
        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('/inventory/{product}', [InventoryController::class, 'show'])->name('inventory.show');
        Route::get('/inventory/{product}/movement', [InventoryController::class, 'createMovement'])->middleware('can:manage-inventory')->name('inventory.movement');
        Route::post('/inventory/{product}/movement', [InventoryController::class, 'storeMovement'])->middleware('can:manage-inventory')->name('inventory.movement.store');
        Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
        Route::get('/pos/search-product', [PosController::class, 'searchProduct'])->name('pos.search-product');
        Route::post('/pos/sale', [PosController::class, 'storeSale'])->name('pos.sale.store');
        Route::get('/cash', [CashRegisterController::class, 'index'])->name('cash.index');
        Route::post('/cash/open', [CashRegisterController::class, 'open'])->name('cash.open');
        Route::post('/cash/close', [CashRegisterController::class, 'close'])->name('cash.close');
        Route::middleware('can:manage-catalogs')->group(function () {
            Route::get('/catalogs/{type?}', [CatalogController::class, 'index'])->name('catalogs.index');
            Route::post('/catalogs/{type}', [CatalogController::class, 'store'])->name('catalogs.store');
            Route::patch('/catalogs/{type}/{id}', [CatalogController::class, 'update'])->name('catalogs.update');
        });
        Route::resource('users', UserController::class)->only(['index', 'store', 'update'])->middleware('can:manage-users');
    });
});
require __DIR__.'/auth.php';
