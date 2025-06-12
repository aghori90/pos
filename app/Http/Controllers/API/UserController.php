<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\User;

date_default_timezone_set('Asia/Kolkata');

class UserController extends Controller
{
    public function register(Request $req){

        $validateDATA=Validator::make($req->all(),
            [
                'name'=>'required|string|min:3|max:50',
                'email'=>'required|string|email|max:50|unique:users',
                'password' => 'required|string|min:6|confirmed'
            ]);

            if ($validateDATA->fails()) 
            {
                return response()->json(['errors' => $validateDATA->errors() ], 422);
            }

            $user = User::create([
                'name'=>$req->name, 
                'email'=>$req->email,
                'password'=>Hash::make($req->password)
                ]);
            $user->save();

            // Generate JWT token for the user
            $token = JWTAuth::fromUser($user);

            return response()->json([
                'msg'=>'User Inserted Successfully',
                'user'=>$user,
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => auth()->factory()->getTTL() * 60
            ], 201);
    }

    Protected function respondWithToken($token){
        return response()->json([
            'success'=>true,
            'access_token'=>$token,
            'token_type'=>'bearer',
            'login_user'=>auth()->user()->id,
            'role'=>auth()->user()->role,
            'status'=>auth()->user()->status,
            'loginStatus'=>auth()->user()->first_login,
            // 'expires_in'=>auth()->factory()->getTTL()*60

        ]);
    }//end respond with token 
}
