<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;
use App\Models\Dealers\AuaResponseurl;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Service\CommonService;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Facades\JWTFactory;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

class AuthenticateUser extends Controller
{
    public function authenticateUser(Request $request)
    {
        // Parse XML Input
        $rawXml = $request->getContent();
        $xml = simplexml_load_string($rawXml, "SimpleXMLElement", LIBXML_NOCDATA);

        if (!$xml) {
            return $this->xmlResponse(['ResponseCode' => '400', 'Message' => 'Invalid XML format']);
        }

        $json = json_decode(json_encode($xml), true);
        $request->merge($json);

        // Validate
        $validator = Validator::make($request->all(), [
            'uid_img' => 'required|string',
            'hhdid' => 'required|string|size:10',
            'sessionid' => 'required|string|size:18',
            'authFailCount' => 'required|integer|max:10',
            'authDeviceFlag' => 'required|string|max:5'
        ]);

        if ($validator->fails()) {
            return $this->xmlResponse([
                'ResponseCode' => '400',
                'Message' => 'Invalid input: ' . $validator->errors()->first()
            ]);
        }

        // Extract values
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
            if (empty($dealer_id)) {
                return $this->xmlResponse([
                    'ResponseCode' => '201',
                    'Message' => 'आपका HDD मशीन सर्वर में रजिस्टर नहीं है ,सुधार हेतु जिला के साथ संपर्क करें'
                ]);
            }

            $row = AuaResponseurl::where('sever_ip', $serverIp)
                ->where('status', $authFailCount)
                ->select('aua_url', 'auaUrlFlag')
                ->first();

            if ($row) {
                $auaUrl = $row->aua_url;
                $auaUrlFlag = $row->auaUrlFlag;
            }
        } catch (\Exception $e) {
            Log::error("Error fetching AUA URL: " . $e->getMessage());
            return $this->xmlResponse([
                'ResponseCode' => '500',
                'Message' => 'AUA URL प्राप्त नहीं हो सका, कृपया बाद में पुनः प्रयास करें'
            ]);
        }

        $vault_token = CommonService::getVaultToken($uid, $auaUrlFlag);
        if ($auaUrlFlag === '2') {
            $vault_token = $uid;
        }

        try {
            $response = CommonService::checkBioMetric($uid_img, $vault_token, $hhdid, $sessionid, $auaUrl, $auaUrlFlag, $dealer_id);

            if (str_contains($response->flag, 'TRUE')) {
                // ✅ Generate JWT token
                $customClaims = [
                    'uid' => $uid,
                    'dealer_id' => $dealer_id,
                    'hhdid' => $hhdid,
                    'sessionid' => $sessionid,
                ];

                $payload = JWTFactory::customClaims($customClaims)->make();
                $token = JWTAuth::encode($payload)->get();

                // ✅ Get XML response and inject token
                $xmlResponse = CommonService::authenticatePDS($uid, $hhdid, $sessionid);
                $xmlContent = $xmlResponse->getContent();

                $xml = new SimpleXMLElement($xmlContent);
                $authNode = $xml->AuthenticationDetails ?? null;

                if ($authNode) {
                    $authNode->addAttribute('jwttoken', $token);
                }

                return response($xml->asXML(), 200)->header('Content-Type', 'application/xml; charset=UTF-8');
            } else {
                return CommonService::authenticateFails($uid, $hhdid, $sessionid, $response->flag);
            }



            // // 🔁 MOCK biometric response for testing (remove this block later)
            
            // // 🔁 MOCK biometric success response
            // $response = (object)[
            //     'flag' => 'TRUE',
            //     'txn' => 'TXN12345'
            // ];

            // // ✅ Generate JWT token manually for test
            // $customClaims = [
            //     'uid' => $uid,
            //     'dealer_id' => $dealer_id,
            //     'hhdid' => $hhdid,
            //     'sessionid' => $sessionid,
            // ];

            // $payload = JWTFactory::customClaims($customClaims)->make();
            // $token = JWTAuth::encode($payload)->get();

            // // ✅ Return sample XML with token
            // return $this->xmlResponse([
            //     'ResponseCode' => '000',
            //     'uid' => $uid ?: '123456789012',
            //     'name' => 'RAJESH KUMAR',
            //     'status' => 'SUCCESS',
            //     'txn' => $response->txn,
            //     'jwttoken' => $token
            // ]);

        } catch (\Exception $e) {
            return $this->xmlResponse([
                'ResponseCode' => '112',
                'Message' => 'UID सर्वर respond नहीं कर रहा है'
            ]);
        }
    }

    // Generates XML Response with attributes
    private function xmlResponse(array $attributes): \Illuminate\Http\Response
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><authenticateUser/>');
        $auth = $xml->addChild('AuthenticationDetails');

        foreach ($attributes as $key => $value) {
            $auth->addAttribute($key, $value);
        }

        $xmlString = mb_convert_encoding($xml->asXML(), 'UTF-8', 'UTF-8');

        return response($xmlString, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
