<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use DB;
use App;
use Illuminate\Http\Request;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function testNotif(){
        // return sendNotif();
        return notif_wa_otp('081340094756', '12345');
        // return notifOrderBaruWa();
    }


    public function getConfig($config){
        $dtConfig = DB::table('rb_config')->where('field', $config)->first();
        return $dtConfig->value;
    }
    
    
    public function getPaket(){
        $dtPaket = DB::table('rb_paket')->where('jenis', 'konsumen')->get();
        
        // return $dtPaket;
        
        $data['paket'] = ['Total Produk', 'Banner Web', 'Popup Promosi', 'Web Identity', 'Support Progressive Web Apps (PWA)', 'Total Level Refferal', 'Fitur Cashback', 'Template Website', 'Notifikasi Browser Konsumen', 'Dukungan Prioritas', 'Whatsapp Group'];
        $data['rincian_paket'] = [];
        for($i=0; $i < count($dtPaket); $i++){
            $data['rincian_paket'][$i]['nama'] = $dtPaket[$i]->nama_paket;
            $data['rincian_paket'][$i]['kode'] = base64_encode($dtPaket[$i]->id_paket);
            $data['rincian_paket'][$i]['harga'] = $dtPaket[$i]->harga;
            $data['rincian_paket'][$i]['tahunan'] = $dtPaket[$i]->hitung_tahunan;
            $data['rincian_paket'][$i]['benefit'][0] = $dtPaket[$i]->max_produk.' Produk';
            $data['rincian_paket'][$i]['benefit'][1] = (int)$dtPaket[$i]->banner;
            $data['rincian_paket'][$i]['benefit'][2] = (int)$dtPaket[$i]->popup_promosi;
            $data['rincian_paket'][$i]['benefit'][3] = (int)$dtPaket[$i]->web_identity;
            $data['rincian_paket'][$i]['benefit'][4] = (int)$dtPaket[$i]->pwa;
            $data['rincian_paket'][$i]['benefit'][5] = $dtPaket[$i]->refferal.' Level';
            $data['rincian_paket'][$i]['benefit'][6] = (int)$dtPaket[$i]->cashback;
            $data['rincian_paket'][$i]['benefit'][7] = $dtPaket[$i]->template.' Template';
            $data['rincian_paket'][$i]['benefit'][8] = (int)$dtPaket[$i]->notif_browser;
            $data['rincian_paket'][$i]['benefit'][9] = (int)$dtPaket[$i]->support_priority;
            $data['rincian_paket'][$i]['benefit'][10] = (int)$dtPaket[$i]->group_wa;
            
        }
        
        return $data;
    }
    
    public function getUsername(Request $req){
        $dtConfig = DB::table('rb_konsumen')->select('subdomain', 'nama_lengkap')->where('username', $req->username)->first();
        if($dtConfig){
            http_response_code(200);
            exit(json_encode($dtConfig));
        } else {
            http_response_code(400);
            exit(json_encode([]));
        }
        
    }
    
    
    public function cekKomisi($id){
        // echo $id.'</br>';
        // $getPenjualan = DB::table('rb_penjualan')->where('id_penjualan', $id)->where('status_pembeli', 'konsumen')->whereIn('proses', ['0','1','2','3'])->first();
        $getPenjualan = DB::table('rb_penjualan')->where('id_penjualan', $id)->where('status_pembeli', 'konsumen')->first();
        // echo json_encode($getPenjualan);
        
        $maxRefferalLevel = DB::table('rb_paket')->where('jenis', 'konsumen')->orderBy('refferal','desc')->first()->refferal;
        
        if($getPenjualan){
            // update Status
            DB::table('rb_penjualan')->where('id_penjualan', $id)->where('status_pembeli', 'konsumen')->update(['proses'=>'4']);
            
            // get reff
            $getKonsumen = DB::table('rb_konsumen')->where('id_konsumen', $getPenjualan->id_pembeli)->first();
            $getLevel = DB::table('rb_reseller_paket as a')->select('a.*', 'b.nama_paket', 'b.lable_harga', 'b.refferal')->leftJoin('rb_paket as b','b.id_paket','a.id_paket')->where('a.status', 'Y')->where('a.id_reseller', $getKonsumen->id_konsumen)->where('a.expire_date', '>=', "date('Y-m-d')")->where('a.users','konsumen')->first();
            if(!$getLevel){
                $getLevel['nama_paket'] = 'Newbie';
                $getLevel['lable_harga'] = 'harga_level_newbie';
                $getLevel['refferal'] = 1;
                
                $getLevel = (object)$getLevel;
            }
            
            echo '</br>======================================';
            echo '</br>Barang Dibeli : ';
            $admin = 0;
            $deviden = 0;
            $dataBarangInvoice = DB::table('rb_penjualan_detail as a')->select('b.harga_konsumen', 'b.harga_platform', 'b.nama_produk', 'b.harga_level_newbie', 'b.harga_level_pedagang', 'b.harga_level_juragan', 'b.harga_level_big', 'b.harga_level_bos', 'a.jumlah', 'b.admin_persen', 'b.deviden_persen')->leftJoin('rb_produk as b', 'b.id_produk', 'a.id_produk')->where('a.id_penjualan',$id)->get();
            foreach($dataBarangInvoice as $brg){
                echo '</br> Nama Barang: '.$brg->nama_produk.' </br>Qty: '.$brg->jumlah.' </br>Harga Jual : '.number_format($brg->harga_konsumen).' </br>Harga Platform : '.number_format($brg->harga_platform).' </br>Harga Level Basic : '.number_format($brg->harga_level_newbie).' </br>Harga Level Bronze : '.number_format($brg->harga_level_pedagang).' </br>Harga Level Silver : '.number_format($brg->harga_level_juragan).' </br>Harga Level Gold : '.number_format($brg->harga_level_big).' </br>Harga Level Platinum : '.number_format($brg->harga_level_bos).'</br>Admin: '.$brg->admin_persen.'%</br>Deviden: '.$brg->deviden_persen.'%</br></br></br>';
                
                if($brg->harga_level_bos > $brg->harga_platform){
                    $admin += (($brg->harga_level_bos - $brg->harga_platform)*$brg->admin_persen)/100;
                    $deviden += (($brg->harga_level_bos - $brg->harga_platform)*$brg->deviden_persen)/100;
                }
            }
            
            echo '</br>Total Fee Admin: '.number_format($admin);
            echo '</br>Total Fee Deviden: '.number_format($deviden);
            
            
            echo '</br>======================================';
            echo '</br>Konsumen: '.$getKonsumen->nama_lengkap;
            echo '</br>Level: '.$getLevel->nama_paket;
            echo '</br>======================================';
            echo '</br></br></br>======================================';
            echo '</br>Max Level Upline Yang Dapat Komisi: '.$maxRefferalLevel;
            
            $sponsor = $getKonsumen->referral_id;
            $sponsor_level = $getLevel->nama_paket;
            $sponsor_level_lable_harga = $getLevel->lable_harga;
            if($sponsor == null){
                echo '</br></br></br>============ TIDAK PUNYA SPONSOR ===========';
                return;
            }
            
            $x = 1;
            while($x <= $maxRefferalLevel){
                echo '</br></br></br>======================================';
                
                $dataRefferal = DB::table('rb_konsumen')->where('id_konsumen', $sponsor)->first();
                
                
                if($dataRefferal){
                    $getLevelReff = DB::table('rb_reseller_paket as a')->select('a.*', 'b.nama_paket', 'b.lable_harga', 'b.refferal')->leftJoin('rb_paket as b','b.id_paket','a.id_paket')->where('a.status', 'Y')->where('a.id_reseller', $dataRefferal->id_konsumen)->where('a.expire_date', '>=', "date('Y-m-d')")->where('a.users','konsumen')->first();
                    if(!$getLevelReff){
                        $getLevelReff['nama_paket'] = 'Newbie';
                        $getLevelReff['lable_harga'] = 'harga_level_newbie';
                        $getLevelReff['refferal'] = 1;
                        
                        $getLevelReff = (object)$getLevelReff;
                    }
                
                    echo '</br>**Upline Ke : '.$x.'**';
                    echo '</br>Nama Upline : '.$dataRefferal->id_konsumen.'. '.$dataRefferal->nama_lengkap;
                    echo '</br>Level : '.$getLevelReff->nama_paket;
                    echo '</br>Max Refferal Level Dari Paket : '.$getLevelReff->refferal;
                    echo '</br>No HP : '.$dataRefferal->no_hp;
                    
                    echo '</br></br>Barang Dibeli : ';
                    $dataBarangJual = DB::table('rb_penjualan_detail as a')->select('b.nama_produk', 'b.harga_level_newbie', 'b.harga_level_pedagang', 'b.harga_level_juragan', 'b.harga_level_big', 'b.harga_level_bos', 'a.jumlah')->leftJoin('rb_produk as b', 'b.id_produk', 'a.id_produk')->where('a.id_penjualan',$id)->get();
                    $totalKomisi = 0;
                    foreach($dataBarangJual as $brg){
                        $hargaDownline = 0;
                        if($sponsor_level_lable_harga == 'harga_level_newbie'){
                            $hargaDownline = $brg->harga_level_newbie;
                        }elseif($sponsor_level_lable_harga == 'harga_level_pedagang'){
                            $hargaDownline = $brg->harga_level_pedagang;
                        }elseif($sponsor_level_lable_harga == 'harga_level_juragan'){
                            $hargaDownline = $brg->harga_level_juragan;
                        }elseif($sponsor_level_lable_harga == 'harga_level_big'){
                            $hargaDownline = $brg->harga_level_big;
                        }elseif($sponsor_level_lable_harga == 'harga_level_bos'){
                            $hargaDownline = $brg->harga_level_bos;
                        }
                        
                        $hargaSendiri = 0;
                        if($getLevelReff->lable_harga == 'harga_level_newbie'){
                            $hargaSendiri = $brg->harga_level_newbie;
                        }elseif($getLevelReff->lable_harga == 'harga_level_pedagang'){
                            $hargaSendiri = $brg->harga_level_pedagang;
                        }elseif($getLevelReff->lable_harga == 'harga_level_juragan'){
                            $hargaSendiri = $brg->harga_level_juragan;
                        }elseif($getLevelReff->lable_harga == 'harga_level_big'){
                            $hargaSendiri = $brg->harga_level_big;
                        }elseif($getLevelReff->lable_harga == 'harga_level_bos'){
                            $hargaSendiri = $brg->harga_level_bos;
                        }
                        
                        echo '</br> Nama Barang: '.$brg->nama_produk.' || Qty: '.$brg->jumlah.' || Harga Level Downline '.$sponsor_level.': '.number_format($hargaDownline).' || Harga Level Sendiri '.$getLevelReff->nama_paket.': '.number_format($hargaSendiri);
                        if($hargaDownline > $hargaSendiri){
                            $totalKomisi += ($hargaDownline - $hargaSendiri)*$brg->jumlah;
                        } else {
                            $totalKomisi += 0;
                        }
                    }
                    
                    echo '</br></br>Total Komisi Yang Didapat : '.number_format($totalKomisi);
                    
                    // cek lolos komisi atau ngk
                    if($getLevelReff->refferal >= $x){
                        echo '</br></br>### Dapat Komisi Dari Transaksi Ini '.number_format($totalKomisi).' ###';
                    } else {
                        echo '</br></br>TIDAK DAPAT KOMISI';
                    }
                    
                    // cek dapat deviden
                    if($getLevelReff->lable_harga == 'harga_level_bos'){
                        echo '</br></br>### Dapat Komisi Deviden '.number_format($deviden).' ###';
                    }
                    
                    $x++;
                    $sponsor = $dataRefferal->referral_id;
                    $sponsor_level = $getLevelReff->nama_paket;
                    $sponsor_level_lable_harga = $getLevelReff->lable_harga;
                } else {
                    echo '</br></br></br>============ END ===========';
                    $x = $maxRefferalLevel+1;
                }
                
                
            }
        }
        
    }
    
    
    public function cekKomisiRebahan($id){
        
        $getPenjualan = DB::table('rebahandapatcuan_member_paket as a')->select('a.*', 'b.nama_paket', 'b.harga', 'b.komisi')->leftJoin('rebahandapatcuan_paket as b', 'b.id', 'a.id_paket')->where('a.id_member_paket', $id)->whereIn('a.status', ['N','Y'])->first();
        // echo json_encode($getPenjualan);
        
        $maxRefferalLevel = DB::table('rb_paket')->where('jenis', 'konsumen')->orderBy('refferal','desc')->first()->refferal;
        
        if($getPenjualan){
            // update Status
            DB::table('rb_penjualan')->where('id_penjualan', $id)->where('status_pembeli', 'konsumen')->update(['proses'=>'4']);
            
            // get reff
            $getKonsumen = DB::table('rb_konsumen')->where('id_konsumen', $getPenjualan->id_konsumen)->first();
            $getLevel = DB::table('rb_reseller_paket as a')->select('a.*', 'b.nama_paket', 'b.lable_harga', 'b.refferal')->leftJoin('rb_paket as b','b.id_paket','a.id_paket')->where('a.status', 'Y')->where('a.id_reseller', $getKonsumen->id_konsumen)->where('a.expire_date', '>=', "date('Y-m-d')")->where('a.users','konsumen')->first();
            if(!$getLevel){
                $getLevel['nama_paket'] = 'Newbie';
                $getLevel['lable_harga'] = 'harga_level_newbie';
                $getLevel['refferal'] = 1;
                
                $getLevel = (object)$getLevel;
            }
            
            $admin = ($getPenjualan->tagihan*$getPenjualan->komisi)/100;
            
            $persenDeviden = DB::table('rb_config')->where('field','deviden_rebahan')->first();
            $deviden = ($getPenjualan->tagihan*$persenDeviden->value)/100;
            
            
            echo '</br>Total Komisi: '.number_format($admin);
            
            
            
            echo '</br>======================================';
            echo '</br>Konsumen: '.$getKonsumen->nama_lengkap;
            echo '</br>Level: '.$getLevel->nama_paket;
            echo '</br>======================================';
            echo '</br></br></br>======================================';
            echo '</br>Max Level Upline Yang Dapat Komisi: '.$maxRefferalLevel;
            
            $sponsor = $getKonsumen->referral_id;
            $sponsor_level = $getLevel->nama_paket;
            $sponsor_level_lable_harga = $getLevel->lable_harga;
            if($sponsor == null){
                echo '</br></br></br>============ TIDAK PUNYA SPONSOR ===========';
                return;
            }
            
            $x = 1;
            while($x <= $maxRefferalLevel){
                echo '</br></br></br>======================================';
                
                $dataRefferal = DB::table('rb_konsumen')->where('id_konsumen', $sponsor)->first();
                
                
                if($dataRefferal){
                    $getLevelReff = DB::table('rb_reseller_paket as a')->select('a.*', 'b.nama_paket', 'b.lable_harga', 'b.refferal')->leftJoin('rb_paket as b','b.id_paket','a.id_paket')->where('a.status', 'Y')->where('a.id_reseller', $dataRefferal->id_konsumen)->where('a.expire_date', '>=', "date('Y-m-d')")->where('a.users','konsumen')->first();
                    if(!$getLevelReff){
                        $getLevelReff['nama_paket'] = 'Newbie';
                        $getLevelReff['lable_harga'] = 'harga_level_newbie';
                        $getLevelReff['refferal'] = 1;
                        
                        $getLevelReff = (object)$getLevelReff;
                    }
                
                    echo '</br>**Upline Ke : '.$x.'**';
                    echo '</br>Nama Upline : '.$dataRefferal->id_konsumen.'. '.$dataRefferal->nama_lengkap;
                    echo '</br>Level : '.$getLevelReff->nama_paket;
                    echo '</br>Max Refferal Level Dari Paket : '.$getLevelReff->refferal;
                    echo '</br>No HP : '.$dataRefferal->no_hp;
                    
                    echo '</br></br>Paket Dibeli : '. $getPenjualan->nama_paket;
                    $dtPersenKomisi = DB::table('rb_config')->where('field','fee_trx_level'.$x)->first();
                    echo '</br>Komisi Upline : '.$dtPersenKomisi->value.'%';
                    
                    if($dtPersenKomisi){
                        $totalKomisi = ($admin * $dtPersenKomisi->value) / 100;    
                    } else {
                        $totalKomisi = 0;
                    }
                    
                    
                    echo '</br></br>Total Komisi Yang Didapat : '.number_format($totalKomisi);
                    
                    // cek lolos komisi atau ngk
                    if($getLevelReff->refferal >= $x){
                        echo '</br></br>### Dapat Komisi Dari Transaksi Ini '.number_format($totalKomisi).' ###';
                        $pesan = 'Halo Kak '.$dataRefferal->nama_lengkap.', Selamat Kakak mendapatkan komisi sebesar *Rp.'.number_format($totalKomisi).'* dari transaksi pembelian paket *'.$getPenjualan->nama_paket.'* oleh *'.$getKonsumen->nama_lengkap.'*.

Info lebih lanjut silakan cek di member area rebahandapatcuan.com


*Salam Cuan*';
                        // kirim_wa($dataRefferal->no_hp, $pesan);
                    } else {
                        echo '</br></br>TIDAK DAPAT KOMISI';
                    }
                    
                    // // cek dapat deviden
                    // if($getLevelReff->lable_harga == 'harga_level_bos'){
                    //     echo '</br></br>### Dapat Komisi Deviden '.number_format($deviden).' ###';
                    // }
                    
                    $x++;
                    $sponsor = $dataRefferal->referral_id;
                    $sponsor_level = $getLevelReff->nama_paket;
                    $sponsor_level_lable_harga = $getLevelReff->lable_harga;
                } else {
                    echo '</br></br></br>============ END ===========';
                    $x = $maxRefferalLevel+1;
                }
                
                
            }
            
            
            $getPaketDeviden = DB::table('rebahandapatcuan_paket')->where('deviden', '1')->first();
            if($getPaketDeviden){
                $id_paket = $getPaketDeviden->id;
                echo '</br></br></br>============ PEMBAGIAN DEVIDEN ===========';
                
                $getPaketMember = DB::table('rebahandapatcuan_member_paket')->where('id_paket', $id_paket)->where('status','Y')->where('expire_date', '>=', date('Y-m-d'))->get();
                echo '</br></br>';
                if(count($getPaketMember) <= 0){
                    echo '###TIDAK ADA YANG DAPAT DEVIDEN###';
                }
                
                foreach($getPaketMember as $row){
                    echo $row->id_konsumen;
                    $getRefferal = DB::table('rb_konsumen as a')
                        ->rightJoin('rebahandapatcuan_member_paket as b', function ($join) {
                            $join->on('b.id_konsumen', '=', 'a.id_konsumen')
                                ->where('b.status', '=', 'Y')
                                ->where('b.expire_date', '>=', date('Y-m-d'))
                                ->where('b.tagihan', '>', 0);
                        })
                        ->where('a.referral_id', '=', $row->id_konsumen)
                        ->select('a.id_konsumen', 'a.nama_lengkap', 'b.tagihan')
                        ->get();
                        
                    echo '</br></br>';
                    echo count($getRefferal);
                    if(count($getRefferal) >= 10){
                        $dataPenerimaDeviden = DB::table('rb_konsumen')->where('id_konsumen', $row->id_konsumen)->first();
                        echo '====DAPAT DEVIDEN====';
                        echo '</br>'.$dataPenerimaDeviden->nama_lengkap.' Rp.'.number_format($deviden);
                    }
                }
            }
        }
        
    }
    
    
    public function cekKomisiRebahanReal($id){
        
        $getPenjualan = DB::table('rebahandapatcuan_member_paket as a')->select('a.*', 'b.nama_paket', 'b.harga', 'b.komisi')->leftJoin('rebahandapatcuan_paket as b', 'b.id', 'a.id_paket')->where('a.id_member_paket', $id)->whereIn('a.status', ['N','Y'])->first();
        
        $maxRefferalLevel = DB::table('rb_paket')->where('jenis', 'konsumen')->orderBy('refferal','desc')->first()->refferal;
        
        if($getPenjualan){
            // update Status
            DB::table('rb_penjualan')->where('id_penjualan', $id)->where('status_pembeli', 'konsumen')->update(['proses'=>'4']);
            
            // get reff
            $getKonsumen = DB::table('rb_konsumen')->where('id_konsumen', $getPenjualan->id_konsumen)->first();
            $getLevel = DB::table('rb_reseller_paket as a')->select('a.*', 'b.nama_paket', 'b.lable_harga', 'b.refferal')->leftJoin('rb_paket as b','b.id_paket','a.id_paket')->where('a.status', 'Y')->where('a.id_reseller', $getKonsumen->id_konsumen)->where('a.expire_date', '>=', "date('Y-m-d')")->where('a.users','konsumen')->first();
            if(!$getLevel){
                $getLevel['nama_paket'] = 'Newbie';
                $getLevel['lable_harga'] = 'harga_level_newbie';
                $getLevel['refferal'] = 1;
                
                $getLevel = (object)$getLevel;
            }
            
            $admin = ($getPenjualan->tagihan*$getPenjualan->komisi)/100;
            
            $persenDeviden = DB::table('rb_config')->where('field','deviden_rebahan')->first();
            $deviden = ($getPenjualan->tagihan*$persenDeviden->value)/100;
            
            
            
            $sponsor = $getKonsumen->referral_id;
            $sponsor_level = $getLevel->nama_paket;
            $sponsor_level_lable_harga = $getLevel->lable_harga;
            if($sponsor == null){
                return;
            }
            
            $x = 1;
            while($x <= $maxRefferalLevel){
                
                $dataRefferal = DB::table('rb_konsumen')->where('id_konsumen', $sponsor)->first();
                
                
                if($dataRefferal){
                    $getLevelReff = DB::table('rb_reseller_paket as a')->select('a.*', 'b.nama_paket', 'b.lable_harga', 'b.refferal')->leftJoin('rb_paket as b','b.id_paket','a.id_paket')->where('a.status', 'Y')->where('a.id_reseller', $dataRefferal->id_konsumen)->where('a.expire_date', '>=', "date('Y-m-d')")->where('a.users','konsumen')->first();
                    if(!$getLevelReff){
                        $getLevelReff['nama_paket'] = 'Newbie';
                        $getLevelReff['lable_harga'] = 'harga_level_newbie';
                        $getLevelReff['refferal'] = 1;
                        
                        $getLevelReff = (object)$getLevelReff;
                    }
                
                    $dtPersenKomisi = DB::table('rb_config')->where('field','fee_trx_level'.$x)->first();
                    
                    if($dtPersenKomisi){
                        $totalKomisi = ($admin * $dtPersenKomisi->value) / 100;    
                    } else {
                        $totalKomisi = 0;
                    }
                    
                    
                    // cek lolos komisi atau ngk
                    if($getLevelReff->refferal >= $x && $totalKomisi > 0){
                        $dtInsert['id_rekening'] = 0;
                        $dtInsert['id_konsumen'] = $dataRefferal->id_konsumen;
                        $dtInsert['nominal'] = $totalKomisi;
                        $dtInsert['withdraw_fee'] = 0;
                        $dtInsert['status'] = 'Sukses';
                        $dtInsert['transaksi'] = 'debit';
                        $dtInsert['akun'] = 'refferal';
                        $dtInsert['keterangan'] = 'Komisi Pembelian Paket';
                        $dtInsert['waktu_pendapatan'] = date('Y-m-d H:i:s');
                        $dtInsert['source'] = 'rebahandapatcuan';
                        DB::table('rb_pendapatan_kurir')->insert($dtInsert);
                        
                        $pesan = 'Halo Kak '.$dataRefferal->nama_lengkap.', Selamat Kakak mendapatkan komisi sebesar *Rp.'.number_format($totalKomisi).'* dari transaksi pembelian paket *'.$getPenjualan->nama_paket.'* oleh *'.$getKonsumen->nama_lengkap.'*.

Info lebih lanjut silakan cek di member area rebahandapatcuan.com


*Salam Cuan*';
                        kirim_wa($dataRefferal->no_hp, $pesan);
                    }
                    
                    
                    $x++;
                    $sponsor = $dataRefferal->referral_id;
                    $sponsor_level = $getLevelReff->nama_paket;
                    $sponsor_level_lable_harga = $getLevelReff->lable_harga;
                } else {
                    $x = $maxRefferalLevel+1;
                }
                
                
            }
            
            
            $getPaketDeviden = DB::table('rebahandapatcuan_paket')->where('deviden', '1')->first();
            if($getPaketDeviden){
                $id_paket = $getPaketDeviden->id;
                
                $getPaketMember = DB::table('rebahandapatcuan_member_paket')->where('id_paket', $id_paket)->where('status','Y')->where('expire_date', '>=', date('Y-m-d'))->get();
                
                foreach($getPaketMember as $row){
                    $getRefferal = DB::table('rb_konsumen as a')
                        ->rightJoin('rebahandapatcuan_member_paket as b', function ($join) {
                            $join->on('b.id_konsumen', '=', 'a.id_konsumen')
                                ->where('b.status', '=', 'Y')
                                ->where('b.expire_date', '>=', date('Y-m-d'))
                                ->where('b.tagihan', '>', 0);
                        })
                        ->where('a.referral_id', '=', $row->id_konsumen)
                        ->select('a.id_konsumen', 'a.nama_lengkap', 'b.tagihan')
                        ->get();
                        
                    $reffDapatDeviden = DB::table('rb_config')->where('field','deviden_banyak_reff')->first();
                    
                    if(count($getRefferal) >= $reffDapatDeviden->value){
                        $dtInsert['id_rekening'] = 0;
                        $dtInsert['id_konsumen'] = $row->id_konsumen;
                        $dtInsert['nominal'] = $deviden;
                        $dtInsert['withdraw_fee'] = 0;
                        $dtInsert['status'] = 'Sukses';
                        $dtInsert['transaksi'] = 'debit';
                        $dtInsert['akun'] = 'deviden';
                        $dtInsert['keterangan'] = 'Deviden Pembelian Paket';
                        $dtInsert['waktu_pendapatan'] = date('Y-m-d H:i:s');
                        $dtInsert['source'] = 'rebahandapatcuan';
                        DB::table('rb_pendapatan_kurir')->insert($dtInsert);
                        
                        
                        $dataPenerimaDeviden = DB::table('rb_konsumen')->where('id_konsumen', $row->id_konsumen)->first();
                        $pesan = 'Halo Kak '.$dataPenerimaDeviden->nama_lengkap.', Selamat Kakak mendapatkan komisi sebesar *Rp.'.number_format($deviden).'* dari transaksi pembelian paket *'.$getPenjualan->nama_paket.'* oleh *'.$getKonsumen->nama_lengkap.'*.

Info lebih lanjut silakan cek di member area rebahandapatcuan.com


*Salam Cuan*';
                        kirim_wa($dataPenerimaDeviden->no_hp, $pesan);
                    }
                }
            }
            
            
        }
        
    }
    
    public function flip_subscribe($email, $kode_transaksi){
        
        $getPaketMember = DB::table('rebahandapatcuan_member_paket')->where('kode_transaksi', $kode_transaksi)->first();
        $getPaket = DB::table('rebahandapatcuan_paket')->where('id', $getPaketMember->id_paket)->first();
        
        $deskripsi = 'Pemnbelian Paket "'.$getPaket->keterangan_paket.'"  Rp.'.number_format($getPaketMember->tagihan);
        $konsumen = DB::table('rb_konsumen')->where('id_konsumen', $getPaketMember->id_konsumen)->first();
        $amount = $getPaketMember->tagihan;
        $uniqID = uniqid();
        
        $secret_key = $this->getConfig('flip_api');
        $secret_key = base64_encode($secret_key."::");
        
        $payloads = [
            "title" => $deskripsi,
            "amount" => $amount,
            "type" => "SINGLE",
            "redirect_url" => "https://rebahandapatcuan.com/member/subsribe_success/flip/".$kode_transaksi,
            "is_address_required" => 0,
            "is_phone_number_required" => 0,
            "sender_name" => $konsumen->nama_lengkap,
            "sender_email" => $email,
            "step" => 2
        ];
        
        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => $this->getConfig('flip_api_link').'v2/pwf/bill',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS => http_build_query($payloads),
          CURLOPT_HTTPHEADER => array(
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic '.$secret_key,
          ),
        ));
        
        $response = curl_exec($curl);
        
        curl_close($curl);
        
        
        $response = json_decode($response, true);
        
        if (isset($response['link_id'])) {
            $linkId = $response['link_id'];
        } else {
            $linkId = '';
        }

        $this->log_payment($kode_transaksi, 'Paket Rebahandapatcuan', 'rebahandapatcuan_member_paket', 'kode_transaksi', $payloads, json_encode($response), $linkId);
        
        return $response;
        
    }
    
    
    public function log_payment($kode_transaksi, $source, $table_database, $field_search, $payload, $response, $id_bill){
        
        $dtInsert['app'] = 'REBAHAN';
        $dtInsert['source'] = $source;
        $dtInsert['table_database'] = $table_database;
        $dtInsert['field_search'] = $field_search;
        $dtInsert['payload'] = json_encode($payload);
        $dtInsert['response'] = json_encode($response);
        $dtInsert['created_at'] = date('Y-m-d H:i:s');
        $dtInsert['id_payment'] = $kode_transaksi;
        $dtInsert['id_bill'] = $id_bill;
        return db::table('payment_response')->insertGetId($dtInsert);
    }
    
    
    public function flipCallback(Request $req){
        $callback_data = json_decode($req->data, true);
        
        
        $dtInsert['app'] = 'CALLBACK_GATEWAY';
        $dtInsert['source'] = 'CALLBACK_GATEWAY';
        $dtInsert['table_database'] = 'payment_response';
        $dtInsert['field_search'] = 'id_payment';
        $dtInsert['payload'] = json_encode($callback_data);
        $dtInsert['response'] = json_encode($callback_data);
        $dtInsert['created_at'] = date('Y-m-d H:i:s');
        $dtInsert['id_payment'] = time().rand(111,999);
        $dtInsert['status'] = $callback_data['status'];
        $dtInsert['id_bill'] = $callback_data['bill_link_id'];
        DB::table('payment_response')->insert($dtInsert);
        
        $id_bill = $callback_data['bill_link_id'];
        $status = $callback_data['status'];
        $cekTransaksi = DB::table('payment_response')->where('id_bill', $id_bill)->whereNot('app','CALLBACK_GATEWAY')->first();
        if($cekTransaksi){
            $getTableTrans = DB::table($cekTransaksi->table_database)->where($cekTransaksi->field_search, $cekTransaksi->id_payment)->first();
            
            if($getTableTrans){
                if($status == 'SUCCESSFUL'){
                    $dtUpdate['status'] = 'SUKSES';
                }
                $this->komisiDonasi($cekTransaksi->id_payment);
                DB::table($cekTransaksi->table_database)->where('id', $getTableTrans->id)->update($dtUpdate);
                
            }
            
        }
    }
    
    
    public function komisiDonasi($kode_transaksi){
        
        $getDonasi = DB::table('donasi_biopori')->where('kode_transaksi',$kode_transaksi)->where('status','!=','SUKSES')->first();
        if(!$getDonasi){
            return;
        }
        $getKonsumen = DB::table('rb_konsumen')->where('id_konsumen',$getDonasi->id_konsumen)->first();
        
        if($getKonsumen->referral_id == ''){
            return;
        }
        
        $komisi = $this->getConfig('komisi_biopori');
        $dtKomisi['id_rekening'] = '0';
        $dtKomisi['id_konsumen'] = $getKonsumen->referral_id;
        $dtKomisi['nominal'] = $komisi*$getDonasi->total_biopori;
        $dtKomisi['withdraw_fee'] = '0';
        $dtKomisi['status'] = 'Sukses';
        $dtKomisi['transaksi'] = 'debit';
        $dtKomisi['keterangan'] = 'Komisi Biopori';
        $dtKomisi['akun'] = 'refferal';
        $dtKomisi['waktu_pendapatan'] = date('Y-m-d H:i:s');
        $dtKomisi['akun'] = 'refferal';
        DB::table('rb_pendapatan_donasi')->insert($dtKomisi);
        
        return;
        
    }
    
    
    public function rebahanVisitorCounter(Request $req){
        $data['reff_visitor'] = $req->reff;
        $data['token'] = $req->token;
        $data['tgl_visitor'] = date('Y-m-d H:i:s');
        
        DB::table('rebahandapatcuan_visitor')->insert($data);
        return true;
    }
    
    public function rebahanVisitorCounterCTA(Request $req){
        $token = $req->token;
        $data['cta_click'] = 1;
        
        return DB::table('rebahandapatcuan_visitor')->where('token',$token)->update($data);
        return true;
    }
    
    
    public function rebahanNotifWa(Request $req){
        $no_hp = $req->no_hp;
        $pesan = $req->pesan;
        kirim_wa($no_hp, $pesan);
    }    


    public function pembagianKomisiKurir($komisi, $id_sopir, $id_order)
    {
        try{
            DB::beginTransaction();
            
            App::setLocale(session()->get('locale'));
            
            $persenSistem = $this->getConfig('fee_kurir_sistem');
            $persenReff = $this->getConfig('fee_kurir_ref');
            $persenKoordinator = $this->getConfig('fee_kurir_koordinator_kota');
            $persenKoordinatorKecamatan = $this->getConfig('fee_kurir_koordinator_kecamatan');
            $persenKasCabang = $this->getConfig('fee_kurir_kas_cabang');
            $persenLainnya = $this->getConfig('fee_kurir_lainnya');
            $persenAgen = $this->getConfig('fee_kurir_agen');
    
            $dtKurir = DB::table('rb_sopir')->where('id_sopir', $id_sopir)->first();
            
            $kecamatanKurir = $dtKurir->kecamatan_id;
            $kotaKurir = $dtKurir->kota_id;
    
            if ($persenSistem > 0) {
                $komisiSistem = $komisi * ($dtKurir->total_komisi/100);
                $dtSistem['id_konsumen'] = 0;
                $dtSistem['amount'] = $komisiSistem;
                $dtSistem['trx_type'] = 'credit';
                $dtSistem['note'] = 'Komisi admin kurir';
                $dtSistem['created_at'] = date('Y-m-d H:i:s');
                DB::table('rb_wallet_users')->insert($dtSistem);
            }
    
            if ($persenReff > 0) {
                $komisiReff = $komisi * ($persenReff/100);
                $kasihAdmin = false;
    
                $cekReff = DB::table('rb_konsumen as a')->select('a.referral_id')->leftJoin('rb_sopir as b', 'b.id_konsumen', 'a.id_konsumen')->where('b.id_sopir', $id_sopir)->first();
                if ($cekReff) {
                    if ($cekReff->referral_id != '') {
                        $dtReff['id_konsumen'] = $cekReff->referral_id;
                        $dtReff['amount'] = $komisiReff;
                        $dtReff['trx_type'] = 'credit';
                        $dtReff['note'] = 'Komisi Refferal Kurir';
                        $dtReff['created_at'] = date('Y-m-d H:i:s');
                        DB::table('rb_wallet_users')->insert($dtReff);
                    } else {
                        $kasihAdmin = true;
                    }
                } else {
                    $kasihAdmin = true;
                }
    
                if ($kasihAdmin) {
                    $dtReff['id_konsumen'] = 0;
                    $dtReff['amount'] = $komisiReff;
                    $dtReff['trx_type'] = 'credit';
                    $dtReff['note'] = 'Komisi Refferal kurir';
                    $dtReff['created_at'] = date('Y-m-d H:i:s');
                    DB::table('rb_wallet_users')->insert($dtReff);
                }
                
            }
    
            if ($persenKoordinator > 0 && $kotaKurir > 0) {
                $komisiKoordinator = $komisi * ($persenKoordinator/100);
                $kasihAdmin = false;
    
                $cekKoordinator = DB::table('rb_sopir')->where('kota_id', $kotaKurir)->where('koordinator_kota', '1')->first();
                if ($cekKoordinator) {
                    $dtKoordinator['id_konsumen'] = $cekKoordinator->id_konsumen;
                    $dtKoordinator['amount'] = $komisiKoordinator;
                    $dtKoordinator['trx_type'] = 'credit';
                    $dtKoordinator['note'] = 'Komisi Koordinator Kurir';
                    $dtKoordinator['created_at'] = date('Y-m-d H:i:s');
                    DB::table('rb_wallet_users')->insert($dtKoordinator);
                        
                } else {
                    $kasihAdmin = true;
                }
    
                if ($kasihAdmin) {
                    $dtKoordinator['id_konsumen'] = 0;
                    $dtKoordinator['amount'] = $komisiKoordinator;
                    $dtKoordinator['trx_type'] = 'credit';
                    $dtKoordinator['note'] = 'Komisi Koordinator Kota kurir';
                    $dtKoordinator['created_at'] = date('Y-m-d H:i:s');
                    DB::table('rb_wallet_users')->insert($dtKoordinator);
                }
                
            }
    
            if ($persenKoordinatorKecamatan > 0 && $kecamatanKurir > 0) {
                $komisiKoordinatorKecamatan = $komisi * ($persenKoordinatorKecamatan/100);
                $kasihAdmin = false;
    
                $cekKoordinator = DB::table('rb_sopir')->where('kecamatan_id', $kecamatanKurir)->where('koordinator_kecamatan', '1')->first();
                if ($cekKoordinator) {
                    $dtKoordinator['id_konsumen'] = $cekKoordinator->id_konsumen;
                    $dtKoordinator['amount'] = $komisiKoordinatorKecamatan;
                    $dtKoordinator['trx_type'] = 'credit';
                    $dtKoordinator['note'] = 'Komisi Koordinator Kecamatan Kurir';
                    $dtKoordinator['created_at'] = date('Y-m-d H:i:s');
                    DB::table('rb_wallet_users')->insert($dtKoordinator);
                        
                } else {
                    $kasihAdmin = true;
                }
    
                if ($kasihAdmin) {
                    $dtKoordinator['id_konsumen'] = 0;
                    $dtKoordinator['amount'] = $komisiKoordinatorKecamatan;
                    $dtKoordinator['trx_type'] = 'credit';
                    $dtKoordinator['note'] = 'Komisi Koordinator kurir';
                    $dtKoordinator['created_at'] = date('Y-m-d H:i:s');
                    DB::table('rb_wallet_users')->insert($dtKoordinator);
                }
                
            }
    
    
            if ($persenKasCabang > 0) {
                $komisiKasCabang = $komisi * ($persenKasCabang/100);
    
                $dtKasCabang['id_konsumen'] = 0;
                $dtKasCabang['amount'] = $komisiKasCabang;
                $dtKasCabang['trx_type'] = 'credit';
                $dtKasCabang['note'] = '';
                $dtKasCabang['created_at'] = date('Y-m-d H:i:s');
                DB::table('rb_wallet_users')->insert($dtKasCabang);
            }
    
    
            if ($persenAgen > 0) {
                $komisiAgen = $komisi * ($persenAgen/100);
                $kasihAdmin = false;
    
                $cek_agen = DB::table('kurir_order')->where('id', $id_order)->where('id_agen', '>', '0')->first();
                if ($cek_agen) {
                    $dtAgen['id_konsumen'] = $cek_agen->id_agen;
                    $dtAgen['amount'] = $komisiAgen;
                    $dtAgen['trx_type'] = 'credit';
                    $dtAgen['note'] = 'Komisi Agen Kurir';
                    $dtAgen['created_at'] = date('Y-m-d H:i:s');
                    DB::table('rb_wallet_users')->insert($dtAgen);
                } else {
                    $kasihAdmin = true;
                }
    
                if ($kasihAdmin) {
                    $dtAgen['id_konsumen'] = 0;
                    $dtAgen['amount'] = $komisiAgen;
                    $dtAgen['trx_type'] = 'credit';
                    $dtAgen['note'] = 'Komisi Agen kurir';
                    $dtAgen['created_at'] = date('Y-m-d H:i:s');
                    DB::table('rb_wallet_users')->insert($dtAgen);
                }
            }

            DB::commit();
            return true;
        } catch (PDOException $e) {
            DB::rollBack();
            return false;
        }
    }
}
