<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\GetNewDealerList;
use App\Http\Controllers\API\AuthenticateUser;

// Route::get('/user', function (Request $request) {
    // return $request->user();
// })->middleware('auth:sanctum');


Route::group(['middleware'=>'api'],function($routes){
    Route::post('register',[UserController::class,'register']);
    Route::post('dealerList',[GetNewDealerList::class,'getNewDealerList']);
    Route::post('authDealer',[AuthenticateUser::class,'authenticateUser']);
});