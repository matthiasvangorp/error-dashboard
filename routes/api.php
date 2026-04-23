<?php

use App\Http\Controllers\Api\IngestController;
use App\Http\Middleware\VerifyIngestSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/ingest/{project_token}', IngestController::class)
    ->middleware([VerifyIngestSignature::class, 'throttle:ingest'])
    ->name('api.ingest');
