<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use DB;

class WalletController extends Controller
{
    public function __construct(Request $req){
        // cekHeaderApi(getallheaders()['Authorization']);

        if ($req->token == '') {
            // http_response_code(404);
            // exit(json_encode(['Message' => 'Token Tidak Berlaku']));
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


    public function GetTransactionHistory(Request $req)
    {

        // default date range: current month
        $startDate = $req->start_date ?? date('Y-m-01');
        $endDate = $req->end_date ?? date('Y-m-t');

        $query1 = DB::table('rb_wallet_users')
            ->select('rb_wallet_users.id', 'rb_wallet_users.trx_type', 'rb_wallet_users.amount', 'rb_wallet_users.created_at', 'rb_wallet_users.note', 'rb_konsumen.no_hp', DB::raw("'success' as status"), DB::raw("'' as bukti_topup"))
            ->join('rb_konsumen', 'rb_wallet_users.id_konsumen', '=', 'rb_konsumen.id_konsumen');


        if (!empty($req->no_hp)) {
            $query1->where('rb_konsumen.no_hp', $req->no_hp);
        }

        $query1->whereBetween(DB::raw('DATE(rb_wallet_users.created_at)'), [$startDate, $endDate]);

        $query2 = DB::table('rb_wallet_requests')
            ->select('rb_wallet_requests.id', 'rb_wallet_requests.req_type as trx_type', 'rb_wallet_requests.amount', 'rb_wallet_requests.created_at', 'rb_wallet_requests.req_type as note', 'rb_konsumen.no_hp', 'rb_wallet_requests.status', 'rb_wallet_requests.proof_image as bukti_topup')
            ->join('rb_konsumen', 'rb_wallet_requests.id_konsumen', '=', 'rb_konsumen.id_konsumen')
            ->where('rb_wallet_requests.status', 'pending');

    
        if (!empty($req->no_hp)) {
            $query2->where('rb_konsumen.no_hp', $req->no_hp);
        }

        $query2->whereBetween(DB::raw('DATE(rb_wallet_requests.created_at)'), [$startDate, $endDate]);

        $query = $query1->union($query2);

        $rows = $query->orderBy('created_at', 'desc')->get();

        $result = [];
        foreach ($rows as $r) {
            // if table has status field it will be returned; otherwise default to 'success'
            $status = property_exists($r, 'status') ? $r->status : 'success';

            $trxType = strtolower((string)($r->trx_type ?? ''));
            // normalize type to 'debit' or 'credit'
            if ($trxType == 'topup') {
                $type = 'topup';
                if ($r->bukti_topup !== null) {
                    $status = 'onprocess';
                }
            } elseif ($trxType == 'withdraw') {
                $type = 'withdraw';
            } else {
                $type = in_array($trxType, ['debit', 'd', 'withdraw', 'out']) ? 'debit' : 'credit';
            }

            

            $created = strtotime($r->created_at);

            $result[] = [
                'id' => (string)$r->id,
                'type' => $type,
                'amount' => $type === 'debit' ? - (int)$r->amount : (int)$r->amount,
                'date' => date('Y-m-d', $created),
                'time' => date('H:i', $created),
                'status' => $status,
                'note' => (string)($r->note ?? ''),
            ];
        }

        return $result;
    }


    public function getTopUpMethods(Request $req)
    {
        return DB::table('rb_rekening')
            ->select('id_rekening', 'nama_bank', 'no_rekening', 'pemilik_rekening')
            ->where('aktif', '1')
            ->orderBy('nama_bank', 'asc')
            ->get();
    }


    public function createTopUpRequest(Request $req)
    {
        $cekKonsumen = DB::table('rb_konsumen')->where('no_hp', $req->no_hp)->first();

        if (!$cekKonsumen) {
            return ['success' => false, 'message' => 'ID Konsumen tidak ditemukan'];
        }

        if (empty($req->amount) || !is_numeric($req->amount) || $req->amount <= 0) {
            return ['success' => false, 'message' => 'Amount harus positif dan valid'];
        }

        $id = DB::table('rb_wallet_requests')->insertGetId([
            'id_konsumen' => $cekKonsumen->id_konsumen,
            'req_type' => 'topup',
            'amount' => $req->amount,
            'payment_method' => $req->payment_method ?? null,
            'status' => 'pending',
            'created_at' => now(), 
        ]);

        return ['success' => true, 'id' => $id, 'message' => 'Top up request created'];
    }


    public function uploadTopUpProof(Request $req)
    {
        // accept either top_up_id or top_upid
        $topUpId = $req->top_up_id ?? $req->top_upid ?? $req->topup_id ?? null;

        if (empty($topUpId)) {
            return ['success' => false, 'message' => 'top_up_id is required'];
        }

        $reqRow = DB::table('rb_wallet_requests')->where('id', $topUpId)->first();
        if (!$reqRow) {
            return ['success' => false, 'message' => 'Top up request not found'];
        }

        $filename = null;

        if ($req->hasFile('bukti_transfer')) {
            $file = $req->file('bukti_transfer');
            $ext = $file->getClientOriginalExtension() ?: 'jpg';
            $filename = 'bukti_transfer_' . $topUpId . '_' . time() . '.' . $ext;
            // store in storage/app/public/top_up
            $path = $file->storeAs('top_up', $filename, 'public');
            if (!$path) {
                return ['success' => false, 'message' => 'Gagal menyimpan file'];
            }
        } elseif (!empty($req->bukti_transfer) && is_string($req->bukti_transfer)) {
            // support base64 data URI
            if (preg_match('/^data:([^;]+);base64,/', $req->bukti_transfer, $m)) {
                $mime = $m[1];
                $ext = explode('/', $mime)[1] ?? 'jpg';
                $data = base64_decode(substr($req->bukti_transfer, strlen($m[0])));
                $filename = 'bukti_transfer_' . $topUpId . '_' . time() . '.' . $ext;
                Storage::disk('public')->put('top_up/' . $filename, $data);
            } else {
                return ['success' => false, 'message' => 'No valid uploaded file found'];
            }
        } else {
            return ['success' => false, 'message' => 'bukti_transfer file required'];
        }

        $update = [
            'proof_image' => $filename,
            'updated_at' => now(),
        ];

        DB::table('rb_wallet_requests')->where('id', $topUpId)->update($update);

        return ['success' => true, 'message' => 'Bukti transfer uploaded', 'filename' => $filename];
    }


    public function createWithdrawRequest(Request $req)
    {
        // validate consumer token and get konsumen
        $dtKonsumen = cekTokenLoginKonsumen($req->token);

        if (!$dtKonsumen) {
            return ['success' => false, 'message' => 'Token tidak valid'];
        }

        // simple validation
        $no_hp = $req->no_hp ?? null;
        $amount = $req->amount ?? null;
        $bank_name = $req->bank_name ?? null;
        $account_name = $req->account_name ?? null;
        $account_number = $req->account_number ?? null;

        if (empty($no_hp)) {
            return ['success' => false, 'message' => 'no_hp is required'];
        }

        if (empty($amount) || !is_numeric($amount) || $amount <= 0) {
            return ['success' => false, 'message' => 'amount harus positif dan valid'];
        }

        if (empty($bank_name) || empty($account_name) || empty($account_number)) {
            return ['success' => false, 'message' => 'bank_name, account_name, and account_number are required'];
        }

        // verify that no_hp belongs to an existing konsumen (allow creating by token owner)
        $cekKonsumen = DB::table('rb_konsumen')->where('no_hp', $no_hp)->first();
        if (!$cekKonsumen) {
            return ['success' => false, 'message' => 'ID Konsumen tidak ditemukan'];
        }

        // insert into rb_wallet_requests table using withdraw type
        $insertData = [
            'id_konsumen' => $cekKonsumen->id_konsumen,
            'req_type' => 'withdraw',
            'amount' => $amount,
            'bank' => $bank_name,
            'norek' => $account_number,
            'nama_rekening' => $account_name,
            'status' => $req->status ?? 'pending',
            'created_at' => now(),
        ];

        $id = DB::table('rb_wallet_requests')->insertGetId($insertData);

        if ($id) {
            return ['success' => true, 'id' => $id, 'message' => 'Withdraw request created'];
        }

        return ['success' => false, 'message' => 'Gagal membuat withdraw request'];
    }



    public function getBankList(Request $req)
    {
        // Primary: read from `bank` table. If not present, fallback to `rb_bank` or `rb_rekening` if project uses different naming.
        // Return fields: kode_bank, nama_bank, type, is_aktif where is_aktif = 1

        // Try the most likely table name first
        if (DB::getSchemaBuilder()->hasTable('bank')) {
            return DB::table('bank')
                ->select('kode_bank', 'nama_bank', 'type', 'is_aktif')
                ->where('is_aktif', 1)
                ->orderBy('nama_bank', 'asc')
                ->get();
        }

        // Fallbacks: some projects use rb_bank or rb_rekening for bank data
        if (DB::getSchemaBuilder()->hasTable('rb_bank')) {
            return DB::table('rb_bank')
                ->select('kode_bank', 'nama_bank', 'type', 'is_aktif')
                ->where('is_aktif', 1)
                ->orderBy('nama_bank', 'asc')
                ->get();
        }

        if (DB::getSchemaBuilder()->hasTable('rb_rekening')) {
            // rb_rekening may use different column names; map them where possible
            $cols = DB::getSchemaBuilder()->getColumnListing('rb_rekening');
            $select = [];
            if (in_array('kode_bank', $cols)) $select[] = 'kode_bank';
            if (in_array('nama_bank', $cols)) $select[] = 'nama_bank';
            if (in_array('type', $cols)) $select[] = 'type';
            if (in_array('is_aktif', $cols)) $select[] = 'is_aktif';

            // If expected columns don't exist, return the full row filtered by aktif flag
            if (count($select) >= 1) {
                return DB::table('rb_rekening')
                    ->select($select)
                    ->where('is_aktif', '1')
                    ->orderBy(in_array('nama_bank', $select) ? 'nama_bank' : 'id_rekening', 'asc')
                    ->get();
            }

            return DB::table('rb_rekening')->where('aktif', '1')->get();
        }

        // If no table found, return empty array to keep response consistent
        return [];
    }

}