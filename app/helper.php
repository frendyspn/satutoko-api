<?php
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\FcmNotificationService;

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
            $api_key = 'SpGFIChfZ1u3AzTvXn8E6JhJ9xYz6dEecKIGTZ8JeUo8gmyF3xrQxYc';
        } else {
            $api_key = 'SpGFIChfZ1u3AzTvXn8E6JhJ9xYz6dEecKIGTZ8JeUo8gmyF3xrQxYc';
        }

        $secret = 'N1Lb1AZo';
        
        
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
            'Authorization: '.$api_key.'.'.$secret
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

if (!function_exists('notifKonsumenNewOrder')) {
    function notifKonsumenNewOrder($id_transaksi){
        $getOrder = get_order_kurir($id_transaksi);

        $pesan = 'Halo Kak '.$getOrder->nama_pemesan.', 
Berikut adalah pesanan Kakak:
Id transaksi: '.$getOrder->kode_order.'
Jenis pesanan : '.$getOrder->jenis_layanan.'
Alamat Penjemputan: '.$getOrder->alamat_jemput.'
Alamat Tujuan: '.$getOrder->alamat_antar.'
Tarif: '.$getOrder->tarif.'

Nama Kurir: '.$getOrder->nama_kurir.'
No wa Kurir: '.$getOrder->no_hp_kurir.'

Status: Menuju Alamat Penjemputan';
        
        kirim_wa($getOrder->no_hp_pemesan, $pesan);
    }
}

if (!function_exists('notifKonsumenFinishOrder')) {
    function notifKonsumenFinishOrder($id_transaksi){
        $getOrder = get_order_kurir($id_transaksi);

        $pesan = 'Halo Kak '.$getOrder->nama_pemesan.',';

if ($getOrder->source == 'MANUAL_KURIR') {
$pesan .= 'Terimakasih sudah menggunakan layanan jasa klukQuick. 
';
}

$pesan .= 'Berikut adalah pesanan Kakak:
Id transaksi: '.$getOrder->kode_order.'
Jenis pesanan : '.$getOrder->jenis_layanan.'
Alamat Penjemputan: '.$getOrder->alamat_jemput.'
Alamat Tujuan: '.$getOrder->alamat_antar.'
Tarif: '.$getOrder->tarif.'

Nama Kurir: '.$getOrder->nama_kurir.'
No wa Kurir: '.$getOrder->no_hp_kurir.'

Status: Selesai';

if ($getOrder->source == 'MANUAL_KURIR') {
$pesan .= 'Semoga Kak '.$getOrder->nama_pemesan.' sehat selalu dan bisa order klikQuick kembali di lain waktu 

Terimakasih.
';
}
        
        kirim_wa($getOrder->no_hp_pemesan, $pesan);
    }
}


if (!function_exists('notifAgenNewOrder')) {
    function notifAgenNewOrder($id_transaksi){
        $getOrder = get_order_kurir($id_transaksi);

        $pesan = 'Ada Order Baru Butuh Approve Agen';
        
        kirim_wa($getOrder->no_hp_agen, $pesan);
    }
}

if (!function_exists('get_order_kurir')) {
    function get_order_kurir($id_transaksi){
        $getOrder = DB::table('kurir_order as a')
        ->select('a.*', 'k_pemesan.no_hp as no_hp_pemesan', 'k_pemesan.nama_lengkap as nama_pemesan', 'k_agen.no_hp as no_hp_agen', 'k_kurir.no_hp as no_hp_kurir', 'k_kurir.nama_lengkap as nama_kurir')
        ->leftJoin('rb_konsumen as k_pemesan', 'k_pemesan.id_konsumen', 'a.id_pemesan')
        ->leftJoin('rb_konsumen as k_agen', 'k_agen.id_konsumen', 'a.id_agen')
        ->leftJoin('rb_sopir as b', 'b.id_sopir', 'a.id_sopir')
        ->leftJoin('rb_konsumen as k_kurir', 'k_kurir.id_konsumen', 'b.id_konsumen')
        ->where('a.id',$id_transaksi)
        ->first();

        return $getOrder;
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


if (!function_exists('fcm_notify')) {
    function fcm_notify($id_konsumen, $title, $body, $data = [])
    {
        $konsumen = DB::table('rb_konsumen')->where('id_konsumen',$id_konsumen)->first();
        if(!$konsumen){
            return;
        }

        $response = app(FcmNotificationService::class)
            ->sendToToken($konsumen->device_token, $title, $body, $data);

        // Simpan log ke database
        DB::table('fcm_logs')->insert([
            'id_konsumen' => $id_konsumen,
            'device_token' => $konsumen->device_token,
            'title' => $title,
            'body' => $body,
            'data' => json_encode($response['payload']),
            'response_status' => $response['status'],
            'response_body' => json_encode($response['body']),
            'success' => $response['success'],
            'created_at' => now()
        ]);

        return $response;
    }
}

if (!function_exists('fcm_topic')) {
    function fcm_topic($topic, $title, $body, $data = [])
    {
        $response = app(FcmNotificationService::class)
        ->sendToTopic($topic, $title, $body, $data);

        // Simpan log ke database
        DB::table('fcm_logs')->insert([
            'id_konsumen' => 0,
            'device_token' => $topic,
            'title' => $title,
            'body' => $body,
            'data' => json_encode($response['payload']),
            'response_status' => $response['status'],
            'response_body' => json_encode($response['body']),
            'success' => $response['success'],
            'created_at' => now()
        ]);

        return $response;
    }
}

if (!function_exists('fcm_topic_live_order')) {
    function fcm_topic_live_order($id_transaksi)
    {
        $getTransaksi = DB::table('kurir_order as a')
        ->select('a.*', 'b.kota_id', 'c.city_name')
        ->leftJoin('rb_konsumen as b', 'b.id_konsumen', 'a.id_agen')
        ->leftJoin('tb_ro_cities as c', 'c.city_id', 'b.kota_id')
        ->where('a.id', $id_transaksi)
        ->first();

        // Validasi data
        if (!$getTransaksi || $getTransaksi->kota_id <= 0) {
            echo 'KOSONG';
            return null;
        }

        
            $topic = 'city_' . strtolower(str_replace(' ', '_', $getTransaksi->city_name));
            $title = 'Transaksi Baru';
            $body = "Ada order {$getTransaksi->jenis_layanan}, senilai {$getTransaksi->tarif}";
            $data = [
                "transaction_id" => (string) $id_transaksi,
                "navigate_to" => "live-order"
            ];


            $response = app(FcmNotificationService::class)
            ->sendToTopic($topic, $title, $body, $data);

            // Simpan log ke database
            DB::table('fcm_logs')->insert([
                'id_konsumen' => 0,
                'device_token' => $topic,
                'title' => $title,
                'body' => $body,
                'data' => json_encode($response['payload']),
                'response_status' => $response['status'],
                'response_body' => json_encode($response['body']),
                'success' => $response['success'],
                'created_at' => now()
            ]);

            return $response;
        

        
    }
}