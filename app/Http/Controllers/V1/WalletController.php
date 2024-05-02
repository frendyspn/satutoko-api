<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use DB;

class WalletController extends Controller
{
    public function __construct(Request $req){
        // cekHeaderApi(getallheaders()['Authorization']);

        if ($req->token == '') {
            http_response_code(404);
            exit(json_encode(['Message' => 'Token Tidak Berlaku']));
        }
        cekTokenLogin($req->token);
    }


    public function GetBalance(Request $req)
    {
        $dtKonsumen = cekTokenLoginKonsumen($req->token);   
        
        $data['history'] = DB::table('rb_pendapatan_kurir')->whereIn('status',['sukses','proses'])->where('id_konsumen', $dtKonsumen->id_konsumen)->orderBy('waktu_pendapatan', 'desc')->get();

        $debit = DB::table('rb_pendapatan_kurir')->select(DB::raw('IFNULL(sum(nominal), 0) as saldo'))->where('status','sukses')->where('id_konsumen', $dtKonsumen->id_konsumen)->where('transaksi', 'debit')->first();
        $kredit = DB::table('rb_pendapatan_kurir')->select(DB::raw('IFNULL(sum(nominal), 0) as saldo'))->where('status','sukses')->where('id_konsumen', $dtKonsumen->id_konsumen)->where('transaksi', 'kredit')->first();

        $data['saldo'] = $debit->saldo-$kredit->saldo;


        $debitKurir = DB::table('rb_pendapatan_kurir')->select(DB::raw('IFNULL(sum(nominal), 0) as saldo'))->where('status','sukses')->where('id_konsumen', $dtKonsumen->id_konsumen)->where('transaksi', 'debit')->where('akun', 'sopir')->first();
        $kreditKurir = DB::table('rb_pendapatan_kurir')->select(DB::raw('IFNULL(sum(nominal), 0) as saldo'))->where('status','sukses')->where('id_konsumen', $dtKonsumen->id_konsumen)->where('transaksi', 'kredit')->where('akun', 'sopir')->first();

        $data['pendapatan'] = $debitKurir->saldo-$kreditKurir->saldo;

        $debitKomisi = DB::table('rb_pendapatan_kurir')->select(DB::raw('IFNULL(sum(nominal), 0) as saldo'))->where('status','sukses')->where('id_konsumen', $dtKonsumen->id_konsumen)->where('transaksi', 'debit')->whereNotIn('akun', ['sopir','deposit','transfer','withdraw'])->first();
        $kreditKomisi = DB::table('rb_pendapatan_kurir')->select(DB::raw('IFNULL(sum(nominal), 0) as saldo'))->where('status','sukses')->where('id_konsumen', $dtKonsumen->id_konsumen)->where('transaksi', 'kredit')->whereNotIn('akun', ['sopir','deposit','transfer','withdraw'])->first();

        $data['komisi'] = $debitKomisi->saldo-$kreditKomisi->saldo;

        $data['kode_unik_deposit'] = true;

        $data['rekening'] = DB::table('rb_rekening')->where('aktif','1')->get();

        return $data;
    }


    public function uploadBuktiDeposit(Request $req)
    {
        $updatenya['keterangan'] = $req->image;
        $updatenya['status'] = 'Proses';
        return DB::table('rb_pendapatan_kurir')->where('id_pendapatan',$req->id)->update($updatenya);
    }



}