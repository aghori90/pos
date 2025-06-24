<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Dealers\Dealer;
use App\Models\Dealers\AlertMessage;
use App\Models\Dealers\Version;
use App\Models\Dealers\HhdMaster;
use App\Models\Dealers\Master;

class GetNewDealerList extends Controller
{
    public function getNewDealerList(Request $request)
    {
        // Parse raw XML input
        $xmlInput = simplexml_load_string($request->getContent(), "SimpleXMLElement", LIBXML_NOCDATA);
        if (!$xmlInput) {
            return $this->xmlManualResponse(['ResponseCode' => '400', 'Message' => 'Invalid XML']);
        }

        // Convert to associative array
        $data = json_decode(json_encode($xmlInput), true);

        $requiredFields = ['hhdid', 'versionno', 'signalRange', 'MobileOperaterName', 'simNo', 'privateKey'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return $this->xmlManualResponse(['ResponseCode' => '400', 'Message' => 'Invalid input']);
            }
        }

        $hhdid = trim($data['hhdid']);
        $versionno = trim($data['versionno']);
        $signalRange = (int) $data['signalRange'];
        $MobileOperaterName = trim($data['MobileOperaterName']);
        $simNo = trim($data['simNo']);
        $privateKey = trim($data['privateKey']);

        $pk = 'hhd9870%^94*@!';
        if ($privateKey !== $pk) {
            return $this->xmlManualResponse(['ResponseCode' => '111', 'Message' => 'सीक्रेट टोकन की मिलान असफल रही']);
        }

        $dealerId = $this->checkMachineHhdId($hhdid);
        if ($dealerId === 'NA') {
            return $this->xmlManualResponse(['ResponseCode' => '202', 'Message' => 'आपका मशीन सर्वर में रजिस्टर नहीं है ,सुधार हेतु जिला के साथ संपर्क करें']);
        }

        $dealer = Dealer::where('id', trim($dealerId))->first();
        if (!$dealer) {
            return $this->xmlManualResponse(['ResponseCode' => '501', 'Message' => 'पी.डी.एस.सर्वर में डीलर लिस्ट नहीं है']);
        }

        $status = AlertMessage::value('status');
        if (!$this->getVersion($hhdid, $versionno, $dealer->dealerType, $MobileOperaterName, $signalRange, $simNo)) {
            return $this->xmlManualResponse(['ResponseCode' => '131', 'Message' => 'नवीनतम संस्करण आ गया हैं । कृपया डाउनलोड करे']);
        }

        if ($dealer->active === "1") {
            if (in_array($dealer->dealerType, ["1", "3"])) {
                return $this->xmlManualResponse([
                    'ResponseCode' => '000',
                    'UserName' => $dealer->name,
                    'UID' => $dealer->uid,
                    'MobileNo' => $dealer->mobile,
                    'DealerType' => $dealer->dealerType,
                    'Date' => now()->format('Y-m-d'),
                    'Time' => now()->format('H:i:s'),
                    'weighingFlag' => $dealer->weighingFlag,
                    'dongleFlag' => $dealer->dongleFlag
                ]);
            } else {
                return $this->xmlManualResponse(['ResponseCode' => '204', 'Message' => 'ऑनलाइन नेटवर्क के लिए रजिस्टर नहीं है']);
            }
        } else {
            if (in_array($dealer->dealerType, ["1", "3"])) {
                $tagDealer = Dealer::where('id', $dealer->tagDealerId)->first();
                return $this->xmlManualResponse([
                    'ResponseCode' => '000',
                    'UserName' => $tagDealer->name,
                    'UID' => $tagDealer->uid,
                    'MobileNo' => $tagDealer->mobile,
                    'DealerType' => $tagDealer->dealerType,
                    'Date' => now()->format('Y-m-d'),
                    'Time' => now()->format('H:i:s'),
                    'weighingFlag' => $tagDealer->weighingFlag,
                    'dongleFlag' => $tagDealer->dongleFlag
                ]);
            } else {
                return $this->xmlManualResponse(['ResponseCode' => '203', 'Message' => 'विक्रेता को निलंबित कर दिया गया है ,सुधार हेतु जिला के साथ संपर्क करें']);
            }
        }
    }

    private function xmlManualResponse(array $attributes): \Illuminate\Http\Response
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><getNewDealerList/>');
        $dealerList = $xml->addChild('DealerList');

        foreach ($attributes as $key => $value) {
            $dealerList->addAttribute($key, $value);
        }

        // Prevent Unicode from being encoded as HTML entities
        $xmlString = $xml->asXML();
        $xmlString = mb_convert_encoding($xmlString, 'UTF-8', 'UTF-8');

        return response($xmlString, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }
    
    private function checkMachineHhdId($hhdid)
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

    private function getVersion($hhdid, $versionno, $dealerType, $mobileOperaterName, $signalRange, $simNo)
    {
        try {
            $today = now()->toDateString();
            $latestVersion = Version::where('dealerType', $dealerType)->orderByDesc('id')->first();

            if (empty($latestVersion->version_no)) {
                return false;
            }

            $isSameVersion = strtolower(trim($latestVersion->version_no)) === strtolower(trim($versionno));
            $versionId = $latestVersion->id;

            Master::where('hhdSlNo', trim($hhdid))->update([
                'version_id' => $versionId,
                'signalRange' => $signalRange,
                'mobileOperaterName' => $mobileOperaterName,
                'simNumber' => $simNo,
                'lastLogin' => $today
            ]);

            HhdMaster::where('hhdSlNo', trim($hhdid))->update([
                'version_id' => $versionId,
                'signalRange' => $signalRange,
                'mobileOperaterName' => $mobileOperaterName,
                'simNumber' => $simNo,
                'lastLogin' => $today
            ]);

            return $isSameVersion;
        } catch (\Exception $e) {
            Log::error('getVersion Error: ' . $e->getMessage());
            return false;
        }
    }
}
