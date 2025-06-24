<?php

namespace App\Http\Controllers\API;

use app\Http\Controllers\API\Controller;
use Illuminate\Http\Request;

class AuthenticateUserWithOTP extends Controller
{
    public function authenticateUserWithOTP(Request $request)
    {
        $mobile = $request->input('mobile');
        $otp = $request->input('otp');
        $hhdid = $request->input('hhdid');
        $sessionid = $request->input('sessionid');

        Log::info("Authentication attempt - Mobile: $mobile, OTP: $otp, HHDID: $hhdid, SessionID: $sessionid");

        $auth = $this->authenticateOTP($mobile, $otp);

        if ($auth === 'TRUE') {
            $authResponse = $this->authenticatePDSWithOTP($mobile, $hhdid, $sessionid);
            return response()->xml([
                'authenticateUserWithOTP' => $authResponse
            ]);
        } elseif ($auth === 'EXP') {
            return response()->xml([
                'authenticateUserWithOTP' => [
                    'AuthenticationDetailsOtp' => [
                        '@attributes' => [
                            'ResponseCode' => '144',
                            'Message' => 'OTP का समय सीमा समाप्त हो गया'
                        ]
                    ]
                ]
            ]);
        } else {
            return response()->xml([
                'authenticateUserWithOTP' => [
                    'AuthenticationDetailsOtp' => [
                        '@attributes' => [
                            'ResponseCode' => '134',
                            'Message' => 'OTP का मिलान असफल रहा'
                        ]
                    ]
                ]
            ]);
        }
    }

    private function authenticateOTP($mobile, $otp_password)
    {
        if (!preg_match('/^\d{10}$/', $mobile)) {
            Log::error("Invalid mobile number format: $mobile");
            return 'FALSE';
        }

        try {
            $otpDetails = DB::connection('pds')
                ->table('otpdetails')
                ->where('mobile_no', $mobile)
                ->orderBy('otp_time', 'desc')
                ->first();

            if (!$otpDetails) {
                return 'FALSE';
            }

            $password = $otpDetails->otppassword;
            $dateFormat = $otpDetails->otp_time;

            if ($this->differenceTime($dateFormat) <= 10) {
                if ($password === $otp_password) {
                    // Move OTP to temp table
                    DB::connection('pds')
                        ->table('otpdetail_temps')
                        ->insert([
                            'mobile_no' => $mobile,
                            'otppassword' => $password,
                            'otp_time' => $dateFormat,
                            'rationcard_no' => $otpDetails->rationcard_no ?? null
                        ]);

                    // Delete from original table
                    DB::connection('pds')
                        ->table('otpdetails')
                        ->where('mobile_no', $mobile)
                        ->delete();

                    return 'TRUE';
                }
            } else {
                return 'EXP';
            }
        } catch (\Exception $e) {
            Log::error("OTP Authentication Error: " . $e->getMessage());
            $this->pdsErrorRecord('authentiocationOtp', $e->getMessage());
            return 'FALSE';
        }

        return 'FALSE';
    }

    private function authenticatePDSWithOTP($mobile, $hhdSlNo, $sessionid)
    {
        try {
            $dealerUser = DB::connection('pds')
                ->table('dealer_users')
                ->where('mobile', $mobile)
                ->first();

            if (!$dealerUser) {
                return [
                    'AuthenticationDetailsOtp' => [
                        '@attributes' => [
                            'ResponseCode' => '000',
                            'BlockCode' => '201',
                            'GroupId' => '9',
                            'Name' => 'NA'
                        ]
                    ]
                ];
            }

            $uid = $dealerUser->uid;
            $districtid = $dealerUser->district_id;
            $dealerid = $dealerUser->dealer_id;
            $dealeruserid = $dealerUser->id;
            $blockcityid = $dealerUser->block_city_id;
            $DuserName = $dealerUser->f_name . ' ' . $dealerUser->l_name;

            $dealerName = DB::connection('pds')
                ->table('hhd_' . $this->currentMonth() . $this->currentYear() . 'dealers')
                ->where('id', $dealerid)
                ->value('name');

            // Log dealer user activity
            $errorLogsDatabase = config('app.error_logs_database', '1');
            
            if ($errorLogsDatabase === '1') {
                DB::connection('pds')
                    ->table('dealerUserLogs_' . $this->currentMonth() . '_' . $this->currentYear() . '_backups')
                    ->insert([
                        'uid' => $uid,
                        'hhdSlNo' => $hhdSlNo,
                        'dealer_id' => $dealerid,
                        'dealer_user_id' => $dealeruserid,
                        'intime' => now(),
                        'status' => '1',
                        'group_id' => '9',
                        'session_id' => $sessionid,
                        'block_city_id' => $blockcityid,
                        'district_id' => $districtid,
                        'dealerUserName' => $DuserName,
                        'dealerName' => $dealerName,
                        'rabbitMqServerId' => '0'
                    ]);

                DB::connection('pds')
                    ->table('hhd_' . $this->currentMonth() . '_' . $this->currentYear() . '_masters')
                    ->where('hhdSlNo', $hhdSlNo)
                    ->update(['lastLogin' => now()]);
            } elseif ($errorLogsDatabase === '2') {
                $insertData = [
                    'uid' => $uid,
                    'hhdSlNo' => $hhdSlNo,
                    'dealer_id' => $dealerid,
                    'dealer_user_id' => $dealeruserid,
                    'intime' => now()->toDateTimeString(),
                    'status' => '1',
                    'group_id' => '9',
                    'session_id' => $sessionid,
                    'block_city_id' => $blockcityid,
                    'district_id' => $districtid,
                    'dealerUserName' => $DuserName,
                    'dealerName' => $dealerName
                ];
                
                // Implement your sender function here
                $this->sender($insertData);
            }

            return [
                'AuthenticationDetailsOtp' => [
                    '@attributes' => [
                        'ResponseCode' => '000',
                        'BlockCode' => '201',
                        'GroupId' => '9',
                        'Name' => 'NA'
                    ]
                ]
            ];

        } catch (\Exception $e) {
            Log::error("PDS Authentication Error: " . $e->getMessage());
            $this->pdsErrorRecord('authenticatePdsOtp', $e->getMessage());
            
            return [
                'AuthenticationDetailsOtp' => [
                    '@attributes' => [
                        'ResponseCode' => '207',
                        'Message' => 'मशीन सीरियल नंबर मिलान विफल'
                    ]
                ]
            ];
        }
    }

    private function differenceTime($dateTime)
    {
        $otpTime = Carbon::parse($dateTime);
        $currentTime = Carbon::now();
        return $currentTime->diffInMinutes($otpTime);
    }

    private function currentMonth()
    {
        return now()->format('m');
    }

    private function currentYear()
    {
        return now()->format('Y');
    }

    private function pdsErrorRecord($method, $message)
    {
        try {
            DB::connection('pds')
                ->table('pds_error_records')
                ->insert([
                    'method' => $method,
                    'error_message' => $message,
                    'created_at' => now()
                ]);
        } catch (\Exception $e) {
            Log::error("Failed to log PDS error: " . $e->getMessage());
        }
    }

}
