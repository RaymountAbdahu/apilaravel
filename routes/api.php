<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ViewController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('login', [AuthController::class, 'login']);
Route::post('logout', [AuthController::class, 'logout']);
Route::get('me', [AuthController::class, 'me']);

Route::middleware('auth:api')->group(function () {
    Route::get('rooms-with-usage', [ViewController::class, 'getAllRoomsWithUsage']);
    Route::get('user', [ViewController::class, 'getAuthenticatedUser']);
    Route::get('fetchUserHome', [ViewController::class, 'getUserHome']);
    Route::get('fetchUserHistory', [ViewController::class, 'getHistory']);
});

Route::get('getAdminRooms', [ViewController::class, 'getAllAdmin']);
Route::get('getAdminHistory', [ViewController::class, 'getAllHistory']);