<?php

use App\Http\Controllers\ProductController;
use App\Http\Controllers\PremiumController;
use App\Http\Middleware\CheckAccessScopes;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::middleware(['verify.shopify'])->group(function () {
    Route::view('/', 'app')->name('home');
    Route::get('/products', [ProductController::class, 'index']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    Route::get('/product/create', [ProductController::class, 'create']);
    Route::post('/product/store', [ProductController::class, 'storeProduct']);
    Route::post('/hello/upload-image', [ProductController::class, 'uploadImage']);
});
