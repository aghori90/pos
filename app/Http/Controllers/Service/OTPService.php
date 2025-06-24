<?php 
namespace App\Http\Controllers\Service;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OTPService
{
    public static function authenticate($mobile, $otp)
    {
        $record = DB::table('otpdetails')
            ->where('mobile_no', $mobile)
            ->orderByDesc('otp_time')
            ->first();

        if (!$record) return 'FALSE';

        // $otpTime = Carbon::parse($record->otp_time);
        $otpTime = Carbon::parse($record->otp_time)->setTimezone('Asia/Kolkata'); // e.g. 'Asia/Kolkata'

        // -- new exp check : ==
        if (Carbon::now()->gt($otpTime->addMinutes(10))) {
            return 'EXP';
        }


        // -- old exp check: ---

        // if (Carbon::now()->diffInMinutes($otpTime) > 10) {
        //     return 'EXP';
        // }

        if ($record->otppassword === $otp) {
            DB::transaction(function () use ($record) {
                DB::table('otpdetail_temps')->insert([
                    'mobile_no' => $record->mobile_no,
                    'otppassword' => $record->otppassword,
                    'otp_time' => $record->otp_time,
                    'rationcard_no' => $record->rationcard_no
                ]);
                DB::table('otpdetails')->where('mobile_no', $record->mobile_no)->delete();
            });
            return 'TRUE';
        }

        return 'FALSE';
    }
}
