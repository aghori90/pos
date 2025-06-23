<?php

namespace App\Http\Controllers\Service;

class AuthJsonCreator
{
    public static function generateAuthJson(array $parsed, $vaultToken, $udc, $sa, $txn, $bt)
    {
        $xml = new \SimpleXMLElement('<Auth/>');
        $xml->addAttribute('xmlns', 'http://www.uidai.gov.in/authentication/uid-auth-request/2.0');
        $xml->addAttribute('uid', $vaultToken);
        $xml->addAttribute('ver', '2.5');
        $xml->addAttribute('sa', $sa);
        $xml->addAttribute('txn', $txn);
        $xml->addAttribute('tid', 'registered');
        $xml->addAttribute('rc', 'Y');

        $uses = $xml->addChild('Uses');
        $uses->addAttribute('bio', 'y');
        $uses->addAttribute('pi', 'n');
        $uses->addAttribute('pa', 'n');
        $uses->addAttribute('pfa', 'n');
        $uses->addAttribute('pin', 'n');
        $uses->addAttribute('bt', $bt);
        $uses->addAttribute('otp', 'n');

        $meta = $xml->addChild('Meta');
        $meta->addAttribute('udc', $udc);
        $meta->addAttribute('rdsId', $parsed['deviceInfo']['rdsId']);
        $meta->addAttribute('rdsVer', $parsed['deviceInfo']['rdsVer']);
        $meta->addAttribute('dpId', $parsed['deviceInfo']['dpId']);
        $meta->addAttribute('dc', $parsed['deviceInfo']['dc']);
        $meta->addAttribute('mi', $parsed['deviceInfo']['mi']);
        $meta->addAttribute('mc', $parsed['deviceInfo']['mc']);

        $skey = $xml->addChild('Skey', $parsed['skey']['value']);
        $skey->addAttribute('ci', $parsed['skey']['ci']);

        $xml->addChild('Hmac', $parsed['hmac']);

        $data = $xml->addChild('Data', $parsed['data']['value']);
        $data->addAttribute('type', $parsed['data']['type']);

        return $xml->asXML();
    }

    public static function generateAuthJsonNic(array $parsed, $vaultToken, $udc, $sa, $txn, $bt, $serverIp)
    {
        $xml = new \SimpleXMLElement('<Auth/>');
        $xml->addAttribute('xmlns', 'http://www.uidai.gov.in/authentication/uid-auth-request/2.0');
        $xml->addAttribute('uid', $vaultToken);
        $xml->addAttribute('lk', 'PDSJKF7FxnBpYZ5EQ3kI');
        $xml->addAttribute('ver', '2.5');
        $xml->addAttribute('pip', $serverIp);
        $xml->addAttribute('sa', $sa);
        $xml->addAttribute('txn', $txn);
        $xml->addAttribute('tid', 'registered');
        $xml->addAttribute('rc', 'Y');

        $uses = $xml->addChild('Uses');
        $uses->addAttribute('bio', 'y');
        $uses->addAttribute('pi', 'n');
        $uses->addAttribute('pa', 'n');
        $uses->addAttribute('pfa', 'n');
        $uses->addAttribute('pin', 'n');
        $uses->addAttribute('bt', $bt);
        $uses->addAttribute('otp', 'n');

        $meta = $xml->addChild('Meta');
        $meta->addAttribute('udc', $udc);
        $meta->addAttribute('rdsId', $parsed['deviceInfo']['rdsId']);
        $meta->addAttribute('rdsVer', $parsed['deviceInfo']['rdsVer']);
        $meta->addAttribute('dpId', $parsed['deviceInfo']['dpId']);
        $meta->addAttribute('dc', $parsed['deviceInfo']['dc']);
        $meta->addAttribute('mi', $parsed['deviceInfo']['mi']);
        $meta->addAttribute('mc', $parsed['deviceInfo']['mc']);

        $skey = $xml->addChild('Skey', $parsed['skey']['value']);
        $skey->addAttribute('ci', $parsed['skey']['ci']);

        $xml->addChild('Hmac', $parsed['hmac']);

        $data = $xml->addChild('Data', $parsed['data']['value']);
        $data->addAttribute('type', $parsed['data']['type']);

        return $xml->asXML();
    }
}

