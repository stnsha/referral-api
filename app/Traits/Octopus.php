<?php

namespace App\Traits;

use Exception;

trait Octopus
{

    public function callAPI($method, $url, $data, $accesstoken)
    {

        $curl = curl_init();
        switch ($method) {
            case "POST":
                curl_setopt($curl, CURLOPT_POST, 1);
                if ($data)
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                break;
            case "PUT":
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
                if ($data)
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                break;
            default:
                if ($data)
                    $url = sprintf("%s?%s", $url, http_build_query($data));
        }

        // OPTIONS:
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
        ));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

        // EXECUTE:
        $result = curl_exec($curl);
        if (!$result) {
            $error = curl_error($curl);
            $errno = curl_errno($curl);
            curl_close($curl);
            throw new Exception("Connection Failure: [{$errno}] {$error}");
        }
        curl_close($curl);
        return $result;
    }

    /**
     * Get customer details from ODB API
     *
     * @param int $customerId
     * @return array|null
     */
    public function customerDetails($customerId)
    {
        $data = [
            'username' => config('services.odb.username'),
            'password' => config('services.odb.password'),
            'customer_id' => $customerId
        ];
        $apiUrl = config('services.odb.url') . '/customerById.php';
        $response = $this->callAPI('POST', $apiUrl, json_encode($data), '');
        $result = json_decode($response, true);

        return $result;
    }

    /**
     * Get staff details from ODB API
     *
     * @param int $staffId
     * @return array|null
     */
    public function staffDetails($staffId)
    {
        $data = [
            'username' => config('services.odb.username'),
            'password' => config('services.odb.password'),
            'staff_id' => $staffId
        ];
        $apiUrl = config('services.odb.url') . '/staffById.php';
        $response = $this->callAPI('POST', $apiUrl, json_encode($data), '');
        $result = json_decode($response, true);

        return $result;
    }

    /**
     * Get outlet details from ODB API
     *
     * @param int $outletId
     * @return array|null
     */
    public function outletDetails($outletId)
    {
        $data = [
            'username' => config('services.odb.username'),
            'password' => config('services.odb.password'),
            'outlet_id' => $outletId
        ];
        $apiUrl = config('services.odb.url') . '/outletById.php';
        $response = $this->callAPI('POST', $apiUrl, json_encode($data), '');
        $result = json_decode($response, true);

        return $result;
    }
}