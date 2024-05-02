<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use DB;

class KasirController extends Controller
{
    public function __construct(Request $req){
        // cekHeaderApi(getallheaders()['Authorization']);

        if ($req->token == '') {
            http_response_code(404);
            exit(json_encode(['Message' => 'Token Tidak Berlaku']));
        }
        cekTokenLogin($req->token);
    }

    // USER START
    public function LevelUser(Request $req)
    {
        $dt_user = DB::table('pos_user')->where('remember_token', $req->token)->first();

        $LevelLogin = $dt_user->level;
        if ($LevelLogin == '') {
            http_response_code(404);
            exit(json_encode(['Message' => 'Level Login Kosong']));
        }
        
        $dtLevel = ['owner','admin', 'kasir'];
        $key = array_search($LevelLogin, $dtLevel);
        if ($key == '') {
            http_response_code(404);
            exit(json_encode(['Message' => 'Terjadi Kesalahan, Silahkan Coba Lagi']));
        }
        
        $newDtLevel = [];
        for ($i=$key+1; $i < count($dtLevel); $i++) { 
            array_push($newDtLevel, $dtLevel[$i]);
        }
        http_response_code(200);
        return $newDtLevel;
    }

    public function ListUser(Request $req)
    {
        $cekUserPos = DB::table('pos_user')->where('remember_token', $req->token)->first();
        $listUser = DB::table('rb_konsumen as a')->leftJoin('rb_reseller as b', 'b.id_konsumen', 'a.id_konsumen')->select('a.*', 'b.id_reseller', 'b.nama_reseller')->where('a.id_pos_user', $cekUserPos->id)->get();

        if(!$listUser){
            http_response_code(404);
            exit(json_encode(['Message' => 'Belum Ada User']));
        }

        // $LevelLogin = $listUser->level;
        // if ($LevelLogin == '') {
        //     http_response_code(404);
        //     exit(json_encode(['Message' => 'Level Login Kosong']));
        // }
        
        // $dtLevel = ['owner','admin', 'kasir'];
        // $key = array_search($LevelLogin, $dtLevel);
        // if ($key == '') {
        //     http_response_code(404);
        //     exit(json_encode(['Message' => 'Terjadi Kesalahan, Silahkan Coba Lagi']));
        // }
        // $newDtLevel = [];
        // for ($i=$key+1; $i < count($dtLevel); $i++) { 
        //     array_push($newDtLevel, $dtLevel[$i]);
        // }
        
        // $listUser = DB::table('pos_user')->where('parent', $dt_user->parent)->whereIn('level', $newDtLevel)->get();
        
        if (count($listUser) > 0) {
            http_response_code(200);
            return $listUser;
        } else {
            http_response_code(404);
            exit(json_encode(['Message' => 'Belum Ada User']));
        }
    }


    public function SaveUser(Request $req)
    {
        $dt_user = DB::table('pos_user')->where('remember_token', $req->token)->first();

        $dtInsert['nama_lengkap'] = $req->nama_user;
        $dtInsert['no_hp'] = $req->hp_user;
        $dtInsert['email'] = $req->email_user;
        $dtInsert['username'] = $req->email_user;
        $dtInsert['id_pos_user'] = $dt_user->parent;
        $dtInsert['password'] = Hash::make('password');
        $dtInsert['tanggal_lahir'] = $req->tanggal_lahir;
        $dtInsert['tempat_lahir'] = '';
        $dtInsert['alamat_lengkap'] = '';
        $dtInsert['kecamatan_id'] = '0';
        $dtInsert['kota_id'] = '0';
        $dtInsert['provinsi_id'] = '0';
        $dtInsert['foto'] = '';
        $dtInsert['tanggal_daftar'] = date('Y-m-d');
        $idCabang = $req->cabang_user;

        if ($req->id_user != '-') {
            $cek_exist = DB::table('rb_konsumen')->where('no_hp', $req->hp_user)->where('id_konsumen', '!=', $req->id_user)->first();
            if ($cek_exist) {
                http_response_code(404);
                exit(json_encode(['Message' => 'Nomor HP SUdah Terdaftar']));
            }

            $dtUser = DB::table('rb_konsumen')->where('id_konsumen', $req->id_user)->update($dtInsert);
        } else {
            $cek_exist = DB::table('rb_konsumen')->where('no_hp', $req->hp_user)->first();
            if ($cek_exist) {
                http_response_code(404);
                exit(json_encode(['Message' => 'Nomor HP SUdah Terdaftar']));
            }
            $dtUpdate['id_konsumen'] = DB::table('rb_konsumen')->insertGetId($dtInsert);

            $dtUser = DB::table('rb_reseller')->where('id_reseller', $idCabang)->update($dtUpdate);
        }
        
        

        if (!$dtUser) {
            http_response_code(404);
            exit(json_encode(['Message' => 'Gagal Menyimpan']));
        } else {
            http_response_code(200);
            exit(json_encode(['Message' => 'Sukses Menyimpan']));
        }
    }

    public function DetailUser(Request $req)
    {
        $dt_user = DB::table('rb_konsumen as a')->leftJoin('rb_reseller as b', 'b.id_konsumen', 'a.id_konsumen')->select('a.*', 'b.id_reseller', 'b.nama_reseller')->where('a.id_konsumen', $req->id_user)->first();
        
        if (!$dt_user) {
            http_response_code(404);
            exit(json_encode(['Message' => 'Belum Ada User']));
        } else {
            http_response_code(200);
            return $dt_user;
        }
    }

    public function DeleteUser(Request $req)
    {
        $dt_user = DB::table('pos_user')->where('id', $req->id_user)->first();
        

        if (!$dt_user) {
            http_response_code(404);
            exit(json_encode(['Message' => 'Belum Ada User']));
        } else {
            $dtHapus = DB::table('pos_user')->where('id', $req->id_user)->delete();
            if (!$dtHapus) {
                http_response_code(404);
                exit(json_encode(['Message' => 'Gagal Menghapus']));
            }
            http_response_code(200);
        }
    }
    // USER END

    // CABANG START
    public function ListCabangUser(Request $req)
    {
        $dt_user = DB::table('pos_user')->where('remember_token', $req->token)->first();
        $dtCabang = DB::table('rb_reseller as a')
        ->select('a.*', 'b.subdistrict_name as kecamatan', 'c.city_name as kota')
        ->rightJoin('tb_ro_subdistricts as b', 'b.subdistrict_id', 'a.kecamatan_id')
        ->rightJoin('tb_ro_cities as c', 'c.city_id', 'a.kota_id')
        ->where('a.id_user_pos', $dt_user->id);

        if ($req->type == 'add') {
            $dtCabang =$dtCabang->where('a.id_konsumen', '0');
        }

        $dtCabang =$dtCabang->get();
        return $dtCabang;
    }


    public function ListCabang(Request $req)
    {
        $dt_user = DB::table('pos_user')->where('remember_token', $req->token)->first();
        $dtCabang = DB::table('rb_reseller as a')->select('a.*', 'b.subdistrict_name as kecamatan', 'c.city_name as kota')->rightJoin('tb_ro_subdistricts as b', 'b.subdistrict_id', 'a.kecamatan_id')->rightJoin('tb_ro_cities as c', 'c.city_id', 'a.kota_id')->where('a.id_user_pos', $dt_user->id)->get();

        if (count($dtCabang) == 0) {
            http_response_code(404);
            exit(json_encode(['Message' => 'Belum Ada Cabang']));
        } else {
            http_response_code(200);
            return $dtCabang;
        }
    }

    public function DetailCabang(Request $req)
    {
        $dt_user = DB::table('pos_user')->where('remember_token', $req->token)->first();
        $dtCabang = DB::table('rb_reseller as a')->select('a.*', 'b.subdistrict_name as kecamatan', 'c.city_name as kota')->rightJoin('tb_ro_subdistricts as b', 'b.subdistrict_id', 'a.kecamatan_id')->rightJoin('tb_ro_cities as c', 'c.city_id', 'a.kota_id')->where('a.id_user_pos', $dt_user->id)->where('a.id_reseller', $req->id_toko)->first();

        if (!$dtCabang) {
            http_response_code(404);
            exit(json_encode(['Message' => 'Belum Ada Cabang']));
        } else {
            http_response_code(200);
            return $dtCabang;
        }
    }

    public function SaveCabang(Request $req)
    {
        $dt_user = DB::table('pos_user')->where('remember_token', $req->token)->first();

        $dtInsert['id_konsumen'] = 0;
        $dtInsert['nama_reseller'] = $req->nama_toko;
        $dtInsert['kecamatan_id'] = $req->kecamatan_toko;
        $dtInsert['kota_id'] = $req->kota_toko;
        $dtInsert['provinsi_id'] = $req->provinsi_toko;
        $dtInsert['alamat_lengkap'] = $req->alamat_toko;
        $dtInsert['no_telpon'] = $req->nomor_hp_toko;
        $dtInsert['keterangan'] = $req->deskripsi_toko;
        $dtInsert['tanggal_daftar'] = date('Y-m-d H:i:s');
        $dtInsert['verifikasi'] = 'Y';
        $dtInsert['id_user_pos'] = $dt_user->id;

        if ($req->id_toko != '-') {
            $dtCabang = DB::table('rb_reseller')->where('id_reseller', $req->id_toko)->update($dtInsert);
        } else {
            $dtCabang = DB::table('rb_reseller')->insert($dtInsert);
        }
        
        

        if (!$dtCabang) {
            http_response_code(404);
            exit(json_encode(['Message' => 'Gagal Menyimpan']));
        } else {
            http_response_code(200);
            exit(json_encode(['Message' => 'Sukses Menyimpan']));
        }
    }

    public function DeleteCabang(Request $req)
    {
        $dt_user = DB::table('pos_user')->where('remember_token', $req->token)->first();
        $dtCabang = DB::table('rb_reseller as a')->select('a.*', 'b.subdistrict_name as kecamatan', 'c.city_name as kota')->rightJoin('tb_ro_subdistricts as b', 'b.subdistrict_id', 'a.kecamatan_id')->rightJoin('tb_ro_cities as c', 'c.city_id', 'a.kota_id')->where('a.id_user_pos', $dt_user->id)->where('a.id_reseller', $req->id_toko)->first();

        if (!$dtCabang) {
            http_response_code(404);
            exit(json_encode(['Message' => 'Belum Ada Cabang']));
        } else {
            $dtHapus = DB::table('rb_reseller')->where('id_reseller', $req->id_toko)->delete();
            if (!$dtHapus) {
                http_response_code(404);
                exit(json_encode(['Message' => 'Gagal Menghapus']));
            }
            http_response_code(200);
        }
    }



    // CABANG END



    // BARANG START
    public function ListBarang(Request $req)
    {
        $data['dtBarang'] = DB::table('rb_produk as a')
        // ->select('a.id_produk', 'a.nama_produk', 'a.harga_reseller', 'a.harga_konsumen', 'a.harga_premium','a.harga_beli', 'a.berat','a.gambar','a.satuan', 'b.nama_kategori')
        ->select('a.*', 'b.nama_kategori', 'c.diskon')
        ->leftJoin('rb_kategori_produk as b', 'b.id_kategori_produk', 'a.id_kategori_produk')
        ->leftJoin('rb_produk_diskon as c', 'c.id_produk', 'a.id_produk')
        ->where('a.id_reseller', $req->id_toko)
        ->where('a.aktif', 'Y')->get();
        // return array_column(json_decode($data['dtBarang']), 'id_produk');

        $data['dtVariasi'] = DB::table('rb_produk_variasi as a')
        ->select('a.*')
        ->whereIn('a.id_produk', array_column(json_decode($data['dtBarang']), 'id_produk'))->orderBy('a.id_produk')->get();

        $data['dtKategori'] = DB::table('rb_kategori_produk as a')
        ->select('a.*')
        ->orderBy('a.nama_kategori')->get();

        $data['dtTerlaris'] = DB::table('rb_penjualan_detail as b')
        ->select('a.id_produk', 'a.nama_produk', 'a.harga_reseller', 'a.harga_konsumen', 'a.harga_premium','a.harga_beli', 'a.berat','a.gambar', 'a.id_kategori_produk', 'a.source')
        ->rightJoin('rb_produk as a', 'a.id_produk', 'b.id_produk')
        ->where('a.aktif', 'Y')
        ->where('a.id_reseller', $req->id_toko)
        ->groupBy('a.id_produk', 'a.nama_produk', 'a.harga_reseller', 'a.harga_konsumen', 'a.harga_premium','a.harga_beli', 'a.berat','a.gambar', 'a.id_kategori_produk', 'a.source')
        ->orderBy(DB::Raw('sum(b.jumlah)'), 'DESC')
        ->get();



        if (count($data['dtBarang']) == 0) {
            http_response_code(404);
            exit(json_encode(['Message' => 'Belum Ada Barang']));
        } else {
            http_response_code(200);
            return $data;
        }
    }


    public function GetKupon(Request $req)
    {
        $kupon = $req->kode_kupon;
        $now = date('Y-m-d H:i:s');

        $cek_kupon = DB::table('rb_produk_kupon')
        ->where('kode_kupon', $kupon)
        // ->where('created_date', '>=' ,$now)
        // ->where('expire_date', '>=' ,$now)
        ->where('jumlah_kupon', '>', '0')
        ->first();

        if (!$cek_kupon) {
            http_response_code(404);
            return json_encode(['Message' => 'Kupon Tidak Ada']);
        } else {
            http_response_code(200);
            return $cek_kupon;
        }
    }
    // BARANG END
}
