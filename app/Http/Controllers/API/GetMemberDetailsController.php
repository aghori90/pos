<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GetMemberDetailsController extends Controller
{
    public function getMemberDetails(Request $request)
    {
        $aadhaarNo = $request->input('AadhaarNo');
        $idNo = $request->input('idNo');

        if (!$aadhaarNo || !$idNo) {
            return $this->xmlResponse(400, 'AadhaarNo और idNo आवश्यक हैं');
        }

        // Step 1: Get current month and year
        $month = Carbon::now()->format('n');
        $year = Carbon::now()->year;

        // Step 2: Get year_id from `years` table
        $yearRow = DB::table('years')->where('name', $year)->first();
        if (!$yearRow) {
            return $this->xmlResponse(500, 'वर्ष की जानकारी नहीं मिली');
        }

        $yearId = $yearRow->id;
        $tableName = "hhd_{$month}_{$yearId}_cardholders";

        try {
            // Step 3: Fetch member info
            $member = DB::table('secc_families')
                ->select(
                    'secc_cardholder_id',
                    'name',
                    'dob',
                    'gender_in_aadhar',
                    'name_sl',
                    'fathername_sl'
                )
                ->where('id', $idNo)
                ->first();

            if (!$member) {
                return $this->xmlResponse(110, 'सदस्य जानकारी नहीं मिली');
            }

            // Step 4: Get District and Block info
            $cardholder = DB::table($tableName . ' as sc')
                ->leftJoin('secc_districts as sd', 'sd.rgi_district_code', '=', 'sc.rgi_district_code')
                ->leftJoin('secc_blocks as sb', 'sb.rgi_block_code', '=', 'sc.rgi_block_code')
                ->select('sd.name_hi as dname', 'sb.name_hi as bname')
                ->where('sc.id', $member->secc_cardholder_id)
                ->first();

            $district = $cardholder->dname ?? 'NA';
            $block = $cardholder->bname ?? 'NA';

            // Step 5: XML Response
            $xml = "<getMemberDetails>";
            $xml .= "<getdetails ResponseCode=\"000\" ";
            $xml .= "Name=\"" . htmlspecialchars($member->name ?? 'NA') . "\" ";
            $xml .= "DOB=\"" . htmlspecialchars($member->dob ?? 'NA') . "\" ";
            $xml .= "Gender=\"" . htmlspecialchars($member->gender_in_aadhar ?? 'NA') . "\" ";
            $xml .= "LandMarks=\"NA\" ";
            $xml .= "Address=\"NA\" ";
            $xml .= "District=\"" . htmlspecialchars($district) . "\" ";
            $xml .= "Name1=\"" . htmlspecialchars($member->name_sl ?? 'NA') . "\" ";
            $xml .= "FatherName1=\"" . htmlspecialchars($member->fathername_sl ?? 'NA') . "\" ";
            $xml .= "District1=\"" . htmlspecialchars($district) . "\" ";
            $xml .= "Block1=\"" . htmlspecialchars($block) . "\" />";
            $xml .= "</getMemberDetails>";

            return response($xml, 200)->header('Content-Type', 'application/xml');

        } catch (\Exception $e) {
            return $this->xmlResponse(110, 'पी.डी.एस. और एस.आर.डी.एच. सर्वर से जानकारी प्राप्त करने का प्रयास विफल रहा');
        }
    }

    private function xmlResponse($code, $message)
    {
        $xml = "<getMemberDetails>";
        $xml .= "<getdetails ResponseCode=\"$code\" Message=\"" . htmlspecialchars($message) . "\" />";
        $xml .= "</getMemberDetails>";

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }
}
