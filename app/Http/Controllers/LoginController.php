<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Hash;

use Illuminate\Http\Request;
use DB;

class LoginController extends Controller
{
    public function __construct(){
        // cekHeaderApi(getallheaders()['Authorization']);
        $headers = getallheaders();
        $token = $headers['Authorization'] ?? 
                $headers['X-Authorization'] ?? 
                $headers['X-Api-Token'] ?? 
                null;

        if (!$token) {
            return response()->json(['error' => 'Authorization header missing'], 401);
        }

        cekHeaderApi($token);
    }

    private function urlForStoragePath($path)
    {
        if (empty($path) || $path === '-') return $path;

        // If already a full URL, return as-is
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        // Normalize the path:
        // /storage/uploads/sopir/file.jpg -> uploads/sopir/file.jpg (old format)
        // /uploads/sopir/file.jpg -> uploads/sopir/file.jpg (new format)
        $normalized = $path;
        
        // Remove leading /storage/ or storage/ prefix (old format from storage/app/public)
        // if (strpos($normalized, '/storage/') === 0) {
        //     $normalized = substr($normalized, 9); // Remove '/storage/' -> 'uploads/sopir/...'
        // } elseif (strpos($normalized, 'storage/') === 0) {
        //     $normalized = substr($normalized, 8); // Remove 'storage/' -> 'uploads/sopir/...'
        // }
        
        // Remove leading slash if exists for new format
        $normalized = ltrim($normalized, '/');
        
        // Build full URL
        // Option 1: Try Laravel's asset() helper
        if (function_exists('asset')) {
            return asset($normalized);
        }

        // Option 2: Use APP_URL from environment
        $appUrl = env('APP_URL', null);
        if ($appUrl) {
            return rtrim($appUrl, '/') . '/' . $normalized;
        }

        // Option 3: Fallback to current request host
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        $scheme = (!empty($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http'));
        return $scheme . '://' . $host . '/' . $normalized;
    }
    
    public function loginTest(Request $req){
        // notif_wa_otp('081340094756', '12345');
        // $token = cekHeaderApi(getallheaders()['Authorization']);
        // return cekHeaderApi($token);
    }

    public function cekLogin(Request $req){
        $validated = $req->validate([
            'username' => 'required',
            'token' => 'required',
        ]);

        if ($req->type_login == 'owner') {
            $cek_login = DB::table('pos_user')
            ->where('remember_token', $req->token)
            ->where('no_hp', $req->username)
            ->count();
        } else {
            $cek_login = DB::table('rb_konsumen')
            ->where('remember_token', $req->token)
            ->where('no_hp', $req->username)
            ->count();
        }
        

        if ($cek_login == 1) {
            http_response_code(200);
        } else {
            http_response_code(400);
        }
        
    }

    public function loginOtp(Request $req){
        
        if ($req->type_login == 'owner') {
            $cek_user = DB::table('pos_user')
            ->where('no_hp', $req->username)
            ->get();
        } else {
            $cek_user = DB::table('rb_konsumen')
            ->where('no_hp', $req->username)
            ->get();
        }

        if (count($cek_user) == 1) {
            $otp = $token = date('Hi').rand(10,99);
            $stop_date = date('Y-m-d H:i:s');
            $update['otp'] = $otp;
            $update['otp_expired'] = date('Y-m-d H:i:s', strtotime($stop_date . ' +5 minute'));
            if ($req->type_login == 'owner'){
                DB::table('pos_user')->where('no_hp', $req->username)->update($update);
            } else {
                DB::table('rb_konsumen')->where('no_hp', $req->username)->update($update);
            }
            notif_wa_otp($req->username, $otp);
            exit(json_encode(['Message' => $otp]));
        } else if (count($cek_user) < 1) {
            http_response_code(404);
            exit(json_encode(['Message' => 'Nomor HP Tidak Terdaftar']));
        } else {
            http_response_code(400);
            exit(json_encode(['Message' => 'Hubungi Administrator Untuk Kesalahan Ini']));
        }

        
    }


    public function verifOtp(Request $req){
        if ($req->username == '' || $req->otp == '') {
            http_response_code(404);
            exit(json_encode(['Message' => 'Nomor HP atau OTP Tidak Ada']));
        }
        

        if ($req->type_login == 'owner') {
            $cek_user = DB::table('pos_user')
            ->where('no_hp', $req->username)
            ->where('otp', $req->otp)
            ->first();
        } else {
            $cek_user = DB::table('rb_konsumen')
            ->select('nama_lengkap as name', 'email', 'no_hp', DB::raw("'admin' as level"), 'otp_expired')
            ->where('no_hp', $req->username)
            ->where('otp', $req->otp)
            ->first();
        }

        if ($cek_user) {

            if(date('Y-m-d H:i:s') > $cek_user->otp_expired){
                http_response_code(400);
                exit(json_encode(['Message' => 'Kode OTP Kadaluarsa']));    
            }
            $token = Hash::make(date('YmdHi').rand(0000,9999));
            $dtUpdate['otp_expired'] = (string)date('Y-m-d H:i:s');
            $dtUpdate['remember_token'] = (string)$token;
            if ($req->type_login == 'owner'){
                DB::table('pos_user')->where('no_hp', $req->username)->update($dtUpdate);
            } else {
                DB::table('rb_konsumen')->where('no_hp', $req->username)->update($dtUpdate);
            }
            http_response_code(200);
            $dt_result['token'] = $token;
            $dt_result['nama_lengkap'] = $cek_user->name;
            $dt_result['email'] = $cek_user->email;
            $dt_result['no_hp'] = $cek_user->no_hp;
            $dt_result['level'] = $cek_user->level;
            exit(json_encode($dt_result));

        } else {
            http_response_code(404);
            exit(json_encode(['Message' => 'Kode OTP Salah']));
        }

        
    }





    // KURIR START
    public function kurirLoginOtp(Request $req){
        
        $cek_user = DB::table('rb_konsumen as a')
        ->leftJoin('rb_sopir as b', 'b.id_konsumen', 'a.id_konsumen')
        ->where('a.no_hp', $req->username)
        ->first();
        
        header('Content-Type: application/json');
        if ($cek_user) {
            if ($cek_user->id_sopir == null) {
                http_response_code(404);
                exit(json_encode(['Message' => 'Belum Terdaftar Sebagai Kurir']));
            }
            $otp = $token = date('Hi').rand(10,99);
            $stop_date = date('Y-m-d H:i:s');
            $update['otp'] = $otp;
            $update['otp_expired'] = date('Y-m-d H:i:s', strtotime($stop_date . ' +5 minute'));
            DB::table('rb_konsumen')->where('no_hp', $req->username)->update($update);
            notif_wa_otp($req->username, $otp);
            
            http_response_code(200);
            exit(json_encode(['Message' => $otp]));
        } else {
            http_response_code(404);
            exit(json_encode(['Message' => 'Nomor HP Tidak Terdaftar']));
        }

        
    }


    public function kurirVerifOtp(Request $req){
        if ($req->username == '' || $req->otp == '') {
            http_response_code(404);
            exit(json_encode(['Message' => 'Nomor HP atau OTP Tidak Ada']));
        }
        

        $cek_user = DB::table('rb_konsumen as a')
        ->select('a.*','b.agen', 'b.koordinator_kota', 'b.koordinator_kecamatan', 'b.foto_diri')
        ->leftJoin('rb_sopir as b', 'b.id_konsumen', 'a.id_konsumen')
        ->where('a.no_hp', $req->username)
        ->where('otp', $req->otp)
        ->first();
        header('Content-Type: application/json');
        if ($cek_user) {

            if(date('Y-m-d H:i:s') > $cek_user->otp_expired){
                http_response_code(400);
                exit(json_encode(['Message' => 'Kode OTP Kadaluarsa']));    
            }
            $token = Hash::make(date('YmdHi').rand(0000,9999));
            $dtUpdate['otp_expired'] = (string)date('Y-m-d H:i:s');
            $dtUpdate['remember_token'] = (string)$token;
            
            DB::table('rb_konsumen')->where('no_hp', $req->username)->update($dtUpdate);

            if (!empty($cek_user->foto_diri)) {
                $cek_user->foto_diri = $this->urlForStoragePath($cek_user->foto_diri);
            }

            http_response_code(200);
            $dt_result['token'] = $token;
            $dt_result['id_konsumen'] = $cek_user->id_konsumen;
            $dt_result['nama_lengkap'] = $cek_user->nama_lengkap;
            $dt_result['email'] = $cek_user->email;
            $dt_result['no_hp'] = $cek_user->no_hp;
            $dt_result['agen'] = $cek_user->agen;
            $dt_result['koordinator_kota'] = $cek_user->koordinator_kota;
            $dt_result['koordinator_kecamatan'] = $cek_user->koordinator_kecamatan;
            $dt_result['foto_diri'] = $cek_user->foto_diri;
            $dt_result['foto'] = $cek_user->foto_diri;
            $dt_result['level'] = 'kurir';
            exit(json_encode($dt_result));

        } else {
            http_response_code(404);
            exit(json_encode(['Message' => 'Kode OTP Salah']));
        }

        
    }

    // KURIR END

    public function kirimOtp(Request $req){
        
        if($req->otp){
            $otp = $req->otp;
        } else {
            $newTime = date('Y-m-d H:i:s', strtotime('+1 minutes', time()));
            $otp = rand(000000, 999999);
            $dt_update['otp_reseller'] = $otp;
            $dt_update['otp_expired'] = $newTime;
            DB::table('rb_konsumen')->where('no_hp', $req->no_hp)->update($dt_update);
        }
        return notif_wa_otp($req->no_hp, $otp, '', $req->sts);
        
    }
    
    
    public function cekOTP(Request $req){
        if ($req->no_hp == '' || $req->otp == '') {
            http_response_code(404);
            exit(json_encode(['Message' => 'Nomor HP atau OTP Tidak Ada']));
        }
        

        $cek_user = DB::table('rb_konsumen as a')
        ->select('a.*')
        ->where('a.no_hp', $req->no_hp)
        ->where('a.otp_reseller', $req->otp)
        ->first();

        if ($cek_user) {

            if(date('Y-m-d H:i:s') > $cek_user->otp_expired){
                http_response_code(400);
                exit(json_encode(['Message' => 'Kode OTP Kadaluarsa']));    
            }
            $token = Hash::make(date('YmdHi').rand(0000,9999));
            $dtUpdate['otp_expired'] = (string)date('Y-m-d H:i:s');
            $dtUpdate['remember_token'] = (string)$token;
            
            DB::table('rb_konsumen')->where('no_hp', $req->no_hp)->update($dtUpdate);

            http_response_code(200);
            $dt_result['token'] = $token;
            $dt_result['nama_lengkap'] = $cek_user->nama_lengkap;
            $dt_result['email'] = $cek_user->email;
            $dt_result['no_hp'] = $cek_user->no_hp;
            exit(json_encode($dt_result));

        } else {
            http_response_code(404);
            exit(json_encode(['Message' => 'Kode OTP Salah']));
        }

        
    }


    public function registrasiRebahanDapatCuan(Request $req){
        if ($req->nama == '') {
            http_response_code(404);
            exit(json_encode(['Message' => 'Nama Harus Diisi']));
        }
        
        if ($req->username == '') {
            http_response_code(404);
            exit(json_encode(['Message' => 'Username Harus Diisi']));
        } else if(preg_match('/\s/', $req->username)){
            http_response_code(404);
            exit(json_encode(['Message' => 'Username Tidak Boleh Ada Spasi']));
        } else {
            $cekHp = DB::table('rb_konsumen')->where('username', $req->username)->first();
            if($cekHp){
                http_response_code(404);
                exit(json_encode(['Message' => 'Username Sudah Terdaftar']));
            }
        }
        
        if ($req->no_hp == '') {
            http_response_code(404);
            exit(json_encode(['Message' => 'Nomor HP Harus Diisi']));
        } else if(strlen($req->no_hp) < 10){
            http_response_code(404);
            exit(json_encode(['Message' => 'Nomor HP Minimal 10 Digit']));
        } else if(strlen($req->no_hp) > 13){
            http_response_code(404);
            exit(json_encode(['Message' => 'Nomor HP Maksimal 13 Digit']));
        } else if(!ctype_digit($req->no_hp)){
            http_response_code(404);
            exit(json_encode(['Message' => 'Nomor HP Harus Angka Semua']));
        } else {
            $cekHp = DB::table('rb_konsumen')->where('no_hp', $req->no_hp)->first();
            if($cekHp){
                http_response_code(404);
                exit(json_encode(['Message' => 'No HP Sudah Terdaftar']));
            }
        }
        
        if ($req->email == '') {
            http_response_code(404);
            exit(json_encode(['Message' => 'Email Harus Diisi']));
        } else if (!filter_var($req->email, FILTER_VALIDATE_EMAIL)){
            http_response_code(404);
            exit(json_encode(['Message' => 'Email Tidak Valid']));
        } else {
            $cekHp = DB::table('rb_konsumen')->where('email', $req->email)->first();
            if($cekHp){
                http_response_code(404);
                exit(json_encode(['Message' => 'Email Sudah Terdaftar']));
            }
        }
        
        if(!ctype_digit($req->provinsi)){
            http_response_code(404);
            exit(json_encode(['Message' => 'Provinsi Harus Angka']));
        } else {
            $cekProv = DB::table('tb_ro_provinces')->where('province_id', $req->provinsi)->first();
            if(!$cekProv){
                http_response_code(404);
                exit(json_encode(['Message' => 'Provinsi Tidak Terdaftar']));
            }
        }
        
        if(!ctype_digit($req->kota)){
            http_response_code(404);
            exit(json_encode(['Message' => 'Kota Harus Angka']));
        } else {
            $cekCity = DB::table('tb_ro_cities')->where('city_id', $req->kota)->where('province_id', $req->provinsi)->first();
            if(!$cekCity){
                http_response_code(404);
                exit(json_encode(['Message' => 'Kota Tidak Terdaftar']));
            }
        }
        
        if(!ctype_digit($req->kecamatan)){
            http_response_code(404);
            exit(json_encode(['Message' => 'Kecamatan Harus Angka']));
        } else {
            $cekKec = DB::table('tb_ro_subdistricts')->where('subdistrict_id', $req->kecamatan)->where('city_id', $req->kota)->first();
            if(!$cekKec){
                http_response_code(404);
                exit(json_encode(['Message' => 'Kecamatan Tidak Terdaftar']));
            }
        }
        
        
        if ($req->kode_reff) {
            $cekReff = DB::table('rb_konsumen')->where('username', $req->kode_reff)->first();
            if(!$cekReff){
                http_response_code(404);
                exit(json_encode(['Message' => 'Kode Reff Tidak Terdaftar']));
            }
            $kodeReff = $cekReff->id_konsumen;
        } else {
            $kodeReff = '';
        }
        
        
        if ($req->paket_member == '') {
            http_response_code(404);
            exit(json_encode(['Message' => 'Paket Member Harus Dipilih']));
        }
        
        
        DB::beginTransaction();

        try {
            $dtInsert['nama_lengkap'] = $req->nama;
            $dtInsert['username'] = $req->username;
            $dtInsert['no_hp'] = $req->no_hp;
            $dtInsert['email'] = $req->email;
            $dtInsert['alamat_lengkap'] = str_replace("\'", "", $req->alamat);
            $dtInsert['provinsi_id'] = $req->provinsi;
            $dtInsert['kota_id'] = $req->kota;
            $dtInsert['kecamatan_id'] = $req->kecamatan;
            $dtInsert['password'] = '';
            $dtInsert['referral_id'] = $kodeReff;
            $dtInsert['tempat_lahir'] = '';
            $dtInsert['foto'] = '';
            $dtInsert['subdomain'] = time().rand(11,99);
            $dtInsert['tanggal_daftar'] = date('Y-m-d');
            $dtInsert['source'] = 'rebahandapatcuan';
            
            $cekInsert = DB::table('rb_konsumen')->insertGetId($dtInsert);
            
            if($cekInsert > 0){
                $kode_transaksi = time().rand(111,999);
                $getPaket = DB::table('rebahandapatcuan_paket')->where('id', $req->paket_member)->first();
                
                $effectiveDate = date('Y-m-d');
                
                if($getPaket->periode == 'year'){
                    $expired = date('Y-m-d', strtotime("+12 months", strtotime($effectiveDate)));
                    $dtpaket['status'] = 'N';
                    $dtpaket['paket'] = '0';
                } else if($getPaket->periode == 'abad'){
                    $expired = date('Y-m-d', strtotime("+100 years", strtotime($effectiveDate)));
                    $dtpaket['status'] = 'Y';
                    $dtpaket['paket'] = 'on';
                } else {
                    $expired = date('Y-m-d', strtotime("+1 months", strtotime($effectiveDate)));
                    $dtpaket['status'] = 'N';
                    $dtpaket['paket'] = '0';
                }
                
                $total_harga = $getPaket->harga;
                
                $addon_paket = array();
                if($req->addon_paket){
                    foreach($req->addon_paket as $pkt){
                        $array = explode("||", $pkt);
                        $dt['nama_addon'] = $array[0];
                        $dt['harga_addon'] = (int)$array[1];
                        $total_harga = $total_harga + $array[1];
                        array_push($addon_paket, $dt);
                    }
                }
                
                $dtpaket['id_konsumen'] = $cekInsert;
                $dtpaket['id_paket'] = $getPaket->id;
                $dtpaket['tagihan'] = $total_harga;
                $dtpaket['expire_date'] = $expired;
                $dtpaket['waktu_paket'] = date('Y-m-d H:i:s');
                $dtpaket['kode_transaksi'] = $kode_transaksi;
                $dtpaket['addon_paket'] = json_encode($addon_paket);
                $idPaketMemberBeli = DB::table('rebahandapatcuan_member_paket')->insertGetId($dtpaket);
                
                if($total_harga > 0){
                    $pay = $this->flip_subscribe($req->email, $kode_transaksi);
                    if(isset($pay['status'])){
                        
                        $dtUpdate['payment_link'] = $pay['link_url'];
            			$dtUpdate['id_payment'] = $pay['link_id'];
            			
            			DB::table('rebahandapatcuan_member_paket')->where('kode_transaksi',$kode_transaksi)->update($dtUpdate);
            			
            		    $result['Message'] = 'Berhasil Melakukan Pendaftaran';
            		    $result['payment_link'] = $pay['link_url'];
            		    
            		    DB::commit();
            		    http_response_code(200);
            		    exit(json_encode($result));
            		} else {
            		    DB::commit();
            		    http_response_code(200);
            		    
            		    $result['Message'] = 'Berhasil Melakukan Pendaftaran';
            		    $result['payment_error'] = json_encode($pay);
            		    
                        exit(json_encode(['Message' => $result]));
            		}
                } else {
                    DB::commit();
                    http_response_code(200);
                    exit(json_encode(['Message' => 'Berhasil Melakukan Pendaftaran']));
                }
                
            }
        
            
        } catch (\Exception $e) {
            
            DB::rollback();
            
            http_response_code(404);
            exit(json_encode(['Message' => $e->getMessage()]));
        }
        
        
    }
    
    
    public function getProfile(Request $req){
        $id = base64_decode($req->id_profile);
        
        $getProfile = DB::table('rb_konsumen as a')
                    ->select('a.username', 'a.nama_lengkap', 'a.email', 'a.no_hp', 'a.tanggal_daftar', 'a.subdomain', 'e.nama_lengkap as nama_refferal', 'b.province_name as nama_provinsi', 'c.city_name as nama_kota', 'd.subdistrict_name as nama_kecamatan')
                    ->leftJoin('tb_ro_provinces as b', 'b.province_id', 'a.provinsi_id')
                    ->leftJoin('tb_ro_cities as c', 'c.city_id', 'a.kota_id')
                    ->leftJoin('tb_ro_subdistricts as d', 'd.subdistrict_id', 'a.kecamatan_id')
                    ->leftJoin('rb_konsumen as e', 'e.id_konsumen', 'a.referral_id')
                    ->where('a.id_konsumen', $id)
                    ->first();
        
        if(!$getProfile){
            http_response_code(404);
            exit(json_encode(['Message' => 'User Tidak Ditemukan']));
        }
        
        $getPaketRebahan = DB::table('rebahandapatcuan_member_paket as a')
                            ->select('a.status','a.tagihan as harga_paket', 'a.expire_date as berlaku_paket','a.waktu_paket as tanggal_beli','a.addon_paket','b.nama_paket','b.keterangan_paket')
                            ->where('a.id_konsumen',$id)
                            ->leftJoin('rebahandapatcuan_paket as b', 'b.id', 'a.id_paket')
                            ->orderBy('a.id_member_paket','desc')
                            ->first();
        
        $result['data_diri'] = $getProfile;
        $result['membership'] = $getPaketRebahan;
        
        http_response_code(200);
        exit(json_encode($result));
    }
    
    
    public function updateProfile(Request $req){
        $id = base64_decode($req->id_profile);
        
        if ($req->nama == '') {
            http_response_code(404);
            exit(json_encode(['Message' => 'Nama Harus Diisi']));
        }
        
        if ($req->username == '') {
            http_response_code(404);
            exit(json_encode(['Message' => 'Username Harus Diisi']));
        } else if(preg_match('/\s/', $req->username)){
            http_response_code(404);
            exit(json_encode(['Message' => 'Username Tidak Boleh Ada Spasi']));
        } else {
            $cekHp = DB::table('rb_konsumen')->where('username', $req->username)->whereNot('id_konsumen',$id)->first();
            if($cekHp){
                http_response_code(404);
                exit(json_encode(['Message' => 'Username Sudah Terdaftar']));
            }
        }
        
        if ($req->no_hp == '') {
            http_response_code(404);
            exit(json_encode(['Message' => 'Nomor HP Harus Diisi']));
        } else if(strlen($req->no_hp) < 10){
            http_response_code(404);
            exit(json_encode(['Message' => 'Nomor HP Minimal 10 Digit']));
        } else if(strlen($req->no_hp) > 13){
            http_response_code(404);
            exit(json_encode(['Message' => 'Nomor HP Maksimal 13 Digit']));
        } else if(!ctype_digit($req->no_hp)){
            http_response_code(404);
            exit(json_encode(['Message' => 'Nomor HP Harus Angka Semua']));
        } else {
            $cekHp = DB::table('rb_konsumen')->where('no_hp', $req->no_hp)->whereNot('id_konsumen',$id)->first();
            if($cekHp){
                http_response_code(404);
                exit(json_encode(['Message' => 'No HP Sudah Terdaftar']));
            }
        }
        
        if ($req->email == '') {
            http_response_code(404);
            exit(json_encode(['Message' => 'Email Harus Diisi']));
        } else if (!filter_var($req->email, FILTER_VALIDATE_EMAIL)){
            http_response_code(404);
            exit(json_encode(['Message' => 'Email Tidak Valid']));
        } else {
            $cekHp = DB::table('rb_konsumen')->where('email', $req->email)->whereNot('id_konsumen',$id)->first();
            if($cekHp){
                http_response_code(404);
                exit(json_encode(['Message' => 'Email Sudah Terdaftar']));
            }
        }
        
        if(!ctype_digit($req->provinsi)){
            http_response_code(404);
            exit(json_encode(['Message' => 'Provinsi Harus Angka']));
        } else {
            $cekProv = DB::table('tb_ro_provinces')->where('province_id', $req->provinsi)->first();
            if(!$cekProv){
                http_response_code(404);
                exit(json_encode(['Message' => 'Provinsi Tidak Terdaftar']));
            }
        }
        
        if(!ctype_digit($req->kota)){
            http_response_code(404);
            exit(json_encode(['Message' => 'Kota Harus Angka']));
        } else {
            $cekCity = DB::table('tb_ro_cities')->where('city_id', $req->kota)->where('province_id', $req->provinsi)->first();
            if(!$cekCity){
                http_response_code(404);
                exit(json_encode(['Message' => 'Kota Tidak Terdaftar']));
            }
        }
        
        if(!ctype_digit($req->kecamatan)){
            http_response_code(404);
            exit(json_encode(['Message' => 'Kecamatan Harus Angka']));
        } else {
            $cekKec = DB::table('tb_ro_subdistricts')->where('subdistrict_id', $req->kecamatan)->where('city_id', $req->kota)->first();
            if(!$cekKec){
                http_response_code(404);
                exit(json_encode(['Message' => 'Kecamatan Tidak Terdaftar']));
            }
        }
        
        
        DB::beginTransaction();

        try {
            $dtInsert['nama_lengkap'] = $req->nama;
            $dtInsert['username'] = $req->username;
            $dtInsert['no_hp'] = $req->no_hp;
            $dtInsert['email'] = $req->email;
            $dtInsert['alamat_lengkap'] = str_replace("\'", "", $req->alamat);
            $dtInsert['provinsi_id'] = $req->provinsi;
            $dtInsert['kota_id'] = $req->kota;
            $dtInsert['kecamatan_id'] = $req->kecamatan;
            
            $cekInsert = DB::table('rb_konsumen')->where('id_konsumen',$id)->update($dtInsert);
            
            if($cekInsert){
                
                DB::commit();
                http_response_code(200);
                exit(json_encode(['Message' => 'Berhasil Melakukan Update Profile']));

            }
        
            
        } catch (\Exception $e) {
            
            DB::rollback();
            
            http_response_code(404);
            exit(json_encode(['Message' => $e->getMessage()]));
        }
        
        
    }
    
}
