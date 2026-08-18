<?php

use App\Http\Controllers\InventoryController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

  Route::resource('products', ProductController::class);

});


Route::middleware(['auth'])->group(function () {

    Route::get(
        '/inventory',
        [InventoryController::class, 'index']
    )->name('inventory.index');


    Route::get(
        '/inventory/{product}',
        [InventoryController::class, 'show']
    )->name('inventory.show');


    Route::get(
        '/inventory/{product}/movement',
        [InventoryController::class, 'createMovement']
    )->name('inventory.movement');


    Route::post(
        '/inventory/{product}/movement',
        [InventoryController::class, 'storeMovement']
    )->name('inventory.movement.store');

});
Route::middleware(['auth'])->group(function () {

    Route::get('/pos', [PosController::class, 'index'])
        ->name('pos.index');

    Route::get('/pos/search-product', [PosController::class, 'searchProduct'])
        ->name('pos.search-product');

});
require __DIR__.'/auth.php';
