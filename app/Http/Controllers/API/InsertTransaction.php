<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Services\BioAuthService;
use App\Exceptions\PdsException;

class InsertTransaction extends Controller
{
    protected $bioAuthService;

    public function __construct(BioAuthService $bioAuthService)
    {
        $this->bioAuthService = $bioAuthService;
        $this->middleware('auth:api');
    }

    public function insertTransaction(Request $request)
    {
        // Validate JWT token
        if (!$request->user()) {
            return $this->errorResponse(401, 'Unauthorized');
        }

        // Parse XML input
        $xml = simplexml_load_string($request->getContent());
        if (!$xml) {
            return $this->errorResponse(400, 'Invalid XML input');
        }

        try {
            // Extract data from XML
            $input = $this->parseInput($xml);

            // Validate input
            $this->validateInput($input);

            // Begin transaction
            DB::beginTransaction();

            // Get family details
            $family = $this->getFamilyDetails($input['transactions'][0]['memberid']);
            if (!$family) {
                throw new PdsException('Invalid ration card/member', 222);
            }

            // Get dealer information
            $dealer = $this->getDealerInfo($input['transactions'][0]['dealeruid']);
            if (!$dealer) {
                throw new PdsException('Dealer not found', 229);
            }

            // Validate transaction
            $this->validateTransaction($input, $family, $dealer);

            // Biometric authentication
            $authResponse = $this->authenticateBiometric($input, $family, $dealer);

            if (!$authResponse['success']) {
                $this->logFailedTransaction($input, $family, $dealer, $authResponse['message']);
                throw new PdsException($authResponse['message'], 211);
            }

            // Process successful transaction
            $transactionId = $this->processTransaction($input, $family, $dealer, $authResponse['txn']);

            // Get location details
            $location = $this->getLocationDetails($family->rgi_district_code);

            DB::commit();

            return $this->successResponse($transactionId, $dealer, $location, $input, $family);

        } catch (PdsException $e) {
            DB::rollBack();
            return $this->errorResponse($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse(500, 'Internal server error');
        }
    }

    private function parseInput($xml)
    {
        $transactions = [];
        foreach ($xml->transactions->transaction as $txn) {
            $transactions[] = [
                'rationCardNo' => (string)$txn->rationCardNo,
                'memberid' => (string)$txn->memberid,
                'sessionid' => (string)$txn->sessionid,
                'hhdid' => (string)$txn->hhdid,
                'dealeruid' => (string)$txn->dealeruid,
                'transactiondate' => (string)$txn->transactiondate,
                'itemid' => (int)$txn->itemid,
                'quantity' => (float)$txn->quantity,
                'hhdtransactionno' => (string)$txn->hhdtransactionno,
                'weingFlag' => (string)$txn->weingFlag
            ];
        }

        return [
            'transactions' => $transactions,
            'uid_bio' => (string)$xml->uid_bio,
            'authFailCount' => (int)$xml->authFailCount,
            'hhdMonth' => (string)$xml->hhdMonth,
            'hhdYear' => (string)$xml->hhdYear,
            'authDeviceFlag' => (string)$xml->authDeviceFlag
        ];
    }

    private function validateInput($input)
    {
        $validator = Validator::make($input, [
            'transactions' => 'required|array|min:1|max:2',
            'transactions.*.memberid' => 'required|string',
            'transactions.*.rationCardNo' => 'required|string',
            'uid_bio' => 'required|string',
            'hhdMonth' => 'required|string|size:1',
            'hhdYear' => 'required|string|size:4',
        ]);

        if ($validator->fails()) {
            throw new PdsException('Invalid input data', 400);
        }
    }

    private function getFamilyDetails($memberId)
    {
        return DB::table('hhd_families')
            ->where('member_id', $memberId)
            ->first();
    }

    private function getDealerInfo($dealerUid)
    {
        return DB::table('dealer_users')
            ->where('uid', $dealerUid)
            ->orWhere('uid_enc', $dealerUid)
            ->first();
    }

    private function validateTransaction($input, $family, $dealer)
    {
        // Check transaction time
        if (!$this->isValidTransactionTime()) {
            throw new PdsException('Transactions not allowed at this time', 400);
        }

        // Check month validity
        if (!$this->isValidMonthYear($input['hhdMonth'], $input['hhdYear'])) {
            throw new PdsException('Invalid month/year selected', 222);
        }

        // Check item count
        if (count($input['transactions']) > 2) {
            throw new PdsException('Please select only one item at a time', 222);
        }

        // Check FPS allocation for each item
        foreach ($input['transactions'] as $transaction) {
            if (!$this->checkFpsAllocation(
                $dealer->dealer_id,
                $input['hhdMonth'],
                $input['hhdYear'],
                $transaction['itemid'],
                $family->cardtype_id
            )) {
                throw new PdsException('Distribution not allowed for this month/item', 222);
            }
        }
    }

    private function isValidTransactionTime()
    {
        $now = Carbon::now();
        $startHour = config('pds.transaction_start_hour', 8);
        $endHour = config('pds.transaction_end_hour', 20);
        
        return $now->hour >= $startHour && $now->hour < $endHour;
    }

    private function isValidMonthYear($month, $year)
    {
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;
        
        return ($year == $currentYear && $month <= $currentMonth) || 
               ($year == $currentYear - 1 && $month == 12 && $currentMonth == 1);
    }

    private function checkFpsAllocation($dealerId, $month, $year, $itemId, $cardTypeId)
    {
        return DB::table('fps_allocation')
            ->where('dealer_id', $dealerId)
            ->where('month', $month)
            ->where('year', $year)
            ->where('item_id', $itemId)
            ->where('card_type_id', $cardTypeId)
            ->exists();
    }

    private function authenticateBiometric($input, $family, $dealer)
    {
        return $this->bioAuthService->authenticate(
            $input['uid_bio'],
            $family->vault_token ?? $family->uid,
            $input['transactions'][0]['hhdid'],
            $input['transactions'][0]['sessionid'],
            $dealer->dealer_id,
            $input['transactions'][0]['memberid'],
            $input['transactions'][0]['rationCardNo'],
            $input['authFailCount']
        );
    }

    private function logFailedTransaction($input, $family, $dealer, $error)
    {
        DB::table('transaction_failures')->insert([
            'member_id' => $family->member_id,
            'ration_card_no' => $family->ration_card_no,
            'dealer_id' => $dealer->dealer_id,
            'error_message' => $error,
            'auth_method' => 'biometric',
            'auth_data' => $input['uid_bio'],
            'month' => $input['hhdMonth'],
            'year' => $input['hhdYear'],
            'created_at' => now()
        ]);
    }

    private function processTransaction($input, $family, $dealer, $authTxn)
    {
        $transactionId = 'TRX' . now()->format('YmdHis') . Str::upper(Str::random(4));
        
        foreach ($input['transactions'] as $txnData) {
            DB::table('transactions')->insert([
                'transaction_id' => $transactionId,
                'member_id' => $family->member_id,
                'ration_card_no' => $family->ration_card_no,
                'dealer_id' => $dealer->dealer_id,
                'item_id' => $txnData['itemid'],
                'quantity' => $txnData['quantity'],
                'month' => $input['hhdMonth'],
                'year' => $input['hhdYear'],
                'auth_txn' => $authTxn,
                'auth_method' => 'biometric',
                'hhd_id' => $txnData['hhdid'],
                'session_id' => $txnData['sessionid'],
                'transaction_date' => now(),
                'status' => 'completed',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
        return $transactionId;
    }

    private function getLocationDetails($rgiDistrictCode)
    {
        return DB::table('locations')
            ->select(
                'districts.name as district_name',
                'blocks.name as block_name',
                'panchayats.name as panchayat_name'
            )
            ->join('districts', 'locations.district_id', '=', 'districts.id')
            ->join('blocks', 'locations.block_id', '=', 'blocks.id')
            ->join('panchayats', 'locations.panchayat_id', '=', 'panchayats.id')
            ->where('locations.rgi_district_code', $rgiDistrictCode)
            ->first();
    }

    private function getCardTypeName($cardTypeId)
    {
        $cardTypes = [
            1 => 'APL',
            2 => 'BPL',
            3 => 'AAY old',
            4 => 'ABPL',
			5 => 'P.H.',
			6 => 'AAY',
			7 => 'WHITE',
			8 => 'GREEN',
			9 => 'PH MIGRANTS'
        ];
        
        return $cardTypes[$cardTypeId] ?? 'UNKNOWN';
    }

    private function successResponse($transactionId, $dealer, $location, $input, $family)
    {
        return response()->xml([
            'InsertTransaction' => [
                'InsertTransitionInfo' => [
                    '@attributes' => [
                        'ResponseCode' => '000',
                        'TransactionId' => $transactionId,
                        'ShopName' => $dealer->shop_name ?? '',
                        'PanchayatName' => $location->panchayat_name ?? '',
                        'BlockName' => $location->block_name ?? '',
                        'DistrictName' => $location->district_name ?? '',
                        'License_no' => $dealer->license_no ?? '',
                        'slipMonth' => $input['hhdMonth'],
                        'slipYear' => $input['hhdYear'],
                        'cardnameval' => $this->getCardTypeName($family->cardtype_id),
                        'NFSA' => '0',
                        'footerflag' => '0',
                        'footerflag1' => '0',
                        'msg' => ': 0.00() : 0.00()',
                        'noteflagmsg' => 'https://aahar.jharkhand',
                        'gettxtmsgdetails' => '1967 / 18002125512 ( ) |'
                    ]
                ]
            ]
        ], 200, ['Content-Type' => 'application/xml']);
    }

    private function errorResponse($code, $message)
    {
        $responseCodeMap = [
            400 => '400',
            401 => '401',
            211 => '211',
            222 => '222',
            229 => '229',
            500 => '111'
        ];
        
        $responseCode = $responseCodeMap[$code] ?? '111';
        
        return response()->xml([
            'InsertTransaction' => [
                'InsertTransitionInfo' => [
                    '@attributes' => [
                        'ResponseCode' => $responseCode,
                        'Message' => $message
                    ]
                ]
            ]
        ], $code, ['Content-Type' => 'application/xml']);
    }
}