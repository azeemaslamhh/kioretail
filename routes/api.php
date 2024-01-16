<?php

use App\Http\Controllers\DemoAutoUpdateController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\OrdersController;
use App\Http\Controllers\API\ProductsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::controller(DemoAutoUpdateController::class)->group(function () {
    Route::get('fetch-data-general', 'fetchDataGeneral')->name('fetch-data-general');
    Route::get('fetch-data-upgrade', 'fetchDataForAutoUpgrade')->name('data-read');
    Route::get('fetch-data-bugs', 'fetchDataForBugs')->name('fetch-data-bugs');
});

Route::middleware(['verify.token'])->group(function () {    
     Route::controller(OrdersController::class)->group(function () {
         Route::post('/addOrders', 'addOrders')->name('add.orders');         
     });
     Route::controller(ProductsController::class)->group(function () {
        Route::post('/updateProductCallBack', 'updateProductCallBack')->name('update.productCallBack');         
    });
    
 });

