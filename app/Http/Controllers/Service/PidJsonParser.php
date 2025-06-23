<?php

namespace App\Http\Controllers\Service;

use Illuminate\Support\Facades\Log;

class PidJsonParser
{
    public static function parse($pidXml)
    {
        try {
            $xml = simplexml_load_string($pidXml);
            $resp = $xml->Resp;
            $deviceInfo = $xml->DeviceInfo;
            $skey = $xml->Skey;
            $hmac = $xml->Hmac;
            $data = $xml->Data;

            return [
                'resp' => [
                    'errCode' => (string) $resp['errCode'],
                    'fCount' => (string) $resp['fCount'] ?? '',
                ],
                'deviceInfo' => [
                    'rdsId' => (string) $deviceInfo['rdsId'],
                    'rdsVer' => (string) $deviceInfo['rdsVer'],
                    'dpId' => (string) $deviceInfo['dpId'],
                    'dc' => (string) $deviceInfo['dc'],
                    'mi' => (string) $deviceInfo['mi'],
                    'mc' => (string) $deviceInfo['mc'],
                ],
                'skey' => [
                    'ci' => (string) $skey['ci'],
                    'value' => (string) $skey,
                ],
                'hmac' => (string) $hmac,
                'data' => [
                    'type' => (string) $data['type'],
                    'value' => (string) $data,
                ],
                'fCount' => (string) $resp['fCount'] ?? ''
            ];
        } catch (\Exception $e) {
            Log::error("PidJsonParser Error: " . $e->getMessage());
            return null;
        }
    }
}

