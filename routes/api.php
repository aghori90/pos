<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\GetNewDealerList;
use App\Http\Controllers\API\AuthenticateUser;
use App\Http\Controllers\API\GetEkycDetails;
use App\Http\Controllers\API\GetMemberDetailsController;
use App\Http\Controllers\API\TransactionReportController;
use App\Http\Controllers\API\AuthenticateUserWithOTP;
use App\Http\Controllers\API\InsertTransactionController;
use App\Http\Controllers\Service\GetTokenize11;

Route::group(['middleware'=>'api'],function($routes){
    Route::post('register',[UserController::class,'register']);
    Route::post('dealerList',[GetNewDealerList::class,'getNewDealerList']);
    Route::post('authenticateUser',[AuthenticateUser::class,'authenticateUser']);
    Route::post('authenticateUserWithOTP', [AuthenticateUserWithOTP::class, 'authenticateUserWithOTP']);
    Route::post('insertTransaction', [InsertTransactionController::class, 'insertTransaction']);
    Route::post('vault/tokenize/{token}/{type}/{utoken}', [GetTokenize11::class, 'getTokenize11']);
});

// Api 2 : get Ekyc Details:  
Route::post('/getekycdetails', [GetEkycDetails::class, 'getEkycDetails']);

// Api 3: get Member Details:
Route::post('/getMemberDetails', [GetMemberDetailsController::class, 'getMemberDetails']);

// Api 4: Get Transaction Report Controller:
Route::post('/transactionReport', [TransactionReportController::class, 'transactionReport']);

// api 5 : Insert Transaction: API: