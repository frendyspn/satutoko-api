<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\V1\KasirController;
use App\Http\Controllers\V1\KurirController;
use App\Http\Controllers\V1\WalletController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\V1\KurirOrderController;


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
    Route::post('/get_transaction_history', [WalletController::class, 'GetTransactionHistory']);
    Route::get('/get_top_up_methods', [WalletController::class, 'getTopUpMethods']);
    Route::post('/create_top_up_request', [WalletController::class, 'createTopUpRequest']);
    Route::post('/upload_top_up_proof', [WalletController::class, 'uploadTopUpProof']);

    Route::post('/cek_komisi_rebahan/{id_penjualan}', [Controller::class, 'cekKomisiRebahanReal']);

    Route::get('/get_bank_list', [WalletController::class, 'getBankList']);

    Route::post('/create_withdraw_request', [WalletController::class, 'createWithdrawRequest']);
});


Route::prefix('kurir')->group(function () {
    Route::post('/login', [LoginController::class, 'kurirLoginOtp']);
    Route::post('/verifikasi_otp', [LoginController::class, 'kurirVerifOtp']);

    Route::prefix('register')->group(function () {
        Route::post('/cek-no-hp', [KurirController::class, 'cekNoHp']);
        Route::get('/get-provinsi', [KurirController::class, 'getProvinsi']);
        Route::post('/get-kota', [KurirController::class, 'getKota']);
        Route::post('/get-kecamatan', [KurirController::class, 'getKecamatan']);
        Route::post('/send-otp', [KurirController::class, 'kirimOtpRegister']);
        Route::post('/verify-otp', [KurirController::class, 'submitDataRegister']);
        Route::post('/cek-status-sopir', [KurirController::class, 'cekStatusSopir']);
        Route::post('/update-kelengkapan-data', [KurirController::class, 'updateKelengkapanData']);
    });

    Route::prefix('v1')->group(function () {
        Route::get('/test', [KurirController::class, 'test']);
        Route::get('/hitungJarak/{titik_jemput}/{titik_tujuan}/{service}', [KurirController::class, 'getPrice']);
        Route::post('/orderKurir', [KurirController::class, 'orderKurir']);
        Route::post('/getBalance', [KurirController::class, 'getBalance']);
        Route::post('/orders/daily', [KurirController::class, 'getOrdersDaily']);
        Route::post('/orders/monthly', [KurirController::class, 'getOrdersMonthly']);

        Route::post('/orders/jenis/daily', [KurirController::class, 'getJenisOrderDaily']);
        Route::post('/orders/jenis/monthly', [KurirController::class, 'getJenisOrderMonthly']);

        Route::get('/jenis-layanan', [KurirController::class, 'layananKurir']);
        Route::post('/transaksi-manual', [KurirController::class, 'listTransaksi']);
        Route::post('/transaksi-manual-konsumen', [KurirController::class, 'listTransaksiKonsumen']);

        Route::post('/list-agen', [KurirController::class, 'getAgens']);
        Route::post('/list-pelanggan', [KurirController::class, 'getPelanggan']);

        Route::post('/save-transaksi-manual', [KurirController::class, 'saveTransaksi']);
        Route::post('/approve-transaksi-manual', [KurirController::class, 'approveTransaksi']);

        Route::post('/update-konsumen', [KurirController::class, 'updateKonsumen']);
        Route::post('/update-konsumen-simple', [KurirController::class, 'updateKonsumenSimple']);
        Route::post('/delete-konsumen', [KurirController::class, 'deleteKonsumen']);

        Route::post('/update-profile-foto', [KurirController::class, 'updateProfileFoto']);

        Route::post('/add-pelanggan', [KurirController::class, 'addDownline']);
        Route::post('/get-pelanggan', [KurirController::class, 'getDownline']);

        Route::post('/check_user_by_phone', [KurirController::class, 'checkUserByPhone']);
        Route::post('/create_transfer_request', [KurirController::class, 'createTransferRequest']);

        // Get available orders for kurir
        Route::get('/kurir-orders/available', [KurirOrderController::class, 'getAvailableOrders']);
        
        // Accept order
        Route::post('/kurir-orders/{orderId}/accept', [KurirOrderController::class, 'acceptOrder']);
        
        // Get kurir's orders
        Route::get('/kurir-orders/my-orders', [KurirOrderController::class, 'getMyOrders']);
        
        // Update order status
        Route::patch('/kurir-orders/{orderId}/status', [KurirOrderController::class, 'updateOrderStatus']);

        Route::post('/kurir-orders', [KurirOrderController::class, 'createOrder']);

        Route::post('/live-order', [KurirController::class, 'listLiveOrder']);
        Route::post('/live-order/create', [KurirController::class, 'saveLiveOrder']);
        Route::post('/live-order/ambil', [KurirController::class, 'ambilLiveOrder']);
        Route::post('/live-order/detail-penjualan', [KurirController::class, 'getPenjualan']);
        Route::post('/live-order/update', [KurirController::class, 'updateLiveOrder']);
        Route::post('/live-order/pickup', [KurirController::class, 'pickupOrder']);
        Route::post('/live-order/complete', [KurirController::class, 'completeOrder']);
        Route::post('/live-order/detail-order', [KurirController::class, 'getOrderDetail']);
        Route::post('/komisi', [KurirController::class, 'getKomisi']);
        Route::post('/admin-kurir', [KurirController::class, 'getPotonganAdmin']);
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




