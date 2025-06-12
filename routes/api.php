<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\GetNewDealerList;

// Route::get('/user', function (Request $request) {
    // return $request->user();
// })->middleware('auth:sanctum');


Route::group(['middleware'=>'api'],function($routes){
    Route::post('register',[UserController::class,'register']);
    Route::post('dealerList',[GetNewDealerList::class,'getNewDealerList']);
});