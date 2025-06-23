<?php

namespace App\Http\Controllers\Service;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use App\Models\Dealers\HhdMaster;
use App\Models\Dealers\DealerUser;
use App\Models\Dealers\DealerUserLog;
use App\Models\Dealers\AuthenticateFail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;


class CommonService extends Controller
{
     public static function checkMachineHhdId($hhdid)
    {
        try {
            $record = HhdMaster::where('mappedStatus', 'M')
                ->where('hhdSlNo', trim($hhdid))
                ->first();

            return $record && !empty($record->dealerId) ? $record->dealerId : 'NA';
        } catch (\Exception $e) {
            Log::error('checkMachineHhdId Error: ' . $e->getMessage());
            return 'NA';
        }
    }

    public static function getVaultToken($uid, $auaUrlFlag)
    {
        $user = DealerUser::where('uid', $uid)->first();
        return ($user && $user->uidVaultFlag === '1') ? $user->vault_token : $uid;
    }

    public static function checkBioMetric($uid_img, $vault_token, $hhdid, $sessionid, $auaUrl, $auaUrlFlag, $dealer_id)
    {
        $parsed = PidJsonParser::parse($uid_img);
        $txn = ($auaUrlFlag === '2') ? "NIC{$hhdid}" . now()->format('YmdHis') . "PDS" : "{$hhdid}" . now()->format('YmdHis');
        $bt = ($parsed['fCount'] ?? 0) > 0 ? 'FMR' : 'IIR';

        $authJson = $auaUrlFlag === '1'
            ? AuthJsonCreator::generateAuthJson($parsed, $vault_token, $hhdid, '100101100', $txn, $bt)
            : AuthJsonCreator::generateAuthJsonNic($parsed, $vault_token, $hhdid, 'JH0001ePDS', $txn, $bt, Config::get('app.server_ip'));

        return (object) self::checkNetworkResponseRD($authJson, $auaUrl);
    }

    public static function authenticatePDS($uid, $hhdid, $sessionid)
    {
        $dealer = DealerUser::where('uid', $uid)->first();

        if (!$dealer) {
            return response()->json(['ResponseCode' => '109', 'Message' => 'PDS सर्वर UID पंजीकृत नहीं है']);
        }

        DealerUserLog::create([
            'uid' => $uid,
            'hhdSlNo' => $hhdid,
            'dealer_id' => $dealer->dealer_id,
            'dealer_user_id' => $dealer->id,
            'intime' => now(),
            'status' => '1',
            'group_id' => '9',
            'session_id' => $sessionid,
            'block_city_id' => $dealer->block_city_id,
            'district_id' => $dealer->district_id,
            'dealerUserName' => "{$dealer->f_name} {$dealer->l_name}",
            'dealerName' => $dealer->dealer->name ?? '',
            'rabbitMqServerId' => '0',
        ]);

        HhdMaster::where('hhdSlNo', $hhdid)->update(['lastLogin' => now()]);

        return response()->json([
            'ResponseCode' => '000',
            'BlockCode' => '201',
            'GroupId' => '9',
            'Name' => $dealer->dealer->name ?? '',
            'dealerid' => $dealer->dealer_id,
            'dealerUserId' => $dealer->id
        ]);
    }

    public static function authenticateFails($uid, $hhdid, $sessionid, $flag)
    {
        $fail = AuthenticateFail::firstOrNew([
            'uid' => $uid,
            'hhdUniqueId' => $hhdid,
            'sessionId' => $sessionid
        ]);
        $fail->nooffail = ($fail->nooffail ?? 0) + 1;
        $fail->save();

        return response()->json([
            'ResponseCode' => '10' . $fail->nooffail,
            'Message' => substr($flag, -3) . ': आधार सर्वर से सत्यता की जांच विफल रही ! पुनः प्रयास करे'
        ]);
    }

    public static function checkNetworkResponseRD($authJson, $auaUrl)
    {
        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])->post($auaUrl, $authJson);
            $json = $response->json();

            return [
                'flag' => ($json['AuthRes']['ret'] ?? 'N') === 'Y' ? 'TRUE000' : 'FALSE000',
                'txn' => $json['AuthRes']['txn'] ?? '',
                'info' => '',
                'errorCode' => ($json['AuthRes']['ret'] ?? 'N') === 'Y' ? '0' : '1'
            ];
        } catch (\Exception $e) {
            Log::error('checkNetworkResponseRD error: ' . $e->getMessage());
            return ['flag' => 'FALSE000', 'txn' => '', 'info' => '', 'errorCode' => '1'];
        }
    }
}
