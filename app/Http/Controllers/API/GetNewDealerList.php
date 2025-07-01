<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;

use App\Models\Dealers\Dealer;
use App\Models\Dealers\AlertMessage;
use App\Models\Dealers\Version;
use App\Models\Dealers\HhdMaster;
use App\Models\Dealers\Master;
use Illuminate\Support\Facades\Log;

class GetNewDealerList extends Controller
{
	public function removeNamespaces($xml){
		$result = new \stdClass();

		foreach ($xml->children() as $child) {
			$key = $child->getName();
			if ($child->children()->count()) {
				$result->$key = $this->removeNamespaces($child);
			} else {
				$result->$key = (string) $child;
			}
		}

		return $result;
	}

    public function getNewDealerList(Request $request)
    {
		$contentType = $request->header('Content-Type');
		if($contentType == 'application/xml'){
			$xmlString = file_get_contents('php://input');

            if (!$xmlString) {
                return $this->xmlManualResponse([
                    'ResponseCode' => '400',
                    'Message' => 'Empty input'
                ]);
            }

			libxml_use_internal_errors(true);
			$xml = simplexml_load_string($xmlString);

			if ($xml === false || gettype($xml) !== 'object') {
                $errors = libxml_get_errors();
                $messages = array_map(fn($e) => trim($e->message), $errors);
                $errorMessage = implode("; ", $messages) ?: 'Invalid XML';

                return $this->xmlManualResponse([
                    'ResponseCode' => '400',
                    'Message' => 'Invalid XML: ' . $errorMessage
                ]);
            }

			$namespaces = $xml->getNamespaces(true);

			$soapBody = $xml->children($namespaces['SOAP-ENV'])->Body ?? null;
            if (!$soapBody) {
                return $this->xmlManualResponse([
                    'ResponseCode' => '400',
                    'Message' => 'SOAP Body not found'
                ]);
            }
			$dealerList = $soapBody->children($namespaces['ns1'])->getNewDealerList ?? null;
            if (!$dealerList) {
                return $this->xmlManualResponse([
                    'ResponseCode' => '400',
                    'Message' => 'getNewDealerList not found'
                ]);
            }
			$request->merge((array)$this->removeNamespaces($dealerList));
		}
        $validator = Validator::make($request->all(), [
            'hhdid' => 'required|string|size:10',
            'versionno' => 'required|string|max:10',
            'signalRange' => 'required|integer|max:10',
            'MobileOperaterName' => 'required|string|max:50',
            'simNo' => 'required|string|size:10',
            'privateKey' => 'required|string',
        ]);

        if ($validator->fails()) {
            // Convert validation errors to a single string message
            $errors = $validator->errors()->all();
            $errorMessage = implode("; ", $errors);

            return $this->xmlManualResponse([
                'ResponseCode' => '400',
                'Message' => 'Invalid input: ' . $errorMessage
            ]);
        }

        $pk = 'hhd9870%^94*@!';
        if ($request->privateKey !== $pk) {
            return $this->xmlManualResponse([
                'ResponseCode' => '111',
                'Message' => 'सीक्रेट टोकन की मिलान असफल रही'
            ]);
        }

        $dealerId = $this->checkMachineHhdId($request->hhdid);

        if ($dealerId === 'NA') {
            return $this->xmlManualResponse([
                'ResponseCode' => '202',
                'Message' => 'आपका मशीन सर्वर में रजिस्टर नहीं है ,सुधार हेतु जिला के साथ संपर्क करें'
            ]);
        }

        $dealer = new Dealer(); // dynamically sets table

        $dealer = $dealer->where('id', trim($dealerId))->first();

        if (!$dealer) {
            return $this->xmlManualResponse([
                'ResponseCode' => '202',
                'Message' => 'डीलर की जानकारी नहीं मिली'
            ]);
        }

        // Alert Message
        $status = AlertMessage::value('status');
        if (!$this->getVersion($request->hhdid, $request->versionno, $dealer->dealerType, $request->MobileOperaterName, $request->signalRange, $request->simNo) === true) {
            return $this->xmlManualResponse([
                'ResponseCode' => '131',
                'Message' => 'नवीनतम संस्करण आ गया हैं । कृपया डाउनलोड करे'
            ]);
        }

        // Dealer active and online check
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
                return $this->xmlManualResponse([
                    'ResponseCode' => '204',
                    'Message' => 'ऑनलाइन नेटवर्क के लिए रजिस्टर नहीं है'
                ]);
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
                return $this->xmlManualResponse([
                    'ResponseCode' => '203',
                    'Message' => 'विक्रेता को निलंबित कर दिया गया है ,सुधार हेतु जिला के साथ संपर्क करें'
                ]);
            }
        }

    }
    private function xmlManualResponse(array $data)
    {
        $soapEnvelope = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><SOAP-ENV:Envelope/>');
        $soapEnvelope->addAttribute('xmlns:SOAP-ENV', 'http://schemas.xmlsoap.org/soap/envelope/');
        $soapEnvelope->addChild('SOAP-ENV:Header', '', 'http://schemas.xmlsoap.org/soap/envelope/');
        $body = $soapEnvelope->addChild('SOAP-ENV:Body', '', 'http://schemas.xmlsoap.org/soap/envelope/');
        $response = $body->addChild('ns1:getNewDealerListResponse', '', 'http://service.fetch.rationcard/');
        $result = $response->addChild('return');

        foreach ($data as $key => $value) {
            $result->addChild($key, htmlspecialchars($value));
        }

        return response($soapEnvelope->asXML(), 200)
            ->header('Content-Type', 'text/xml; charset=utf-8');
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

            // Step 1: Get latest version for dealerType
            $latestVersion = Version::where('dealerType', $dealerType)
                ->orderByDesc('id')
                ->first();

            if (empty($latestVersion->version_no)) {
                return false;
            }

            $versionId = $latestVersion->id;
           $dbVersionNo = $latestVersion->version_no;

            // Step 2: Compare version
            $isSameVersion = strtolower(trim($dbVersionNo)) === strtolower(trim($versionno)); 

            // Step 3: Update hhd_6_12_masters
           $update_month_year_hhd_master = Master::where('hhdSlNo', trim($hhdid))
                ->update([
                    'version_id' => $versionId,
                    'signalRange' => $signalRange,
                    'mobileOperaterName' => $mobileOperaterName,
                    'simNumber' => $simNo,
                    'lastLogin' => $today,
                ]);
        //    if(!$update_month_year_hhd_master){
        //    return response()->json([
        //             'ResponseCode' => 1000,
        //             'Message' => 'hhd month year not updated'
        //         ]);
        //     }

            // Step 4: Update hhd_masters
           $update_hhd_masters= HhdMaster::where('hhdSlNo', trim($hhdid))
                ->update([
                    'version_id' => $versionId,
                    'signalRange' => $signalRange,
                    'mobileOperaterName' => $mobileOperaterName,
                    'simNumber' => $simNo,
                    'lastLogin' => $today,
                ]);

                //  if(!$update_month_year_hhd_master){
                //     return response()->json([
                //                 'ResponseCode' => 1001,
                //                 'Message' => 'hhd master not updated'
                //             ]);
                //         }

            return $isSameVersion;
        } catch (\Exception $e) {
            Log::error('getVersion Error: ' . $e->getMessage());
            return false;
        }
    }
}
