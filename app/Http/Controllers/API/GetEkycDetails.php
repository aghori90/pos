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
        // Step 1: Parse XML input
        $rawXml = $request->getContent();
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($rawXml);

        if (!$xml || !isset($xml->ekycData) || !isset($xml->uid) || !isset($xml->hhdSlNo) || !isset($xml->privateKey)) {
            return response('<?xml version="1.0"?><getEkycDetails><response ResponseCode="400" Message="Invalid or incomplete XML input" /></getEkycDetails>', 400)
                ->header('Content-Type', 'application/xml');
        }

        $ekycData = (string) $xml->ekycData;
        $uid = (string) $xml->uid;
        $hhdSlNo = (string) $xml->hhdSlNo;
        $privateKey = (string) $xml->privateKey;

        // Step 2: Validate manually
        if (strlen($uid) !== 12 || empty($ekycData) || empty($hhdSlNo) || empty($privateKey)) {
            return response('<?xml version="1.0"?><getEkycDetails><response ResponseCode="400" Message="Required fields are missing or invalid" /></getEkycDetails>', 400)
                ->header('Content-Type', 'application/xml');
        }

        // Step 3: Private key check
        $pk = 'hhd9870%^94*@!';
        if ($privateKey !== $pk) {
            return response('<?xml version="1.0"?><getEkycDetails><response ResponseCode="111" Message="सीक्रेट टोकन की मिलान असफल रही" /></getEkycDetails>', 200)
                ->header('Content-Type', 'application/xml');
        }

        // Step 4: Fetch from DB
        $auaUrl = 'abc';
        $auaUrlFlag = '0';

        try {
            $serverIp = config('database.connections.mysql.host');
            $row = DB::table('auaResponseurls')
                ->select('aua_url', 'auaUrlFlag')
                ->where('sever_ip', $serverIp)
                ->where('status', '1')
                ->first();

            if ($row) {
                $auaUrl = $row->aua_url;
                $auaUrlFlag = $row->auaUrlFlag;

                if (empty(trim($auaUrl))) {
                    return response('<?xml version="1.0"?><getEkycDetails><response ResponseCode="201" Message="aua url blank coming from database" /></getEkycDetails>', 200)
                        ->header('Content-Type', 'application/xml');
                }
            }
        } catch (\Exception $e) {
            Log::error('Ekyc DB error: ' . $e->getMessage());
        }

        $auaUrlp = "http://10.249.34.231:8080/NicASAServer/ASAMain";

        // Step 5: XML Success Response
        $responseXml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
        $responseXml .= "<getEkycDetails>";
        $responseXml .= "<response ";
        $responseXml .= "ResponseCode=\"200\" ";
        $responseXml .= "Message=\"Success\" ";
        $responseXml .= "ekycData=\"" . htmlspecialchars($ekycData) . "\" ";
        $responseXml .= "uid=\"" . htmlspecialchars($uid) . "\" ";
        $responseXml .= "hhdSlNo=\"" . htmlspecialchars($hhdSlNo) . "\" ";
        $responseXml .= "auaUrlUsed=\"" . htmlspecialchars($auaUrlp) . "\" ";
        $responseXml .= "auaUrlFlag=\"" . htmlspecialchars($auaUrlFlag) . "\" ";
        $responseXml .= "db_aua_url=\"" . htmlspecialchars($auaUrl) . "\" />";
        $responseXml .= "</getEkycDetails>";

        return response($responseXml, 200)->header('Content-Type', 'application/xml');
    }

}
