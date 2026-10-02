<?php

use App\Http\Controllers\Api\CandidatureController;
use App\Http\Controllers\Api\FactureController;
use App\Http\Controllers\Api\ProduitController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('candidatures', CandidatureController::class)->except('update');
Route::apiResource('produits', ProduitController::class)->except('update');
Route::post('factures', [FactureController::class, 'store']);
