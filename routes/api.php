<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\V1\KasirController;
use App\Http\Controllers\V1\KurirController;
use App\Http\Controllers\V1\WalletController;
use App\Http\Controllers\Controller;

use App\Http\Controllers\SatuAppsController;

use App\Http\Controllers\V1\FlipController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/testNotif', [Controller::class, 'testNotif']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/test', [LoginController::class, 'loginTest']);

Route::post('/kirim_otp', [LoginController::class, 'kirimOtp']);

Route::post('/cek_otp', [LoginController::class, 'cekOTP']);

Route::post('/login', [LoginController::class, 'loginOtp']);

Route::post('/verifikasi_otp', [LoginController::class, 'verifOtp']);

Route::post('/cek_login', [LoginController::class, 'cekLogin']);

Route::prefix('v1')->group(function () {
    // Route::post('/test', 'LoginController@loginTest');
    Route::post('/list_cabang_user', [KasirController::class, 'ListCabangUser']);
    Route::post('/list_cabang', [KasirController::class, 'ListCabang']);
    Route::post('/save_cabang', [KasirController::class, 'SaveCabang']);
    Route::post('/detail_cabang', [KasirController::class, 'DetailCabang']);
    Route::post('/hapus_cabang', [KasirController::class, 'DeleteCabang']);

    Route::post('/list_level', [KasirController::class, 'LevelUser']);

    Route::post('/list_user', [KasirController::class, 'ListUser']);
    Route::post('/save_user', [KasirController::class, 'SaveUser']);
    Route::post('/detail_user', [KasirController::class, 'DetailUser']);
    Route::post('/hapus_user', [KasirController::class, 'DeleteUser']);


    Route::post('/list_barang', [KasirController::class, 'ListBarang']);

    Route::post('/cek_kupon', [KasirController::class, 'GetKupon']);



    Route::post('/get_saldo', [WalletController::class, 'GetBalance']);
    Route::post('/upload_deposit', [WalletController::class, 'uploadBuktiDeposit']);
    
    Route::post('/cek_komisi_rebahan/{id_penjualan}', [Controller::class, 'cekKomisiRebahanReal']);
});


Route::prefix('kurir')->group(function () {
    Route::post('/login', [LoginController::class, 'kurirLoginOtp']);
    Route::post('/verifikasi_otp', [LoginController::class, 'kurirVerifOtp']);

    Route::prefix('v1')->group(function () {
        Route::get('/test', [KurirController::class, 'test']);
        Route::get('/hitungJarak/{titik_jemput}/{titik_tujuan}/{service}', [KurirController::class, 'getPrice']);
        Route::post('/orderKurir', [KurirController::class, 'orderKurir']);
    });

    
});


Route::prefix('satuapps')->group(function () {
    Route::post('/login', [SatuAppsController::class, 'loginOtp']);
    Route::post('/verifikasi_otp', [SatuAppsController::class, 'verifOtp']);
    Route::post('/cek_login', [SatuAppsController::class, 'cekLogin']);
    Route::post('/send_notif', [KurirController::class, 'notifUpdateOrder']);
});


Route::prefix('payment')->group(function () {
    Route::prefix('v1')->group(function () {
        Route::post('/get_bank_account', [FlipController::class, 'getBankAccount']);
        Route::post('/flip_transfer', [FlipController::class, 'flipTransfer']);
        Route::post('/cek_transfer', [FlipController::class, 'cekTransfer']);
    });

    
});


Route::prefix('rebahandapatcuan')->group(function () {
    Route::post('/login', [LoginController::class, 'kirimOtp']);
    Route::post('/verifikasi_otp', [LoginController::class, 'cekOTP']);
    Route::post('/registrasi', [LoginController::class, 'registrasiRebahanDapatCuan']);
    Route::get('/profile', [LoginController::class, 'getProfile']);
    Route::post('/update_profile', [LoginController::class, 'updateProfile']);
});


Route::get('/get_paket_konsumen', [Controller::class, 'getPaket']);

Route::post('/get_username', [Controller::class, 'getUsername']);

Route::post('/flip_callback', [Controller::class, 'flipCallback']);



Route::post('/rebahan_dapat_cuan_visitor', [Controller::class, 'rebahanVisitorCounter']);
Route::post('/rebahan_dapat_cuan_cta_click', [Controller::class, 'rebahanVisitorCounterCTA']);
Route::post('/rebahan_dapat_cuan_notif_wa', [Controller::class, 'rebahanNotifWa']);




