<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TransactionReportController extends Controller
{
    private $secretKey = 'pdstra@90%';

    public function transactionReport(Request $request)
    {
        $hhdid = $request->input('hhdid');
        $privateKey = $request->input('privateKey');

        if (strcasecmp($privateKey, $this->secretKey) !== 0) {
            return $this->xmlResponse('transactionReport', '111', 'सीक्रेट टोकन की मिलान असफल रही');
        }

        return $this->generateReportXml($hhdid);
    }

    protected function generateReportXml($hhdid)
    {
        $today = Carbon::now()->toDateString();

        try {
            $rows = DB::table('transactions')
                ->select('cardtype_id', 'item_id', DB::raw('SUM(liftedquantity) as liftedquantity'))
                ->where('hhdUniqueId', $hhdid)
                ->whereDate('dateoftransaction', $today)
                ->groupBy('cardtype_id', 'item_id')
                ->orderBy('cardtype_id')
                ->get();

            if ($rows->isEmpty()) {
                return $this->xmlResponse('transactionReport', '022', 'लेन देन विवरण उपलब्ध नहीं है!');
            }

            $xml = "<transactionReport>";
            foreach ($rows as $row) {
                $cardTypeName = $this->getCardTypeName($row->cardtype_id);
                $itemName      = $this->getItemName($row->item_id);
                $qty           = $row->liftedquantity;

                $xml .= "<gethhdreport ResponseCode=\"000\" HhdId=\"{$hhdid}\" ItemName=\"{$itemName}\" Quantity=\"{$qty}\" cardTypeName=\"{$cardTypeName}\" />";
            }
            $xml .= "</transactionReport>";

            return response($xml, 200)
                   ->header('Content-Type', 'application/xml');

        } catch (\Exception $e) {
            Log::error("TransactionReport error: ".$e->getMessage());
            return $this->xmlResponse('transactionReport', '303', 'तकनिकी समस्या है जिला से संपर्क करें');
        }
    }

    private function getCardTypeName($cardTypeId)
    {
        return DB::table('cardtypes')
                 ->where('id', $cardTypeId)
                 ->value('name_hn') ?? 'Unknown';
    }

    private function getItemName($itemId)
    {
        return DB::table('items')
                 ->where('id', $itemId)
                 ->value('name') ?? 'Unknown';
    }

    private function xmlResponse($root, $code, $message)
    {
        $xml = "<{$root}>";
        $xml .= "<gethhdreport ResponseCode=\"{$code}\" Message=\"{$message}\" />";
        $xml .= "</{$root}>";

        return response($xml, 200)
               ->header('Content-Type', 'application/xml');
    }
}
