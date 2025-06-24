<?php

namespace App\Http\Controllers\Service;

use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Helpers\BasicFunctions;


class DealerService
{
    public static function authenticate($mobile, $hhdid, $sessionid)
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement('authenticateUserWithOTP');
        $dom->appendChild($root);

        $user = DB::table('dealer_users')->where('mobile', $mobile)->first();
        if (!$user) {
            $response = $dom->createElement('AuthenticationDetailsOtp');
            $response->setAttribute('ResponseCode', '109');
            $response->setAttribute('Message', 'पी.डी.एस.सर्वर पंजीकृत नहीं');
            $root->appendChild($response);

            return response($dom->saveXML(), 200)->header('Content-Type', 'application/xml');
        }

        // **** old inline funciton ***

        // $currentYear = date('Y');

        // $yearId = DB::table('years')->where('name', $currentYear)->value('id');

        // *** new function ***
        $yearId = BasicFunctions::getYearId();

        if (!$yearId) {
            $response = $dom->createElement('AuthenticationDetailsOtp');
            $response->setAttribute('ResponseCode', '999');
            $response->setAttribute('Message', 'वर्ष तालिका से वर्ष आईडी नहीं मिली');
            $root->appendChild($response);

            return response($dom->saveXML(), 200)->header('Content-Type', 'application/xml');
        }
    

        // ** old year fetch logic ** suffix old
        // $month = date('n');
        // $suffix = "{$month}_{$yearId}";

        // ** NEW year fetch logic ** suffix old
        $suffix = BasicFunctions::getMonthYearSuffix();
        if (!$suffix) {
            $response = $dom->createElement('AuthenticationDetailsOtp');
            $response->setAttribute('ResponseCode', '999');
            $response->setAttribute('Message', 'वर्ष तालिका से वर्ष आईडी नहीं मिली');
            $root->appendChild($response);

            return response($dom->saveXML(), 200)->header('Content-Type', 'application/xml');
        }

        $dealerTable = "hhd_{$suffix}_dealers";
        $logTable = "dealerUserLogs_{$suffix}_backups";
        $masterTable = "hhd_{$suffix}_masters";

        $dealerName = DB::table($dealerTable)->where('id', $user->dealer_id)->value('name');
        $dealerUserName = $user->f_name . ' ' . $user->l_name;

        $logData = [
            'uid' => $user->uid,
            'hhdSlNo' => $hhdid,
            'dealer_id' => $user->dealer_id,
            'dealer_user_id' => $user->id,
            'intime' => now(),
            'status' => 1,
            'group_id' => 9,
            'session_id' => $sessionid,
            'block_city_id' => $user->block_city_id,
            'district_id' => $user->district_id,
            'dealerUserName' => $dealerUserName,
            'dealerName' => $dealerName,
            'rabbitMqServerId' => 0
        ];

        try {
            DB::table($logTable)->insert($logData);
            DB::table($masterTable)
                ->where('hhdSlNo', $hhdid)
                ->update(['lastLogin' => now()]);
        } catch (\Exception $e) {
            $response = $dom->createElement('AuthenticationDetailsOtp');
            $response->setAttribute('ResponseCode', '207');
            $response->setAttribute('Message', 'मशीन सीरियल नंबर मिलान विफल');
            $root->appendChild($response);

            return response($dom->saveXML(), 200)->header('Content-Type', 'application/xml');
        }

        $response = $dom->createElement('AuthenticationDetailsOtp');
        $response->setAttribute('ResponseCode', '000');
        $response->setAttribute('block_code', '201');
        $response->setAttribute('group_id', '9');
        $response->setAttribute('name', 'NA');
        $root->appendChild($response);

        return response($dom->saveXML(), 200)->header('Content-Type', 'application/xml');
    }
}
