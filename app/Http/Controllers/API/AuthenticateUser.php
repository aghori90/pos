<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;

use App\Models\AuaResponseurl;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Service\CommonService;

class AuthenticateUser extends Controller
{
    public function authenticateUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'uid_img' => 'required|string',
            'hhdid' => 'required|string|size:10',
            'sessionid' => 'required|string|size:18',
            'authFailCount' => 'required|integer|max:10',
            'authDeviceFlag' => 'required|string|max:5'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ResponseCode' => 400,
                'Message' => 'Invalid input',
                'Errors' => $validator->errors()
            ], 400);
        }

        $uid_img = $request->input('uid_img');
        $hhdid = $request->input('hhdid');
        $sessionid = $request->input('sessionid');
        $authFailCount = $request->input('authFailCount');
        $authDeviceFlag = $request->input('authDeviceFlag');

        $uid = '';
        $vault_token = '';
        $auaUrl = '';
        $auaUrlFlag = '';
        $dealer_id = '';

        if (strlen($sessionid) > 10) {
            $uid = substr($sessionid, 6, 12);
            $sessionid = substr($sessionid, 0, 6);
        }

        try {
            $serverIp = Config::get('app.server_ip');

            $dealer_id = CommonService::checkMachineHhdId($hhdid);

            $row = AuaResponseurl::where('sever_ip', $serverIp)
                ->where('status', $authFailCount)
                ->select('aua_url', 'auaUrlFlag')
                ->first();

            if ($row) {
                $auaUrl = $row->aua_url;
                $auaUrlFlag = $row->auaUrlFlag;
            }
        } catch (\Exception $e) {
            $auaUrl = 'abc';
        }

        $vault_token = CommonService::getVaultToken($uid, $auaUrlFlag);

        if ($auaUrlFlag === '2') {
            $vault_token = $uid;
        }

        try {
            $response = CommonService::checkBioMetric($uid_img, $vault_token, $hhdid, $sessionid, $auaUrl, $auaUrlFlag, $dealer_id);

            if (str_contains($response->flag, 'TRUE')) {
                return CommonService::authenticatePDS($uid, $hhdid, $sessionid);
            } else {
                return CommonService::authenticateFails($uid, $hhdid, $sessionid, $response->flag);
            }
        } catch (\Exception $e) {
            return response()->json([
                'ResponseCode' => '112',
                'Message' => 'UID सर्वर respond नहीं कर रहा है'
            ]);
        }
    }
}

