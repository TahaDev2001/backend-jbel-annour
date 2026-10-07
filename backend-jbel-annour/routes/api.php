<?php

use App\Http\Controllers\Api\CandidatureController;
use App\Http\Controllers\Api\FactureController;
use App\Http\Controllers\Api\ProduitController;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdminFactureController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('candidatures', CandidatureController::class)->except('update');
Route::apiResource('produits', ProduitController::class)->except('update');


Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('factures', [FactureController::class, 'store']);

    Route::middleware(EnsureAdmin::class)->prefix('admin')->group(function () {
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::patch('/users/{id}/approve', [AdminUserController::class, 'approve']);
        Route::patch('/users/{id}/reject', [AdminUserController::class, 'reject']);

        Route::get('/factures', [AdminFactureController::class, 'index']);
        Route::get('/factures/export', [AdminFactureController::class, 'export']);
        Route::get('/factures/download/{filename}', [AdminFactureController::class, 'download'])
            ->where('filename', '[A-Za-z0-9._-]+');
        Route::post('/factures/{id}', [AdminFactureController::class, 'update'])->whereNumber('id');
    });
});



