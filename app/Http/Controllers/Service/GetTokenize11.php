<?php

namespace App\Http\Controllers\Service;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Exception;

class GetTokenize11 extends Controller
{
        /**
     * Get token or detoken value for dealer or beneficiary.
     *
     * @param  string  $token  (1 for token, 2 for detoken)
     * @param  string  $dealerorbeneficiery  (1 = dealer, 2 = beneficiary)
     * @param  string  $utoken  UID or token string
     * @return string
     */
    public function getTokenize11($token, $dealerorbeneficiery, $utoken)
    {
        $auaResponse = "111";
        $ref = "";

        // Check SOP logging flag from config/app.php
        if (Config::get('app.sopLogsFlag') === "1") {
            Log::info("Inside token list");
        }

        if (strcasecmp($dealerorbeneficiery, "1") === 0) {
            // Dealer
            try {
                if ($token === "1") {
                    $ref = "http://10.92.195.211:8080/Vault/voult/token/" . $utoken;
                } elseif ($token === "2") {
                    $ref = "http://10.92.195.211:8080/Vault/voult/detoken/" . $utoken;
                }

                $auaResponse = $this->callVaultService($ref);

            } catch (Exception $e) {
                Log::error("Vault API error (dealer): " . $e->getMessage());
            }

        } elseif (strcasecmp($dealerorbeneficiery, "2") === 0) {
            // Beneficiary
            try {
                if ($token === "1") {
                    $ref = "http://10.92.195.211:8080/Vault/voult/bene_vault/token/" . $utoken;
                } elseif ($token === "2") {
                    $ref = "http://10.92.195.211:8080/Vault/voult/bene_vault/detoken/" . $utoken;
                }

                $auaResponse = $this->callVaultService($ref);

                if (Config::get('app.sopLogsFlag') === "1") {
                    Log::info("Vault token or UID response: " . $auaResponse);
                }

            } catch (Exception $e) {
                Log::error("Vault API error (beneficiary): " . $e->getMessage());
            }
        }

        return $auaResponse;
    }

    private function callVaultService($url)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json"
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch));
        }

        curl_close($ch);
        return $response;
    }
}
