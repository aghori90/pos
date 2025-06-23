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
    public function getNewDealerList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'hhdid' => 'required|string|size:10',
            'versionno' => 'required|string|max:10',
            'signalRange' => 'required|integer|max:10',
            'MobileOperaterName' => 'required|string|max:50',
            'simNo' => 'required|string|size:10',
            'privateKey' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ResponseCode' => 400,
                'Message' => 'Invalid input',
                'Errors' => $validator->errors()
            ], 400);
        }

        $pk = 'hhd9870%^94*@!';
        if ($request->privateKey !== $pk) {
            return response()->json([
                'ResponseCode' => 111,
                'Message' => 'सीक्रेट टोकन की मिलान असफल रही'
            ]);
        }

        $dealerId = $this->checkMachineHhdId($request->hhdid);

        if ($dealerId === 'NA') {
            return response()->json([
                'ResponseCode' => 202,
                'Message' => 'आपका मशीन सर्वर में रजिस्टर नहीं है ,सुधार हेतु जिला के साथ संपर्क करें'
            ]);
        }

        $dealer = new Dealer(); // dynamically sets table

        $dealer = $dealer->where('id', trim($dealerId))->first();

        // echo $dealer->name;
        // echo "===";
        // echo $dealer->dealerType;
        //  die();
    

        if (!$dealer) {
            return response()->json([
                'ResponseCode' => 202,
                'Message' => 'डीलर की जानकारी नहीं मिली'
            ]);
        }

        // Alert Message
        $status = AlertMessage::value('status');

        // Simulated version check - you would implement this
        // echo $this->getVersion($request->hhdid, $request->versionno, $dealer->dealerType, $request->MobileOperaterName, $request->signalRange, $request->simNo);die();
        if (!$this->getVersion($request->hhdid, $request->versionno, $dealer->dealerType, $request->MobileOperaterName, $request->signalRange, $request->simNo) === true) {
                return response()->json([
                'ResponseCode' => 131,
                'Message' => 'नवीनतम संस्करण आ गया हैं । कृपया डाउनलोड करे'
            ]);
           
        }

        // Dealer active and online check
        if ($dealer->active === "1") {
            //DealerType=1 (online dealer) and DealerType=3 (offline dealer)
            if (in_array($dealer->dealerType, ["1", "3"])) {
                return response()->json([
                    'ResponseCode' => 200,
                    'Message' => 'डीलर सूची सफलतापूर्वक प्राप्त हुई',
                    'DealerData' => $dealer
                ]);
            } else {
                return response()->json([
                    'ResponseCode' => 204,
                    'Message' => 'ऑनलाइन नेटवर्क के लिए रजिस्टर नहीं है'
                ]);
            }
        } else {
            // Suspended Dealer, fallback to tagDealerId if allowed
            if (in_array($dealer->dealerType, ["1", "3"])) {
                $tagDealer = new Dealer();
                $tagDealer = $tagDealer->where('id', $dealer->tagDealerId)->first();

                return response()->json([
                    'ResponseCode' => 200,
                    'Message' => 'टैग डीलर से सूची प्राप्त हुई',
                    'DealerData' => $tagDealer
                ]);
            } else {
                return response()->json([
                    'ResponseCode' => 203,
                    'Message' => 'विक्रेता को निलंबित कर दिया गया है ,सुधार हेतु जिला के साथ संपर्क करें'
                ]);
            }
        }
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
