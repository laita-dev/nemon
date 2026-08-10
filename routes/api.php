<?php

use App\Http\Controllers\IndexedPriceController;
use Illuminate\Support\Facades\Route;

Route::post('/calculate', [IndexedPriceController::class, 'calculate']);
Route::get('/consumptions', [IndexedPriceController::class, 'getConsumptions']);
Route::get('/prices', [IndexedPriceController::class, 'getPrices']);
Route::post('/omie/sync', [IndexedPriceController::class, 'syncOmie']);
