<?php

use App\Http\Controllers\IndexedPriceController;
use Illuminate\Support\Facades\Route;

// Main web page -> Energy Indexed Price Dashboard
Route::get('/', [IndexedPriceController::class, 'index'])->name('home');

// Direct POST /calculate endpoint for web/JSON calls
Route::post('/calculate', [IndexedPriceController::class, 'calculate'])->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// Direct POST /omie/sync endpoint for live OMIE data fetch
Route::post('/omie/sync', [IndexedPriceController::class, 'syncOmie'])->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// Direct API data endpoints for web frontend fetch
Route::get('/api/consumptions', [IndexedPriceController::class, 'getConsumptions']);
Route::get('/api/prices', [IndexedPriceController::class, 'getPrices']);
Route::post('/api/omie/sync', [IndexedPriceController::class, 'syncOmie'])->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
