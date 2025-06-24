<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Service\OTPService;
use App\Http\Controllers\Service\DealerService;
use Illuminate\Support\Facades\Response;

class AuthenticationController 
{
    public function authenticateUserWithOTP(Request $request)
    {
        // Parse XML body
        $xmlString = $request->getContent();
        libxml_use_internal_errors(true); // suppress XML parsing errors

        $xml = simplexml_load_string($xmlString);
        if (!$xml) {
            return response('<?xml version="1.0" encoding="UTF-8"?><Error>Invalid XML Format</Error>', 400)
                ->header('Content-Type', 'application/xml');
        }

        // Extract and validate required fields
        $mobile = (string) $xml->mobile ?? null;
        $otp = (string) $xml->otp ?? null;
        $hhdid = (string) $xml->hhdid ?? null;
        $sessionid = (string) $xml->sessionid ?? null;

        if (!$mobile || !$otp || !$hhdid || !$sessionid) {
            return response('<?xml version="1.0" encoding="UTF-8"?><Error>Missing required fields</Error>', 422)
                ->header('Content-Type', 'application/xml');
        }

        $otpResult = OTPService::authenticate($mobile, $otp);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement('authenticateUserWithOTP');
        $dom->appendChild($root);

        if ($otpResult === 'TRUE') {
            $dealerResponse = DealerService::authenticate($mobile, $hhdid, $sessionid);
            return response($dealerResponse, 200)
                ->header('Content-Type', 'application/xml');
        }

        $authElement = $dom->createElement('AuthenticationDetailsOtp');

        if ($otpResult === 'EXP') {
            $authElement->setAttribute('ResponseCode', '144');
            $authElement->setAttribute('Message', 'OTP का समय सीमा समाप्त हो गया');
        } else {
            $authElement->setAttribute('ResponseCode', '134');
            $authElement->setAttribute('Message', 'OTP का मिलान असफल रहा');
        }

        $root->appendChild($authElement);

        return response($dom->saveXML(), 200)
            ->header('Content-Type', 'application/xml');
    }
}
