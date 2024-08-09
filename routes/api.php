<?php

use App\Http\Controllers\ListingsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::group(array('prefix' => '/v1'), function () {

    Route::get('/client-listings', [ListingsController::class, 'getClientXMLListings']);

    Route::get('/client-bayut-listings/{company_slug}/{pagination_count?}', [ListingsController::class, 'getClientBayutListings']);
    
    Route::get('/listing-status/{company_slug}/{pagination_count?}', [ListingsController::class, 'findUnsuccessfulListings']);
});