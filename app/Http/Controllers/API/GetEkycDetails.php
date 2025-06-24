<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class GetEkycDetails extends Controller
{
    public function getEkycDetails(Request $request)
    {
        // Step 1: Validate input
        $validator = Validator::make($request->all(), [
            'ekycData' => 'required|string',
            'uid' => 'required|string|size:12',
            'hhdSlNo' => 'required|string',
            'privateKey' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ResponseCode' => 400,
                'Message' => 'Invalid input',
                'Errors' => $validator->errors()
            ], 400);
        }

        // Step 2: Check private key
        $pk = 'hhd9870%^94*@!';
        if ($request->privateKey !== $pk) {
            return response()->json([
                'ResponseCode' => 111,
                'Message' => 'सीक्रेट टोकन की मिलान असफल रही'
            ]);
        }

        $auaUrl = '';
        $auaUrlFlag = '';

        try {
            // You can also hardcode or set this in .env if needed
            $serverIp = config('database.connections.mysql.host');

            $row = DB::table('auaResponseurls')
                ->select('aua_url', 'auaUrlFlag')
                ->where('sever_ip', $serverIp)
                ->where('status', '1')
                ->limit(1)
                ->first();

            if ($row) {
                $auaUrl = $row->aua_url;
                $auaUrlFlag = $row->auaUrlFlag;

                if (empty(trim($auaUrl))) {
                    return response()->json([
                        'ResponseCode' => 201,
                        'Message' => 'aua url blank coming from database'
                    ]);
                }
            } else {
                $auaUrl = 'abc';
            }
        } catch (\Exception $e) {
            Log::error('Ekyc DB error: ' . $e->getMessage());
            $auaUrl = 'abc';
        }

        // Step 3: Return response (real call will happen in helper in future)
        $auaUrlp = "http://10.249.34.231:8080/NicASAServer/ASAMain";

        return response()->json([
            'ResponseCode' => 200,
            'Message' => 'Success',
            'ekycData' => $request->ekycData,
            'uid' => $request->uid,
            'hhdSlNo' => $request->hhdSlNo,
            'auaUrlUsed' => $auaUrlp,
            'auaUrlFlag' => $auaUrlFlag,
            'db_aua_url' => $auaUrl
        ]);
    }
}
