<?php
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

if (!function_exists('kirim_wa')) {
    function kirim_wa($user_no_hp, $pesan, $sts = 1)
    {
        
        // Watzup Start
// 		$num_key = "oQbP6ITZ6ZN51vnU";
        
//         $dataSending = Array();
// 		$dataSending["api_key"] = "PDQ9ITZLQEWNPTZL";
// 		$dataSending["number_key"] = $num_key;
// 		$dataSending["phone_no"] = "$user_no_hp";
//         $dataSending["message"] = $pesan;
        
//         $url_api = 'https://api.watzap.id/v1/send_message';
        

// 		$curl = curl_init();
// 		curl_setopt_array($curl, array(
// 			CURLOPT_URL => $url_api,
// 			CURLOPT_RETURNTRANSFER => true,
// 			CURLOPT_ENCODING => '',
// 			CURLOPT_MAXREDIRS => 10,
// 			CURLOPT_TIMEOUT => 0,
// 			CURLOPT_FOLLOWLOCATION => true,
// 			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
// 			CURLOPT_CUSTOMREQUEST => 'POST',
// 			CURLOPT_POSTFIELDS => json_encode($dataSending),
// 			CURLOPT_HTTPHEADER => array(
// 				'Content-Type: application/json'
// 			),
// 		));
// 		$response = curl_exec($curl);
// 		curl_close($curl);
        // Watzup End
        if($sts == 1) {
            $api_key = 'Pa5FxaL0g08uxQyMLVeu0Ez4MRyn8PYvTHkD5OpyfXRL6eyIfOMn3Fn';
        } else {
            $api_key = 'Pa5FxaL0g08uxQyMLVeu0Ez4MRyn8PYvTHkD5OpyfXRL6eyIfOMn3Fn';
        }
        
        
        $num_key = '';
        $curl = curl_init();
        $data = [
            'phone' => "$user_no_hp",
            'message' => "$pesan",
        ];

        
        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => 'https://jogja.wablas.com/api/send-message',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS => http_build_query($data),
          CURLOPT_HTTPHEADER => array(
            'Authorization: '.$api_key
          ),
        ));
        
        $response = curl_exec($curl);
        
        curl_close($curl);
        
                
		$dtInsert['nomor'] = $user_no_hp;
		$dtInsert['response'] = $response;
		$dtInsert['number_key'] = $num_key;
		$dtInsert['pesan'] = $pesan;
		$dtInsert['created_at'] = date('Y-m-d H:i:s');
		DB::table('wa_response')->insert($dtInsert);
        // return $response;
    }
}

if (!function_exists('notif_wa_otp')) {
    function notif_wa_otp($no_hp, $otp, $bahasa='', $sts = 1){
        if ($bahasa == 'en') {
            $pesan = 'Your OTP is '.$otp;
        } else {
            $pesan = 'Kode OTP Anda '.$otp.', Jangan Berikan Kode Ini Kesiapapun';
        }
        
        kirim_wa($no_hp, $pesan, $sts);
    }
}

if (!function_exists('hari_ind')) {
    function hari_ind($day){
        if ($day == 'Sunday') {
            return 'Minggu';
        } else if ($day == 'Monday') {
            return 'Senin';
        } else if ($day == 'Tuesday') {
            return 'Selasa';
        } else if ($day == 'Wednesday') {
            return 'Rabu';
        } else if ($day == 'Thursday') {
            return 'Kamis';
        } else if ($day == 'Friday') {
            return 'Jumat';
        } else if ($day == 'Saturday') {
            return 'Sabtu';
        } else {
            return '';
        }
        
    }
}


if (!function_exists('cekHeaderApi')) {
    function cekHeaderApi($token)
    {
        $arr_token = explode(' ',$token);
        if (!Hash::check(date('Ymd').'#'.'SATUKASIR', $arr_token[1])){
            http_response_code(400);
            exit(json_encode(['Message' => 'NOT AUTHORIZED']));
        }
    }
}

if (!function_exists('cekHeaderApiSatuM')) {
    function cekHeaderApiSatuM($token)
    {
        $arr_token = explode(' ',$token);
        
        if (!Hash::check(date('Ymd').'TEST', $arr_token[1])){
            http_response_code(400);
            exit(json_encode(['Message' => 'NOT AUTHORIZED']));
        }
        
        // if (!Hash::check(date('Ymd').'#SATUMILYARBIOPORI', $arr_token[1])){
        //     http_response_code(400);
        //     exit(json_encode(['Message' => 'NOT AUTHORIZED']));
        // }
    }
}

if (!function_exists('cekHeaderApiSatuApps')) {
    function cekHeaderApiSatuApps($token)
    {
        $arr_token = explode(' ',$token);
        if (!Hash::check(date('Ymd').'#'.'SATUAPPSNYA', $arr_token[1])){
            http_response_code(400);
            exit(json_encode(['Message' => 'NOT AUTHORIZED']));
        }
    }
}


if (!function_exists('cekTokenLogin')) {
    function cekTokenLogin($token)
    {
        return $token;
        $cekLogin = DB::table('pos_user')->where('remember_token', $token)->first();
        if (!$cekLogin){
            http_response_code(400);
            exit(json_encode(['Message' => 'NOT AUTHORIZED']));
        }
    }
}


if (!function_exists('cekTokenLoginKonsumen')) {
    function cekTokenLoginKonsumen($token)
    {
        $cekLogin = DB::table('rb_konsumen')->where('remember_token', $token)->first();
        if (!$cekLogin){
            http_response_code(400);
            exit(json_encode(['Message' => 'NOT AUTHORIZED']));
        }
        http_response_code(200);
        return $cekLogin;
    }
}


if (!function_exists('sendNotif')) {
    function sendNotif()
    {
        $url = 'https://fcm.googleapis.com/fcm/send';
        $FcmToken = DB::table('rb_sopir')->whereNotNull('device_token')->pluck('device_token')->all();
        // $FcmToken = ['flLATe5FZxggA68C6MxuQ6:APA91bGu1zWiaG9-iqL9sG-X5T2eRwMC_AKgUKYudHtp7I5Qy40ygqxM8a6MZnB05EgXCKhOThBLbgoMCCfUxMuDFqoOKlbm7dOMgJPtFuGnxcCftAzDx6DEw-9qI6LB5zcFQSuc3HlT'];
          
        $serverKey = 'AAAAlaK5Bn0:APA91bHuXoyvd5SCZRm2FYViRlIqndbkJm9DjwKtESDxMFNjKGeYQLUaBiRj96y6LgrY4CUdP7DiNcuUEcOgkwRIrjeM-AncIRB61w04VB8UTeQ0glTHNiQ7eVzn4fpZGNhGBqT0oLPm';
  
        $data = [
            "registration_ids" => $FcmToken,
            "notification" => [
                "title" => 'Test',
                "body" => 'Test Notification',
            ],
            'data' => [
                'requireInteraction' => true,
            ],
        ];
        $encodedData = json_encode($data);
    
        $headers = [
            'Authorization:key=' . $serverKey,
            'Content-Type: application/json',
        ];
    
        $ch = curl_init();
      
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        // Disabling SSL Certificate support temporarly
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);        
        curl_setopt($ch, CURLOPT_POSTFIELDS, $encodedData);
        // Execute post
        $result = curl_exec($ch);
        if ($result === FALSE) {
            die('Curl failed: ' . curl_error($ch));
        }        
        // Close connection
        curl_close($ch);
        // FCM response
        dd($result); 
    }
}

if (!function_exists('notifOrderBaruWa')) {
    function notifOrderBaruWa()
    {
        $FcmToken = DB::table('rb_sopir')->whereNotNull('device_token')->pluck('id_konsumen')->all();
        for ($i=0; $i < count($FcmToken); $i++) { 
            echo $FcmToken[$i];
            $dtKons = DB::table('rb_konsumen')->select('no_hp')->where('id_konsumen', $FcmToken[$i])->first();
            $pesan = 'Ada Pesanan Baru, Ayo Lihat Sekarang';
            kirim_wa($dtKons->no_hp, $pesan);
        }
        
    }
}

if (!function_exists('notifOrderBaru')) {
    function notifOrderBaru()
    {
        $url = 'https://fcm.googleapis.com/fcm/send';
        $FcmToken = DB::table('rb_sopir')->whereNotNull('device_token')->pluck('device_token')->all();
          
        $serverKey = 'AAAAlaK5Bn0:APA91bHuXoyvd5SCZRm2FYViRlIqndbkJm9DjwKtESDxMFNjKGeYQLUaBiRj96y6LgrY4CUdP7DiNcuUEcOgkwRIrjeM-AncIRB61w04VB8UTeQ0glTHNiQ7eVzn4fpZGNhGBqT0oLPm';
  
        $data = [
            "registration_ids" => $FcmToken,
            "notification" => [
                "title" => 'Ada Order Baru',
                "body" => 'Ada Order Untuk Anda',
            ],
            'data' => [
                'requireInteraction' => true,
            ],
        ];
        $encodedData = json_encode($data);
    
        $headers = [
            'Authorization:key=' . $serverKey,
            'Content-Type: application/json',
        ];
    
        $ch = curl_init();
      
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);        
        curl_setopt($ch, CURLOPT_POSTFIELDS, $encodedData);
        $result = curl_exec($ch);
        if ($result === FALSE) {
            die('Curl failed: ' . curl_error($ch));
        }        
        curl_close($ch);
    }
}


if (!function_exists('notifOrderUpdate')) {
    function notifOrderUpdate($token, $pesan)
    {
        $url = 'https://fcm.googleapis.com/fcm/send';
        $FcmToken = [$token];
          
        $serverKey = 'AAAAlaK5Bn0:APA91bHuXoyvd5SCZRm2FYViRlIqndbkJm9DjwKtESDxMFNjKGeYQLUaBiRj96y6LgrY4CUdP7DiNcuUEcOgkwRIrjeM-AncIRB61w04VB8UTeQ0glTHNiQ7eVzn4fpZGNhGBqT0oLPm';
  
        $data = [
            "registration_ids" => $FcmToken,
            "notification" => [
                "title" => 'Update Order',
                "body" => $pesan,
            ],
            'data' => [
                'requireInteraction' => true,
            ],
        ];
        $encodedData = json_encode($data);
        
        $headers = [
            'Authorization:key=' . $serverKey,
            'Content-Type: application/json',
        ];
    
        $ch = curl_init();
      
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);        
        curl_setopt($ch, CURLOPT_POSTFIELDS, $encodedData);
        $result = curl_exec($ch);
        if ($result === FALSE) {
            die('Curl failed: ' . curl_error($ch));
        }        
        curl_close($ch);
    }
}



if (!function_exists('logApi')) {
    function logApi($controller, $api, $source, $request, $response, $status)
    {
        $arrCtrl = explode('\'',$controller);
        $dtInsert['api'] = $api;
        $dtInsert['source'] = $source;
        $dtInsert['request'] = $request;
        $dtInsert['response'] = $response;
        $dtInsert['response_code'] = $status;
        $dtInsert['controller'] = enc($arrCtrl);
        $dtInsert['created_at'] = date('Y-m-d H:i:s');

        DB::table('log_api')->insert($dtInsert);

        // logApi(static::class, __FUNCTION__, '', json_encode($req->all()), $otp, '200');
        
    }
}