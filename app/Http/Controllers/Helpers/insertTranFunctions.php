<?php

namespace App\Http\Controllers\Helpers;

use Illuminate\Support\Facades\DB;
use Exception;

/**
     ***** THIS FILE CONTAINS ALL THE BASIC FUNCITONS WHICH ARE USED IN DIFFERENT PARTS OF THE API *****
     */
    
class insertTranFunctions
{
//    get hhd Families Transaction table : 6-12
    public static function getHhdFamiliesTransaction($rationno, $hhdMonth, $hhdYear)
    {
        // gets month and year from the parameters: 
        $tableName = "hhd_{$hhdMonth}_{$hhdYear}_families";
        $result = null;

        try {
            $row = DB::table($tableName)
                ->select(
                    'family_count',
                    'cardtype_id',
                    'rgi_block_code',
                    'rationcard_no',
                    'id',
                    'rgi_district_code',
                    'dealer_id',
                    'cardtypeName',
                    'gender_hn',
                    'bfd1',
                    'bfd2',
                    'bfd3',
                    'mobile',
                    'uid',
                    'uid_enc',
                    'uid_verified',
                    'rgi_village_code',
                    'name_sl',
                    'relation_hn',
                    'uidVaultFlag',
                    'vault_token'
                )
                ->where('id', $rationno)
                ->first();

            if ($row) {
                $result = [
                    'cardtype_id'       => $row->cardtype_id,
                    'dealer_id'         => $row->dealer_id,
                    'id'                => $row->id,
                    'rgi_district_code' => $row->rgi_district_code,
                    'rationcard_no'     => $row->rationcard_no,
                    'rgi_block_code'    => $row->rgi_block_code,
                    'bfd1'              => $row->bfd1,
                    'bfd2'              => $row->bfd2,
                    'bfd3'              => $row->bfd3,
                    'mobile'            => $row->mobile,
                    'uid'               => $row->uid,
                    'uid_enc'           => $row->uid_enc,
                    'uid_verified'      => $row->uid_verified,
                    'rgi_village_code'  => $row->rgi_village_code,
                    'cardtypeName'      => $row->cardtypeName,
                    'gender_hn'         => $row->gender_hn,
                    'name_sl'           => $row->name_sl,
                    'relation_hn'       => $row->relation_hn,
                    'member_count'      => $row->family_count,
                    'uidVaultFlag'      => $row->uidVaultFlag,
                    'vault_token'       => $row->vault_token
                ];
            }

        } catch (Exception $e) {
            // Optional: log error
            // Log::error('Error in getHhdFamiliesTransaction: ' . $e->getMessage());

            // Optional: your original error function
            // self::pdsErrorRecord('cardholderData', $e->getMessage());

            $result = null;
        }

        return $result;
    }

}
