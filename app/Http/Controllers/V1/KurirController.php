<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use DB;

class KurirController extends Controller
{
    public function __construct(Request $req){
        // cekHeaderApi(getallheaders()['Authorization']);

        if ($req->token == '') {
            // http_response_code(404);
            // exit(json_encode(['Message' => 'Token Tidak Berlaku']));
        }
        cekTokenLogin($req->token);
    }


    public function getDistance($titik_jemput, $titik_tujuan)
    {
        if ($titik_jemput == $titik_tujuan) {
            return "Tidak Bisa Sama";
        }
        $arrJemput = explode(',', $titik_jemput);
        $arrTujuan = explode(',', $titik_tujuan);

        $latitudeFrom = $arrJemput[0];
        $longitudeFrom = $arrJemput[1];

        $latitudeTo = $arrTujuan[0];
        $longitudeTo = $arrTujuan[1];

        //Calculate distance from latitude and longitude
        $theta = $longitudeFrom - $longitudeTo;
        $dist = sin(deg2rad($latitudeFrom)) * sin(deg2rad($latitudeTo)) +  cos(deg2rad($latitudeFrom)) * cos(deg2rad($latitudeTo)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;

        $distance = ($miles * 1.609344).' km';

        return ceil((float)$distance);
    }


    public function getPrice($titik_jemput, $titik_tujuan, $service){
        // return $this->getCityFromLatLng($titik_jemput);
        if ($titik_jemput == $titik_tujuan) {
            return "Tidak Bisa Sama";
        }
        $distance = $this->getDistance($titik_jemput, $titik_tujuan);
        if ($distance > $this->getConfig('max_jarak_km') && $this->getConfig('max_jarak_km') > 0) {
            return 0-1;
        }
        
        $kota = DB::table('tb_ro_cities')->where('city_name', $this->getCityFromLatLng($titik_jemput))->first();
        if($kota){
            $id_kota = $kota->city_id;
        } else {
            $id_kota = '0';
        }
        
        $getTarif = DB::table('tarif_layanan')->where('id_kota', $id_kota)->first();
        if(!$getTarif){
            $getTarif = DB::table('tarif_layanan')->where('id_kota', '0')->first();
        }
        
        // $tarif_awal = $this->getConfig('ongkir_awal_kurir');
        // $tarif_dinamis = $this->getConfig('ongkir_per_km');
        if($service == 'RIDE'){
            $tarif_awal = $getTarif->tarif_awal_ride;
            $tarif_dinamis = $getTarif->tarif_km_ride;    
        } else if($service == 'SEND'){
            $tarif_awal = $getTarif->tarif_awal_send;
            $tarif_dinamis = $getTarif->tarif_km_send;    
        } else if($service == 'SHOP'){
            $tarif_awal = $getTarif->tarif_awal_shop;
            $tarif_dinamis = $getTarif->tarif_km_shop;
        } else if($service == 'FOOD'){
            $tarif_awal = $getTarif->tarif_awal_food;
            $tarif_dinamis = $getTarif->tarif_km_food;
        } else {
            $tarif_awal = $getTarif->tarif_awal_ride;
            $tarif_dinamis = $getTarif->tarif_km_ride;
        }
        
        $tarif = $tarif_awal + ($tarif_dinamis * $distance);
        
        $dtIns['kota'] = $id_kota;
        $dtIns['awal'] = $tarif_awal;
        $dtIns['dinamis'] = $tarif_dinamis;
        $dtIns['jarak'] = $distance;
        $dtIns['ongkir'] = $tarif;
        $dtIns['service'] = $service;
        DB::table('api_ongkir')->insert($dtIns);

        return $tarif;
    }
    
    function getCityFromLatLng($latLing) {
        
        // Make a request to the Google Maps Geocoding API
        $url = "https://maps.googleapis.com/maps/api/geocode/json?latlng=".str_replace(' ','',$latLing)."&key=AIzaSyAjruIgxJm6OS9ewzXUJ17CR7DMPcozpfo";
        $response = file_get_contents($url);
    
        // Decode the JSON response
        $data = json_decode($response, true);
    
        // Extract city from the response
        if ($data && isset($data['results'][0]['address_components'])) {
            $addressComponents = $data['results'][0]['address_components'];
            $city = '';
    
            foreach ($addressComponents as $component) {
                if (in_array('administrative_area_level_2', $component['types'])) {
                    $city = $component['long_name'];
                    break;
                }
            }
    
            // Return the city name
            return $city;
        }
    
        // Return an error message if city is not found
        return 'City not found';
    }

    public function orderKurir(Request $req)
    {
        $titik_jemput = $req->titik_jemput;
        $titik_tujuan = $req->titik_tujuan;
        $service = $req->service;
        $id_penjualan = $req->id_penjualan;
        $souce = $req->source;

        if ($titik_jemput == '' || $titik_tujuan == '') {
            http_response_code(404);
            exit(json_encode(['Message' => 'Titik antar dan jemput tidak boleh kosong']));
        }

        if ($titik_jemput == $titik_tujuan) {
            http_response_code(404);
            exit(json_encode(['Message' => 'Titik antar dan jemput tidak boleh sama']));
        }

        $tarif = $this->getPrice($titik_jemput, $titik_tujuan, $service);

        $data['tarif'] = $tarif;

        if ($id_penjualan != '' && ($souce == 'POS' || $souce == 'MP')) {
            $cekPenjualan = DB::table('kurir_order')->where('id_penjualan', $id_penjualan)->where('source', $souce)->whereNotIn('status',['cancel','onprocess'])->get();
            if (count($cekPenjualan) > 0) {
                http_response_code(404);
                exit(json_encode(['Message' => 'Id Penjualan Masih Aktif']));
            }

            $cekTblPenjualan = DB::table('rb_penjualan')->where('id_penjualan', $id_penjualan)->first();
            if (!$cekTblPenjualan) {
                http_response_code(404);
                exit(json_encode(['Message' => 'Penjualan Tidak Ada Di Marketplace']));
            }

            $data['kode_order'] = 'SND-'.time();
        }

        if ($req->exists('jenis_layanan') && $req->source == 'APPS') {
            $dtLogin = cekTokenLoginKonsumen($req->login_token);
            $dtTambahan = json_decode($req->data_tambahan);

            if ($req->jenis_layanan == 'KURIR') {
                $data['status'] = 'NEW';

                $data['kode_order']                 = 'SND-'.time();
                $data['alamat_jemput']              = $dtTambahan->alamat_jemput;
                $data['pemberi_barang']             = $dtTambahan->pemberi_barang;
                $data['telp_pemberi_barang']        = $dtTambahan->telp_pemberi_barang;
                $data['catatan_pemberi_barang']     = $dtTambahan->catatan_pemberi_barang;

                $data['alamat_antar']               = $dtTambahan->alamat_antar;
                $data['penerima_barang']            = $dtTambahan->penerima_barang;
                $data['telp_penerima_barang']       = $dtTambahan->telp_penerima_barang;
                $data['catatan_penerima_barang']    = $dtTambahan->catatan_penerima_barang;
            } else if ($req->jenis_layanan == 'RIDE') {
                $data['status'] = 'NEW';

                $data['kode_order']                 = 'RDR-'.time();
                $data['alamat_jemput']              = $dtTambahan->alamat_jemput;
                // $data['catatan_pemberi_barang']     = $dtTambahan->catatan_pemberi_barang;

                $data['alamat_antar']               = $dtTambahan->alamat_antar;
                // $data['catatan_penerima_barang']    = $dtTambahan->catatan_penerima_barang;
            } else if ($req->jenis_layanan == 'FOOD') {
                $data['status'] = 'NEW';

                $data['kode_order']                 = 'FOD-'.time();
                $data['alamat_jemput']              = $dtTambahan->alamat_jemput;
                $data['id_penjualan']               = $dtTambahan->id_penjualan;
                $data['alamat_antar']               = $dtTambahan->alamat_antar;
            } else if ($req->jenis_layanan == 'SHOP') {
                $data['status'] = 'PENDING';

                $data['kode_order']                 = 'SHP-'.time();
                $data['alamat_jemput']              = $dtTambahan->alamat_jemput;
                $data['alamat_antar']               = $dtTambahan->alamat_antar;
                $data['pemberi_barang']             = $dtTambahan->pemberi_barang;
                $data['id_reseller']             = $dtTambahan->id_reseller;
            } else {
                # code...
            }
            $data['id_pemesan']             = $dtLogin->id_konsumen;
            $data['jenis_layanan']          = $req->jenis_layanan;
        } else {
            $data['id_penjualan'] = $id_penjualan;
        }

        if ($req->exists('metode_pembayaran')){
            $data['metode_pembayaran'] = $req->metode_pembayaran;
        } else {
            $data['metode_pembayaran'] = 'WALLET';
        }

        $data['titik_jemput'] = $titik_jemput;
        $data['titik_antar'] = $titik_tujuan;
        $data['service'] = $service;
        
        $data['tanggal_order'] = date('Y-m-d H:i:s');
        $data['source'] = $souce;
        

        $insertOrder = DB::table('kurir_order')->insert($data);
        $id = DB::getPdo()->lastInsertId();

        if ($insertOrder) {
            notifOrderBaru();
            http_response_code(200);
            exit(json_encode(['Message' => 'Order Sukses', 'id' => $id]));
        } else {
            http_response_code(404);
            exit(json_encode(['Message' => 'Kesalahan Menyimpan Order']));
        }
        
        
    }

    public function test(Request $req)
    {
        return "TEST";
    }


    public function notifUpdateOrder(Request $req)
    {
        $token = $req->device_token;
        $pesan = $req->pesan;
        $no_hp = $req->no_hp;
        
        notifOrderUpdate($no_hp, $pesan);
        notifOrderUpdate($token, $pesan);
    }

}