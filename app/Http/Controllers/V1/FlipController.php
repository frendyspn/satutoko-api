<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use DB;

class FlipController extends Controller
{
    public function __construct(Request $req){
        cekHeaderApiSatuM(getallheaders()['Authorization']);
    }
    
    public function getBankAccount(Request $req){
        $secret_key = $this->getConfig('flip_api');
        $secret_key = base64_encode($secret_key."::");
        
        $payloads = [
            "account_number" => $req->no_rek,
            "bank_code" => $req->bank,
            "inquiry_key" => $req->kode
        ];
        
        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => $this->getConfig('flip_api_link').'v2/disbursement/bank-account-inquiry',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS => http_build_query($payloads),
          CURLOPT_HTTPHEADER => array(
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic '.$secret_key,
          ),
        ));
        
        $response = curl_exec($curl);
        
        curl_close($curl);
        return json_decode($response);
    }
    
    

    
    
    public function flipTransfer(Request $req){
        $secret_key = $this->getConfig('flip_api');
        $secret_key = base64_encode($secret_key."::");
        
        $payloads = [
            "account_number" => $req->no_rek,
            "bank_code" => $req->bank,
            "amount" => $req->amount
        ];
        
        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => $this->getConfig('flip_api_link').'v3/disbursement',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS => http_build_query($payloads),
          CURLOPT_HTTPHEADER => array(
            'Content-Type: application/x-www-form-urlencoded',
            'idempotency-key: '.$req->kode_withdraw,
            'Authorization: Basic '.$secret_key,
          ),
        ));
        
        $response = curl_exec($curl);
        
        curl_close($curl);
        return json_decode($response);
    }
    
    public function cekTransfer(Request $req){
        $secret_key = $this->getConfig('flip_api');
        $secret_key = base64_encode($secret_key."::");
        
        
        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => $this->getConfig('flip_api_link').'v3/get-disbursement?idempotency-key='.$req->kode_withdraw,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'GET',
          CURLOPT_HTTPHEADER => array(
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic '.$secret_key,
          ),
        ));
        
        $response = curl_exec($curl);
        
        curl_close($curl);
        return json_decode($response);
    }
}