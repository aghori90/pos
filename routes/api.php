<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\GetNewDealerList;
use App\Http\Controllers\API\AuthenticateUser;

// Route::get('/user', function (Request $request) {
    // return $request->user();
// })->middleware('auth:sanctum');

// Api 1 : getNewDealerList
Route::group(['middleware'=>'api'],function($routes){
    Route::post('register',[UserController::class,'register']);
    Route::post('dealerList',[GetNewDealerList::class,'getNewDealerList']);
    Route::post('authDealer',[AuthenticateUser::class,'authenticateUser']);
});

// Api 2 : get Ekyc Details:
use App\Http\Controllers\API\GetEkycDetails;  
Route::post('/getekycdetails', [GetEkycDetails::class, 'getEkycDetails']);

// Api 3: get Member Details:
use App\Http\Controllers\API\GetMemberDetailsController;
Route::post('/getMemberDetails', [GetMemberDetailsController::class, 'getMemberDetails']);

// Api 4: Get Transaction Report Controller:
use App\Http\Controllers\API\TransactionReportController;
Route::post('/transactionReport', [TransactionReportController::class, 'transactionReport']);

// api 5 : Insert Transaction: API:
