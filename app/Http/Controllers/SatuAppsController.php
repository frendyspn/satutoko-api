<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Hash;

use Illuminate\Http\Request;
use DB;

class SatuAppsController extends Controller
{
    public function __construct(){
        cekHeaderApiSatuApps(getallheaders()['Authorization']);
    }


    public function loginOtp(Request $req){
        
        $cek_user = DB::table('rb_konsumen')
        ->where('no_hp', $req->username)
        ->get();

        if (count($cek_user) == 1) {
            $otp = $token = date('Hi').rand(10,99);
            $stop_date = date('Y-m-d H:i:s');
            $update['otp'] = $otp;
            $update['otp_expired'] = date('Y-m-d H:i:s', strtotime($stop_date . ' +5 minute'));
            
            DB::table('rb_konsumen')->where('no_hp', $req->username)->update($update);
            
            notif_wa_otp($req->username, $otp);
            
            http_response_code(200);
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
        

        $cek_user = DB::table('rb_konsumen')
        ->select('nama_lengkap as name', 'email', 'no_hp', DB::raw("'user' as level"), 'otp_expired')
        ->where('no_hp', $req->username)
        ->where('otp', $req->otp)
        ->first();
        

        if ($cek_user) {

            if(date('Y-m-d H:i:s') > $cek_user->otp_expired){
                http_response_code(400);
                exit(json_encode(['Message' => 'Kode OTP Kadaluarsa']));    
            }
            $token = Hash::make(date('YmdHi').rand(0000,9999));
            $dtUpdate['otp_expired'] = (string)date('Y-m-d H:i:s');
            $dtUpdate['remember_token'] = (string)$token;
            
            DB::table('rb_konsumen')->where('no_hp', $req->username)->update($dtUpdate);
            
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


    public function cekLogin(Request $req){
        $validated = $req->validate([
            'username' => 'required',
            'token' => 'required',
        ]);

        $cek_login = DB::table('rb_konsumen')
        ->where('remember_token', $req->token)
        ->where('no_hp', $req->username)
        ->count();        
        

        if ($cek_login == 1) {
            http_response_code(200);
        } else {
            http_response_code(400);
        }
        
    }


    


}