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

    public function cekStatusSopir(Request $req)
    {
        $get_konsumen = DB::table('rb_konsumen')->where('no_hp', $req->no_hp)->first();

        // normalize numeric-zero fields to empty string for clients expecting empty values
        if ($get_konsumen) {
            $this->normalizeKonsumen($get_konsumen);
        }

        if(!$get_konsumen){
            header('Content-Type: application/json');
                return response()->json([
                    'success' => false,
                    'message' => ''
                ]);
        }


        $sopir = DB::table('rb_sopir')->where('id_konsumen', $get_konsumen->id_konsumen)->first();

        if ($sopir) {
            // convert lampiran path to full URL if present
            if (!empty($sopir->lampiran)) {
                $sopir->lampiran = $this->urlForStoragePath($sopir->lampiran);
            }

            if (!empty($sopir->lampiran_stnk)) {
                $sopir->lampiran_stnk = $this->urlForStoragePath($sopir->lampiran_stnk);
            }

            if (!empty($sopir->foto_diri)) {
                $sopir->foto_diri = $this->urlForStoragePath($sopir->foto_diri);
            }

            if ($sopir->id_jenis_kendaraan == 1) {
                $sopir->jenis_kendaraan = 'Motor';
            } elseif ($sopir->id_jenis_kendaraan == 2) {
                $sopir->jenis_kendaraan = 'Mobil';
            }

            $sopir->no_hp = $get_konsumen->no_hp;

            header('Content-Type: application/json');
                return response()->json([
                    'success' => true,
                    'data' => $sopir,
                    'message' => 'Sopir Sudah Terdaftar'
                ]);
        } else {
            header('Content-Type: application/json');
                return response()->json([
                    'success' => false,
                    'message' => 'Sopir Belum Terdaftar'
                ]);
        }
    }

    public function updateKelengkapanData(Request $req)
    {
        
        // Validate required fields
        $validator = \Validator::make($req->all(), [
            'no_hp' => 'required',
            'type_kendaraan' => 'required',
            'merek' => 'required',
            'plat_nomor' => 'required',
            // 'foto_sim' => 'required',
            // 'foto_stnk' => 'required',
            // 'foto_diri' => 'required',
        ]);

        if ($validator->fails()) {
            header('Content-Type: application/json');
            http_response_code(422);
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        // Cari konsumen berdasarkan no_hp
        $konsumen = DB::table('rb_konsumen')->where('no_hp', $req->no_hp)->first();

        if (!$konsumen) {
            header('Content-Type: application/json');
            http_response_code(404);
            return response()->json([
                'success' => false,
                'message' => 'No HP tidak ditemukan di rb_konsumen'
            ], 404);
        }

        $id_konsumen = $konsumen->id_konsumen;

        // Siapkan data untuk rb_sopir
        // Handle foto_sim: allow multipart file upload or base64 string
        $lampiranPath = null;
        $lampiranStnkPath = null;
        $lampiranDiriPath = null;

        try {
            if ($req->hasFile('foto_sim') && $req->file('foto_sim')->isValid()) {
                $file = $req->file('foto_sim');
                $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
                $publicPath = public_path('uploads/sopir');
                if (!is_dir($publicPath)) {
                    mkdir($publicPath, 0755, true);
                }
                // Cek apakah file berhasil dipindahkan
                try {
                    $file->move($publicPath, $filename);
                    $lampiranPath = '/uploads/sopir/' . $filename;
                } catch (\Exception $e) {
                    error_log('Gagal memindahkan file ke ' . $publicPath . '/' . $filename . ' : ' . $e->getMessage());
                }
            } elseif ($req->filled('foto_sim') && !$req->hasFile('foto_sim')) {
                // Only process non-file input: accept a base64 string or a URL/path
                $fotoSim = $req->foto_sim;
                
                // detect data URI
                if (preg_match('/^data:\w+\/[-+.\w]+;base64,/', $fotoSim)) {
                    $parts = explode(',', $fotoSim);
                    $meta = $parts[0];
                    $data = $parts[1] ?? '';
                    $ext = 'png';
                    if (preg_match('/image\/(\w+)/', $meta, $m)) {
                        $ext = $m[1];
                    }
                    $decoded = base64_decode($data);
                    if ($decoded !== false) {
                        $filename = time() . '_foto_sim.' . $ext;
                        $publicPath = public_path('uploads/sopir');
                        if (!is_dir($publicPath)) {
                            mkdir($publicPath, 0755, true);
                        }
                        $fullPath = $publicPath . '/' . $filename;
                        file_put_contents($fullPath, $decoded);
                        $lampiranPath = '/uploads/sopir/' . $filename;
                    }
                } elseif (filter_var($fotoSim, FILTER_VALIDATE_URL) || (strpos($fotoSim, '/uploads/') === 0)) {
                    // if client sent a valid URL or path starting with /uploads/, keep as-is
                    $lampiranPath = $fotoSim;
                }
                // Otherwise, ignore invalid input (don't store temp paths or random strings)
            }

            if ($req->hasFile('foto_stnk') && $req->file('foto_stnk')->isValid()) {
                $file = $req->file('foto_stnk');
                $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
                $publicPath = public_path('uploads/sopir');
                if (!is_dir($publicPath)) {
                    mkdir($publicPath, 0755, true);
                }
                // Cek apakah file berhasil dipindahkan
                try {
                    $file->move($publicPath, $filename);
                    $lampiranStnkPath = '/uploads/sopir/' . $filename;
                } catch (\Exception $e) {
                    error_log('Gagal memindahkan file ke ' . $publicPath . '/' . $filename . ' : ' . $e->getMessage());
                }
            } elseif ($req->filled('foto_stnk') && !$req->hasFile('foto_stnk')) {
                // Only process non-file input: accept a base64 string or a URL/path
                $fotostnk = $req->foto_stnk;
                
                // detect data URI
                if (preg_match('/^data:\w+\/[-+.\w]+;base64,/', $fotostnk)) {
                    $parts = explode(',', $fotostnk);
                    $meta = $parts[0];
                    $data = $parts[1] ?? '';
                    $ext = 'png';
                    if (preg_match('/image\/(\w+)/', $meta, $m)) {
                        $ext = $m[1];
                    }
                    $decoded = base64_decode($data);
                    if ($decoded !== false) {
                        $filename = time() . '_foto_sim.' . $ext;
                        $publicPath = public_path('uploads/sopir');
                        if (!is_dir($publicPath)) {
                            mkdir($publicPath, 0755, true);
                        }
                        $fullPath = $publicPath . '/' . $filename;
                        file_put_contents($fullPath, $decoded);
                        $lampiranStnkPath = '/uploads/sopir/' . $filename;
                    }
                } elseif (filter_var($fotostnk, FILTER_VALIDATE_URL) || (strpos($fotostnk, '/uploads/') === 0)) {
                    // if client sent a valid URL or path starting with /uploads/, keep as-is
                    $lampiranStnkPath = $fotostnk;
                }
                // Otherwise, ignore invalid input (don't store temp paths or random strings)
            }


            if ($req->hasFile('foto_diri') && $req->file('foto_diri')->isValid()) {
                $file = $req->file('foto_diri');
                $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
                $publicPath = public_path('uploads/sopir');
                if (!is_dir($publicPath)) {
                    mkdir($publicPath, 0755, true);
                }
                // Cek apakah file berhasil dipindahkan
                try {
                    $file->move($publicPath, $filename);
                    $lampiranDiriPath = '/uploads/sopir/' . $filename;
                } catch (\Exception $e) {
                    error_log('Gagal memindahkan file ke ' . $publicPath . '/' . $filename . ' : ' . $e->getMessage());
                }
            } elseif ($req->filled('foto_diri') && !$req->hasFile('foto_diri')) {
                // Only process non-file input: accept a base64 string or a URL/path
                $fotoDiri = $req->foto_diri;
                
                // detect data URI
                if (preg_match('/^data:\w+\/[-+.\w]+;base64,/', $fotoDiri)) {
                    $parts = explode(',', $fotoDiri);
                    $meta = $parts[0];
                    $data = $parts[1] ?? '';
                    $ext = 'png';
                    if (preg_match('/image\/(\w+)/', $meta, $m)) {
                        $ext = $m[1];
                    }
                    $decoded = base64_decode($data);
                    if ($decoded !== false) {
                        $filename = time() . '_foto_sim.' . $ext;
                        $publicPath = public_path('uploads/sopir');
                        if (!is_dir($publicPath)) {
                            mkdir($publicPath, 0755, true);
                        }
                        $fullPath = $publicPath . '/' . $filename;
                        file_put_contents($fullPath, $decoded);
                        $lampiranDiriPath = '/uploads/sopir/' . $filename;
                    }
                } elseif (filter_var($fotoDiri, FILTER_VALIDATE_URL) || (strpos($fotoDiri, '/uploads/') === 0)) {
                    // if client sent a valid URL or path starting with /uploads/, keep as-is
                    $lampiranDiriPath = $fotoDiri;
                }
                // Otherwise, ignore invalid input (don't store temp paths or random strings)
            }

        } catch (\Exception $e) {
            // Log error for debugging if needed
            error_log('File upload error: ' . $e->getMessage());
            $lampiranPath = null;
            $lampiranStnkPath = null;
            $lampiranDiriPath = null;
        }

        

        $dataSopir = [
            'id_konsumen' => $id_konsumen,
            'id_jenis_kendaraan' => $req->type_kendaraan,
            'merek' => $req->merek,
            'plat_nomor' => $req->plat_nomor,
            'lampiran' => $lampiranPath,
            'lampiran_stnk' => $lampiranStnkPath,
            'foto_diri' => $lampiranDiriPath,
        ];

        try {
            DB::beginTransaction();

            $sopir = DB::table('rb_sopir')->where('id_konsumen', $id_konsumen)->first();

            if ($sopir) {
                // Update existing
                DB::table('rb_sopir')->where('id_konsumen', $id_konsumen)->update([
                    'id_jenis_kendaraan' => $req->type_kendaraan,
                    'merek' => $req->merek,
                    'plat_nomor' => $req->plat_nomor,
                    'lampiran' => $lampiranPath,
                    'lampiran_stnk' => $lampiranStnkPath,
                    'foto_diri' => $lampiranDiriPath,
                ]);

                DB::commit();

                header('Content-Type: application/json');
                return response()->json([
                    'success' => true,
                    'message' => 'Data kelengkapan sopir berhasil diperbarui',
                    // 'data' => $dataSopir
                ]);
            } else {
                // Insert new record
                DB::table('rb_sopir')->insert($dataSopir);

                DB::commit();

                header('Content-Type: application/json');
                return response()->json([
                    'success' => true,
                    'message' => 'Data kelengkapan sopir berhasil disimpan',
                    // 'data' => $dataSopir
                ]);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            header('Content-Type: application/json');
            http_response_code(500);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }


    public function updateProfileFoto(Request $req)
    {
        
        // Validate required fields
        $validator = \Validator::make($req->all(), [
            'no_hp' => 'required',
            'foto_diri' => 'required',
        ]);

        if ($validator->fails()) {
            header('Content-Type: application/json');
            http_response_code(422);
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        // Cari konsumen berdasarkan no_hp
        $konsumen = DB::table('rb_konsumen')->where('no_hp', $req->no_hp)->first();

        if (!$konsumen) {
            header('Content-Type: application/json');
            http_response_code(404);
            return response()->json([
                'success' => false,
                'message' => 'No HP tidak ditemukan di rb_konsumen'
            ], 404);
        }

        $id_konsumen = $konsumen->id_konsumen;

        $lampiranDiriPath = null;

        try {
            if ($req->hasFile('foto_diri') && $req->file('foto_diri')->isValid()) {
                $file = $req->file('foto_diri');
                $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
                $publicPath = public_path('uploads/sopir');
                if (!is_dir($publicPath)) {
                    mkdir($publicPath, 0755, true);
                }
                // Cek apakah file berhasil dipindahkan
                try {
                    $file->move($publicPath, $filename);
                    $lampiranDiriPath = '/uploads/sopir/' . $filename;
                } catch (\Exception $e) {
                    error_log('Gagal memindahkan file ke ' . $publicPath . '/' . $filename . ' : ' . $e->getMessage());
                }
            } elseif ($req->filled('foto_diri') && !$req->hasFile('foto_diri')) {
                // Only process non-file input: accept a base64 string or a URL/path
                $fotoDiri = $req->foto_diri;
                
                // detect data URI
                if (preg_match('/^data:\w+\/[-+.\w]+;base64,/', $fotoDiri)) {
                    $parts = explode(',', $fotoDiri);
                    $meta = $parts[0];
                    $data = $parts[1] ?? '';
                    $ext = 'png';
                    if (preg_match('/image\/(\w+)/', $meta, $m)) {
                        $ext = $m[1];
                    }
                    $decoded = base64_decode($data);
                    if ($decoded !== false) {
                        $filename = time() . '_foto_sim.' . $ext;
                        $publicPath = public_path('uploads/sopir');
                        if (!is_dir($publicPath)) {
                            mkdir($publicPath, 0755, true);
                        }
                        $fullPath = $publicPath . '/' . $filename;
                        file_put_contents($fullPath, $decoded);
                        $lampiranDiriPath = '/uploads/sopir/' . $filename;
                    }
                } elseif (filter_var($fotoDiri, FILTER_VALIDATE_URL) || (strpos($fotoDiri, '/uploads/') === 0)) {
                    // if client sent a valid URL or path starting with /uploads/, keep as-is
                    $lampiranDiriPath = $fotoDiri;
                }
                // Otherwise, ignore invalid input (don't store temp paths or random strings)
            }

        } catch (\Exception $e) {
            // Log error for debugging if needed
            error_log('File upload error: ' . $e->getMessage());
            $lampiranDiriPath = null;
        }

        

        $dataSopir = [
            'id_konsumen' => $id_konsumen,
            'foto_diri' => $lampiranDiriPath,
        ];

        try {
            DB::beginTransaction();

            $get_konsumen = DB::table('rb_konsumen as a')
            ->select('a.*', 'b.province_name as provinsi', 'c.city_name as kota', 'd.subdistrict_name as kecamatan', 'e.foto_diri', 'e.foto_diri as foto')
            ->leftJoin('tb_ro_provinces as b', 'b.province_id', 'a.provinsi_id')
            ->leftJoin('tb_ro_cities as c', 'c.city_id', 'a.kota_id')
            ->leftJoin('tb_ro_subdistricts as d', 'd.subdistrict_id', 'a.kecamatan_id')
            ->leftJoin('rb_sopir as e', 'a.id_konsumen', 'e.id_konsumen')
            ->where('a.id_konsumen', $id_konsumen)
            ->first();
            // normalize numeric-zero fields to empty string for clients expecting empty values
            if ($get_konsumen) {
                $this->normalizeKonsumen($get_konsumen);
            }
            if ($get_konsumen) {

                // ensure foto is a full URL
                if (!empty($get_konsumen->foto)) {
                    $get_konsumen->foto = $this->urlForStoragePath($get_konsumen->foto);
                }

                if (!empty($get_konsumen->foto_diri)) {
                    $get_konsumen->foto_diri = $this->urlForStoragePath($get_konsumen->foto_diri);
                }

            }

            $sopir = DB::table('rb_sopir')->where('id_konsumen', $id_konsumen)->first();

            if ($sopir) {
                // Update existing
                DB::table('rb_sopir')->where('id_konsumen', $id_konsumen)->update([
                    'foto_diri' => $lampiranDiriPath,
                ]);

                DB::commit();

                header('Content-Type: application/json');
                return response()->json([
                    'success' => true,
                    'message' => 'Data kelengkapan sopir berhasil diperbarui',
                    'data' => $get_konsumen
                ]);
            } else {
                // Insert new record
                DB::table('rb_sopir')->insert($dataSopir);

                DB::commit();

                header('Content-Type: application/json');
                return response()->json([
                    'success' => true,
                    'message' => 'Data kelengkapan sopir berhasil disimpan',
                    'data' => $get_konsumen
                ]);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            header('Content-Type: application/json');
            http_response_code(500);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function cekNoHp(Request $req)
    {
        $get_konsumen = DB::table('rb_konsumen as a')
        ->select('a.*', 'b.province_name as provinsi', 'c.city_name as kota', 'd.subdistrict_name as kecamatan', 'e.foto_diri', 'e.foto_diri as foto')
        ->leftJoin('tb_ro_provinces as b', 'b.province_id', 'a.provinsi_id')
        ->leftJoin('tb_ro_cities as c', 'c.city_id', 'a.kota_id')
        ->leftJoin('tb_ro_subdistricts as d', 'd.subdistrict_id', 'a.kecamatan_id')
        ->leftJoin('rb_sopir as e', 'a.id_konsumen', 'e.id_konsumen')
        ->where('a.no_hp', $req->no_hp)
        ->first();
        // normalize numeric-zero fields to empty string for clients expecting empty values
        if ($get_konsumen) {
            $this->normalizeKonsumen($get_konsumen);
        }
        if ($get_konsumen) {

            // ensure foto is a full URL
            if (!empty($get_konsumen->foto)) {
                $get_konsumen->foto = $this->urlForStoragePath($get_konsumen->foto);
            }

            if (!empty($get_konsumen->foto_diri)) {
                $get_konsumen->foto_diri = $this->urlForStoragePath($get_konsumen->foto_diri);
            }

            $sopir = DB::table('rb_sopir')->where('id_konsumen', $get_konsumen->id_konsumen)->first();
            if ($sopir && !empty($sopir->lampiran)) {
                $sopir->lampiran = $this->urlForStoragePath($sopir->lampiran);
            }
            if ($sopir) {
                header('Content-Type: application/json');
                return response()->json([
                    'success' => false,
                    'registered' => true,
                    'data' => $get_konsumen,
                    'message' => 'No HP Sudah Terdaftar Sebagai Kurir'
                ]);
            } else {
                header('Content-Type: application/json');
                return response()->json([
                    'success' => true,
                    'data' => $get_konsumen,
                    'registered' => true,
                    'message' => 'No HP Sudah Terdaftar'
                ]);
            }
            
        } else {
            header('Content-Type: application/json');
            return response()->json([
                'success' => true,
                'registered' => false,
                'message' => 'No HP Belum Terdaftar'
            ]);
        }
        
    }



    public function getProvinsi(Request $req)
    {
        $data = DB::table('tb_ro_provinces')->orderBy('province_name')->get();

        header('Content-Type: application/json');
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Data Provinsi Berhasil Dimuat'
        ]);
    }


    public function getKota(Request $req)
    {
        $data = DB::table('tb_ro_cities')->orderBy('city_name');

        if ($req->provinsi_id) {
            $data = $data->where('province_id', $req->provinsi_id);
        }

        $data = $data->get();

        header('Content-Type: application/json');
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Data Kota Berhasil Dimuat'
        ]);
    }


    public function getKecamatan(Request $req)
    {
        $data = DB::table('tb_ro_subdistricts')->orderBy('subdistrict_name');

        if ($req->kota_id) {
            $data = $data->where('city_id', $req->kota_id);
        }

        $data = $data->get();

        header('Content-Type: application/json');
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Data Kecamatan Berhasil Dimuat'
        ]);
    }

    public function kirimOtpRegister(Request $req){
        
        if($req->otp){
            $otp = $req->otp;
        } else {
            $newTime = date('Y-m-d H:i:s', strtotime('+1 minutes', time()));
            $otp = rand(000000, 999999);
            $dt_update['no_hp'] = $req->no_hp;
            $dt_update['otp'] = $otp;
            $dt_update['otp_expired'] = $newTime;
            DB::table('otp_register')->insert($dt_update);
        }
        
        header('Content-Type: application/json');
        return response()->json([
            'success' => true,
            'message' => $otp
        ]);
        
    }

    public function submitDataRegister(Request $req)
    {
        // Validasi field minimal
        $validator = \Validator::make($req->all(), [
            'no_hp' => 'required',
            'nama_lengkap' => 'required',
            'email' => 'required|email',
            'jenis_kelamin' => 'required',
            'tanggal_lahir' => 'required',
            'tempat_lahir' => 'required',
            'provinsi_id' => 'required',
            'kota_id' => 'required',
            'kecamatan_id' => 'required',
            'alamat' => 'required',
            'otp' => 'required',
        ]);

        if ($validator->fails()) {
            header('Content-Type: application/json');
            http_response_code(422);
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        // Cek OTP di tabel otp_register (no_hp, otp, otp_expired)
        $otpRow = DB::table('otp_register')
            ->where('no_hp', $req->no_hp)
            ->where('otp', $req->otp)
            ->first();

        if (!$otpRow) {
            header('Content-Type: application/json');
            http_response_code(404);
            return response()->json([
                'success' => false,
                'message' => 'OTP tidak ditemukan'
            ], 404);
        }

        // Cek expired
        if (!empty($otpRow->otp_expired)) {
            $now = date('Y-m-d H:i:s');
            if (strtotime($otpRow->otp_expired) < strtotime($now)) {
                header('Content-Type: application/json');
                http_response_code(400);
                return response()->json([
                    'success' => false,
                    'message' => 'OTP sudah kadaluarsa'
                ], 400);
            }
        }

        DB::beginTransaction();
        try {
            // Cek apakah no_hp sudah terdaftar di rb_konsumen
            $konsumen = DB::table('rb_konsumen')->where('no_hp', $req->no_hp)->first();

            if (!$konsumen) {
                // Insert ke rb_konsumen
                $dtKonsumen = [
                    'username' => $req->no_hp,
                    'password' => Hash::make(rand(10000000, 99999999)),
                    'nama_lengkap' => $req->nama_lengkap,
                    'email' => $req->email,
                    'jenis_kelamin' => $req->jenis_kelamin,
                    'tanggal_lahir' => date('Y-m-d', strtotime($req->tanggal_lahir)),
                    'tempat_lahir' => $req->tempat_lahir,
                    'alamat_lengkap' => $req->alamat,
                    'kecamatan_id' => $req->kecamatan_id,
                    'kota_id' => $req->kota_id,
                    'provinsi_id' => $req->provinsi_id,
                    'no_hp' => $req->no_hp,
                    'foto' => '-',
                    'tanggal_daftar' => date('Y-m-d'),
                    'token' => 'N',
                    'referral_id' => null,
                    'verifikasi' => 'Y',
                ];

                $id_konsumen = DB::table('rb_konsumen')->insertGetId($dtKonsumen);
                $konsumen = DB::table('rb_konsumen')->where('id_konsumen', $id_konsumen)->first();
            }

            // Pastikan ada rb_sopir untuk id_konsumen ini
            $sopir = DB::table('rb_sopir')->where('id_konsumen', $konsumen->id_konsumen)->first();
            if (!$sopir) {
                $dtSopir = [
                    'id_konsumen' => $konsumen->id_konsumen,
                    'kecamatan_id' => $req->kecamatan_id,
                    'kota_id' => $req->kota_id,
                    'provinsi_id' => $req->provinsi_id,
                    'aktif' => 'N',
                    'id_jenis_kendaraan' => '0',
                    'plat_nomor' => '',
                    'merek' => '',
                    'lainnya' => '',
                ];

                DB::table('rb_sopir')->insert($dtSopir);
            } else {
                header('Content-Type: application/json');
                return response()->json([
                    'success' => false,
                    'message' => 'Registrasi gagal, sudah terdaftar sebagai kurir',
                    'data' => [
                        'konsumen' => $konsumen
                    ]
                ]);
            }

            // Optionally mark otp_register as used (delete or update)
            DB::table('otp_register')->where('no_hp', $req->no_hp)->delete();


            $token = Hash::make(date('YmdHi').rand(0000,9999));
            $dtUpdateOtp['otp_expired'] = (string)date('Y-m-d H:i:s');
            $dtUpdateOtp['remember_token'] = (string)$token;
            
            DB::table('rb_konsumen')->where('no_hp', $req->no_hp)->update($dtUpdateOtp);

            http_response_code(200);
            $dt_result['token'] = $token;
            $dt_result['nama_lengkap'] = $konsumen->nama_lengkap;
            $dt_result['email'] = $konsumen->email;
            $dt_result['no_hp'] = $konsumen->no_hp;
            $dt_result['agen'] = 0;
            $dt_result['koordinator_kota'] = 0;
            $dt_result['koordinator_kecamatan'] = 0;
            $dt_result['level'] = 'kurir';
            


            DB::commit();

            header('Content-Type: application/json');
            return response()->json([
                'success' => true,
                'message' => 'Registrasi sukses, silahkan login',
                'data' => [
                    'konsumen' => $konsumen,
                    'token' => $token,
                    'user_login' => $dt_result
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            header('Content-Type: application/json');
            http_response_code(500);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }

    }

    /**
     * Update data konsumen by id_konsumen
     * Expects request fields similar to the $dtKonsumen array used on register.
     */
    public function updateKonsumen(Request $req)
    {
        $validator = \Validator::make($req->all(), [
            'id_konsumen' => 'required|integer',
            'no_hp' => 'required',
            'nama_lengkap' => 'required',
            'email' => 'required|email',
            'jenis_kelamin' => 'required',
            'tanggal_lahir' => 'required',
            'tempat_lahir' => 'required',
            'provinsi_id' => 'required',
            'kota_id' => 'required',
            'kecamatan_id' => 'required',
            'alamat' => 'required',
        ]);

        if ($validator->fails()) {
            header('Content-Type: application/json');
            http_response_code(422);
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $id = $req->id_konsumen;

        $existing = DB::table('rb_konsumen')->where('id_konsumen', $id)->first();
        if (!$existing) {
            header('Content-Type: application/json');
            http_response_code(404);
            return response()->json([
                'success' => false,
                'message' => 'Data konsumen tidak ditemukan'
            ], 404);
        }

        // Build update array (follow provided structure but avoid overwriting password unless requested)
        $dtKonsumen = [
            'username' => $req->no_hp,
            'nama_lengkap' => $req->nama_lengkap,
            'email' => $req->email,
            'jenis_kelamin' => $req->jenis_kelamin,
            'tanggal_lahir' => date('Y-m-d', strtotime($req->tanggal_lahir)),
            'tempat_lahir' => $req->tempat_lahir,
            'alamat_lengkap' => $req->alamat,
            'kecamatan_id' => $req->kecamatan_id,
            'kota_id' => $req->kota_id,
            'provinsi_id' => $req->provinsi_id,
            'no_hp' => $req->no_hp,
            'foto' => $req->filled('foto') ? $req->foto : ($existing->foto ?? '-'),
            'token' => $req->filled('token') ? $req->token : ($existing->token ?? 'N'),
            'referral_id' => $req->filled('referral_id') ? $req->referral_id : ($existing->referral_id ?? null),
            'verifikasi' => $req->filled('verifikasi') ? $req->verifikasi : ($existing->verifikasi ?? 'Y'),
        ];

        // Optional: update password if provided or explicitly request reset_password=1
        if ($req->filled('password')) {
            $dtKonsumen['password'] = Hash::make($req->password);
        } elseif ($req->filled('reset_password') && $req->reset_password == '1') {
            $dtKonsumen['password'] = Hash::make(rand(10000000, 99999999));
        }

        // Optional: allow overriding tanggal_daftar only when provided
        if ($req->filled('tanggal_daftar')) {
            $dtKonsumen['tanggal_daftar'] = date('Y-m-d', strtotime($req->tanggal_daftar));
        }

        try {
            DB::beginTransaction();

            DB::table('rb_konsumen')->where('id_konsumen', $id)->update($dtKonsumen);

            DB::commit();

            header('Content-Type: application/json');
            return response()->json([
                'success' => true,
                'message' => 'Data konsumen berhasil diperbarui',
                'data' => $dtKonsumen
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            header('Content-Type: application/json');
            http_response_code(500);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }


    public function deleteKonsumen(Request $req)
    {
        $validator = \Validator::make($req->all(), [
            'id_konsumen' => 'required|integer',
            'no_hp' => 'required',
        ]);

        if ($validator->fails()) {
            header('Content-Type: application/json');
            http_response_code(422);
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $id = $req->id_konsumen;

        $existing = DB::table('rb_konsumen')->where('id_konsumen', $id)->first();
        if (!$existing) {
            header('Content-Type: application/json');
            http_response_code(404);
            return response()->json([
                'success' => false,
                'message' => 'Data konsumen tidak ditemukan'
            ], 404);
        }

        // Build update array (follow provided structure but avoid overwriting password unless requested)
        $dtKonsumen = [
            'referral_id' => null,
        ];


        try {
            DB::beginTransaction();

            DB::table('rb_konsumen')->where('id_konsumen', $id)->update($dtKonsumen);

            DB::commit();

            header('Content-Type: application/json');
            return response()->json([
                'success' => true,
                'message' => 'Data konsumen berhasil diperbarui',
                'data' => $dtKonsumen
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            header('Content-Type: application/json');
            http_response_code(500);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }


    public function updateKonsumenSimple(Request $req)
    {
        $validator = \Validator::make($req->all(), [
            'id_konsumen' => 'required|integer',
            'no_hp' => 'required',
            'nama_lengkap' => 'required',
            // 'email' => 'required|email',
            // 'jenis_kelamin' => 'required',
            // 'tanggal_lahir' => 'required',
            // 'tempat_lahir' => 'required',
            // 'provinsi_id' => 'required',
            // 'kota_id' => 'required',
            // 'kecamatan_id' => 'required',
            'alamat' => 'required',
        ]);

        if ($validator->fails()) {
            header('Content-Type: application/json');
            http_response_code(422);
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $id = $req->id_konsumen;

        $existing = DB::table('rb_konsumen')->where('id_konsumen', $id)->first();
        if (!$existing) {
            header('Content-Type: application/json');
            http_response_code(404);
            return response()->json([
                'success' => false,
                'message' => 'Data konsumen tidak ditemukan'
            ], 404);
        }

        // Build update array (follow provided structure but avoid overwriting password unless requested)
        $dtKonsumen = [
            'username' => $req->no_hp,
            'nama_lengkap' => $req->nama_lengkap,
            // 'email' => $req->email,
            // 'jenis_kelamin' => $req->jenis_kelamin,
            // 'tanggal_lahir' => date('Y-m-d', strtotime($req->tanggal_lahir)),
            // 'tempat_lahir' => $req->tempat_lahir,
            'alamat_lengkap' => $req->alamat,
            // 'kecamatan_id' => $req->kecamatan_id,
            // 'kota_id' => $req->kota_id,
            // 'provinsi_id' => $req->provinsi_id,
            'no_hp' => $req->no_hp,
            // 'foto' => $req->filled('foto') ? $req->foto : ($existing->foto ?? '-'),
            // 'token' => $req->filled('token') ? $req->token : ($existing->token ?? 'N'),
            // 'referral_id' => $req->filled('referral_id') ? $req->referral_id : ($existing->referral_id ?? null),
            // 'verifikasi' => $req->filled('verifikasi') ? $req->verifikasi : ($existing->verifikasi ?? 'Y'),
        ];

        // Optional: update password if provided or explicitly request reset_password=1
        if ($req->filled('password')) {
            $dtKonsumen['password'] = Hash::make($req->password);
        } elseif ($req->filled('reset_password') && $req->reset_password == '1') {
            $dtKonsumen['password'] = Hash::make(rand(10000000, 99999999));
        }

        // Optional: allow overriding tanggal_daftar only when provided
        if ($req->filled('tanggal_daftar')) {
            $dtKonsumen['tanggal_daftar'] = date('Y-m-d', strtotime($req->tanggal_daftar));
        }

        try {
            DB::beginTransaction();

            DB::table('rb_konsumen')->where('id_konsumen', $id)->update($dtKonsumen);

            DB::commit();

            header('Content-Type: application/json');
            return response()->json([
                'success' => true,
                'message' => 'Data konsumen berhasil diperbarui',
                'data' => $dtKonsumen
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            header('Content-Type: application/json');
            http_response_code(500);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
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

    /**
     * Return available courier services for frontend
     *
     * Response shape:
     * {
     *   ride: { key, name, description },
     *   send: { ... },
     *   food: { ... },
     *   shop: { ... }
     * }
     */
    public function layananKurir(Request $req)
    {
        $services = [
            'ride' => [
                'key' => 'RIDE',
                'name' => 'Ride',
                'description' => 'Layanan antar penumpang cepat dan aman',
            ],
            'send' => [
                'key' => 'SEND',
                'name' => 'Send',
                'description' => 'Kirim barang/ paket antar lokasi',
            ],
            'food' => [
                'key' => 'FOOD',
                'name' => 'Food',
                'description' => 'Pesan dan antar makanan dari restoran',
            ],
            'shop' => [
                'key' => 'SHOP',
                'name' => 'Shop',
                'description' => 'Belanja dan antar barang dari toko/reseller',
            ],
        ];

        header('Content-Type: application/json');
        return response()->json([
            'success' => true,
            'data' => $services,
            'message' => 'Layanan kurir berhasil diambil'
        ]);
    }


    public function notifUpdateOrder(Request $req)
    {
        $token = $req->device_token;
        $pesan = $req->pesan;
        $no_hp = $req->no_hp;
        
        notifOrderUpdate($no_hp, $pesan);
        notifOrderUpdate($token, $pesan);
    }

    /**
     * Return list of agen (drivers who are agents)
     *
     * Uses query specified by the user:
     * DB::table('rb_sopir as a')->select('b.id_konsumen', 'b.nama_lengkap')
     *     ->leftJoin('rb_konsumen as b', 'b.id_konsumen', 'a.id_konsumen')
     *     ->where('a.agen', '1')->where('a.aktif', 'Y')->orderBy('b.nama_lengkap')->get()
     */
    public function getAgens(Request $req)
    {
        $agens = DB::table('rb_sopir as a')
            ->select('b.id_konsumen', 'b.nama_lengkap')
            ->rightJoin('rb_konsumen as b', 'b.id_konsumen', 'a.id_konsumen')
            ->leftJoin('rb_konsumen as c', 'c.kota_id', 'b.kota_id')
            ->where('a.agen', '1')
            ->where('a.aktif', 'Y')
            ->where('c.no_hp', $req->no_hp)
            ->orderBy('b.nama_lengkap')
            ->get();

        header('Content-Type: application/json');
        return response()->json([
            'success' => true,
            'data' => $agens,
            'message' => 'Daftar agen berhasil diambil'
        ]);
    }

    /**
     * Return list of pelanggan (customers)
     * 
     * Request params:
     * - search: string (optional) - search by nama_lengkap or no_hp
     * 
     * Response:
     * {
     *   success: true,
     *   data: [{id_konsumen, nama_lengkap, no_hp}, ...],
     *   message: "..."
     * }
     */
    public function getPelanggan(Request $req)
    {
        $query = DB::table('rb_konsumen as b')
            ->select('b.id_konsumen', 'b.nama_lengkap', 'b.no_hp');

        // Filter pencarian berdasarkan nama_lengkap atau no_hp
        $search = $req->input('search');
        if (!empty($search) && $search != '') {
            $query->where(function($q) use ($search) {
                $q->where('b.nama_lengkap', 'like', '%' . $search . '%')
                  ->orWhere('b.no_hp', 'like', '%' . $search . '%');
            });
        }

        $pelanggan = $query->orderBy('b.nama_lengkap')->get();

        // convert foto paths to full URLs when present
        $pelanggan->transform(function($item) {
            if (!empty($item->foto)) {
                $item->foto = $this->urlForStoragePath($item->foto);
            }
            return $item;
        });

        header('Content-Type: application/json');
        return response()->json([
            'success' => true,
            'data' => $pelanggan,
            'message' => 'Daftar pelanggan berhasil diambil',
            'total' => $pelanggan->count()
        ]);
    }

    /**
     * Convert a storage path (like /storage/uploads/...) or relative path to a full URL
     * 
     * Handles paths stored as:
     * - /storage/uploads/sopir/file.jpg (old database format - from storage/app/public)
     * - /uploads/sopir/file.jpg (new database format - from public/uploads)
     * - storage/uploads/sopir/file.jpg (without leading slash)
     * 
     * Returns URL accessible via web:
     * - https://api.satutoko.my.id/uploads/sopir/file.jpg
     */
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

    /**
     * Normalize konsumen object fields where the database stores 0 for empty foreign keys.
     * Converts 0 or '0' to an empty string for kota_id, kecamatan_id, and to '1' for provinsi_id.
     *
     * @param object $konsumen - passed by reference (stdClass from DB::first())
     */
    private function normalizeKonsumen(&$konsumen)
    {
        $fields = ['provinsi_id', 'kota_id', 'kecamatan_id'];
        foreach ($fields as $f) {
            if (property_exists($konsumen, $f)) {
                if ($konsumen->$f === 0 || $konsumen->$f === '0') {
                    if ($f === 'provinsi_id') {
                        $konsumen->$f = '1';
                    } else {
                        $konsumen->$f = '';
                    }
                }
            }
        }
    }

    /**
     * Clean phone number from frontend to format starting with 08 and only digits.
     * - remove all non-digit characters
     * - if starts with 62 replace with 0
     * - if starts with +62 replace with 0
     * - ensure it starts with 08; if not and starts with 8, prefix 0
     */
    private function cleanPhoneNumber($phone)
    {
        if (empty($phone)) return $phone;
        // remove everything that's not a digit
        $digits = preg_replace('/[^0-9]/', '', (string)$phone);

        // replace leading +62 or 62 with 0
        if (strpos($digits, '62') === 0) {
            $digits = '0' . substr($digits, 2);
        }

        // if starts with 8 (e.g. 81234...), prefix 0 => 081234...
        if (strpos($digits, '8') === 0) {
            $digits = '0' . $digits;
        }

        return $digits;
    }



    /**
     * Add pelanggan minimal to rb_konsumen
     * Expects request contains: nama_lengkap, no_hp, alamat_lengkap
     * Returns JSON response similar to other methods
     */
    public function addDownline(Request $req)
    {
        $validator = \Validator::make($req->all(), [
            'nama_lengkap' => 'required',
            'no_hp' => 'required',
            'alamat_lengkap' => 'required',
            'referral_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            header('Content-Type: application/json');
            http_response_code(422);
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        // Normalize phone according to rules
        $cleanNoHp = $this->cleanPhoneNumber($req->no_hp);

        try {
            DB::beginTransaction();

            // Check if already exists
            $existing = DB::table('rb_konsumen')->where('no_hp', $cleanNoHp)->first();
            if ($existing) {
                DB::rollBack();
                header('Content-Type: application/json');
                return response()->json([
                    'success' => false,
                    'message' => 'No HP sudah terdaftar',
                    'data' => $existing
                ], 409);
            }

            $dtKonsumen = [
                'username' => $cleanNoHp,
                // generate a random password and hash it
                'password' => Hash::make(rand(10000000, 99999999)),
                'nama_lengkap' => $req->nama_lengkap,
                'email' => '-',
                'jenis_kelamin' => 'Laki-laki',
                'tanggal_lahir' => date('Y-m-d'),
                'tempat_lahir' => '-',
                'alamat_lengkap' => $req->alamat_lengkap,
                'kecamatan_id' => '0',
                'kota_id' => '0',
                'provinsi_id' => '0',
                'no_hp' => $cleanNoHp,
                'foto' => '-',
                'tanggal_daftar' => date('Y-m-d'),
                'token' => 'N',
                'referral_id' => $req->referral_id,
                'verifikasi' => 'N',
            ];

            $id = DB::table('rb_konsumen')->insertGetId($dtKonsumen);

            DB::commit();

            $konsumen = DB::table('rb_konsumen')->where('id_konsumen', $id)->first();

            header('Content-Type: application/json');
            return response()->json([
                'success' => true,
                'message' => 'Pelanggan berhasil ditambahkan',
                'data' => $konsumen
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            header('Content-Type: application/json');
            http_response_code(500);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getBalance(Request $req)
    {
        $this->getPendingWithdrawRequestsOlderThan7Days();

        $result = DB::table('rb_wallet_users as a')
            ->leftJoin('rb_konsumen as b', 'b.id_konsumen', '=', 'a.id_konsumen')
            ->where('b.no_hp', $req->no_hp)
            ->selectRaw('
                SUM(CASE 
                    WHEN a.trx_type = ? THEN a.amount 
                    WHEN a.trx_type = ? THEN -a.amount 
                    ELSE 0 
                END) as saldo_akhir
            ', ['credit', 'debit'])
            ->first();

        $resultRequest = DB::table('rb_wallet_requests as a')
            ->leftJoin('rb_konsumen as b', 'b.id_konsumen', '=', 'a.id_konsumen')
            ->where('b.no_hp', $req->no_hp)
            ->selectRaw('
                SUM(CASE 
                    WHEN a.req_type = ? THEN -a.amount 
                    ELSE 0 
                END) as saldo_akhir
            ', ['withdraw'])
            ->where('a.status', 'pending')
            ->first();

        $saldo_request = $resultRequest->saldo_akhir ?? 0;

        $saldo = $result->saldo_akhir ?? 0;

        header('Content-Type: application/json');
        http_response_code(200);
        exit(json_encode(['Message' => 'Get Balance Sukses', 'balance' => $saldo+$saldo_request]));
    }


    // Endpoint khusus untuk pie chart jenis order (harian)
    public function getJenisOrderDaily(Request $request)
    {
        $no_hp = $request->input('no_hp');
        $date = $request->input('date', date('Y-m-d')); // Default hari ini
        
        $jenisOrder = DB::table('kurir_order as a')
            ->select([
                'a.jenis_layanan',
                DB::raw('COUNT(*) as total_order'),
                DB::raw('SUM(a.tarif) as total_pendapatan'),
            ])
            ->leftJoin('rb_sopir as b', 'b.id_sopir', '=', 'a.id_sopir')
            ->leftJoin('rb_konsumen as c', 'c.id_konsumen', '=', 'b.id_konsumen')
            ->where('a.status', 'FINISH')
            ->where('c.no_hp', $no_hp)
            ->whereDate('a.tanggal_order', $date)
            ->whereNotNull('a.jenis_layanan')
            ->where('a.jenis_layanan', '!=', '')
            ->groupBy('a.jenis_layanan')
            ->orderBy('total_order', 'desc')
            ->get();
        
        // Format data untuk pie chart
        $formatted = $this->formatJenisOrderForPieChart($jenisOrder);
        
        header('Content-Type: application/json');
        return response()->json([
            'success' => true,
            'data' => $formatted,
            'message' => 'Data jenis order berhasil diambil'
        ]);
    }
    
    // Endpoint khusus untuk pie chart jenis order (bulanan)
    public function getJenisOrderMonthly(Request $request)
    {
        $no_hp = $request->input('no_hp');
        $year = $request->input('year', date('Y'));
        $month = $request->input('month', date('m'));
        
        $jenisOrder = DB::table('kurir_order as a')
            ->select([
                'a.jenis_layanan',
                DB::raw('COUNT(*) as total_order'),
                DB::raw('SUM(a.tarif) as total_pendapatan'),
            ])
            ->leftJoin('rb_sopir as b', 'b.id_sopir', '=', 'a.id_sopir')
            ->leftJoin('rb_konsumen as c', 'c.id_konsumen', '=', 'b.id_konsumen')
            ->where('a.status', 'FINISH')
            ->where('c.no_hp', $no_hp)
            ->whereYear('a.tanggal_order', $year)
            ->whereMonth('a.tanggal_order', $month)
            ->whereNotNull('a.jenis_layanan')
            ->where('a.jenis_layanan', '!=', '')
            ->groupBy('a.jenis_layanan')
            ->orderBy('total_order', 'desc')
            ->get();
        
        // Format data untuk pie chart
        $formatted = $this->formatJenisOrderForPieChart($jenisOrder);
        
        header('Content-Type: application/json');
        return response()->json([
            'success' => true,
            'data' => $formatted,
            'message' => 'Data jenis order berhasil diambil'
        ]);
    }
    
    // Format data untuk pie chart dengan warna yang sesuai
    private function formatJenisOrderForPieChart($jenisOrder)
    {
        // Mapping warna berdasarkan jenis layanan
        $colors = [
            'send' => '#0d6efd',
            'food' => '#fd7e14',
            'ride' => '#20c997',
            'shop' => '#6f42c1',
        ];
        
        $formatted = [];
        
        foreach ($jenisOrder as $item) {
            $jenis = strtolower(trim($item->jenis_layanan));
            $color = $colors[$jenis] ?? '#6c757d'; // Default gray jika jenis tidak dikenali
            
            $formatted[] = [
                'name' => ucfirst($jenis),
                'orders' => (int)$item->total_order,
                'pendapatan' => (int)$item->total_pendapatan,
                'color' => $color,
                'legendFontColor' => '#6c757d',
                'legendFontSize' => 12,
            ];
        }
        
        return $formatted;
    }
    
    // Endpoint gabungan (harian) - sudah ada, tambahkan pie chart
    public function getOrdersDaily(Request $request)
    {
        $no_hp = $request->input('no_hp');
        $date = $request->input('date', date('Y-m-d'));
        
        // Data untuk bar chart (hourly)
        $ordersHourly = $this->getOrders($no_hp, 'daily', $date);
        
        // Data untuk pie chart jenis order
        $jenisOrder = DB::table('kurir_order as a')
            ->select([
                'a.jenis_layanan',
                DB::raw('COUNT(*) as total_order'),
                DB::raw('SUM(a.tarif) as total_pendapatan'),
            ])
            ->leftJoin('rb_sopir as b', 'b.id_sopir', '=', 'a.id_sopir')
            ->leftJoin('rb_konsumen as c', 'c.id_konsumen', '=', 'b.id_konsumen')
            ->where('a.status', 'FINISH')
            ->where('c.no_hp', $no_hp)
            ->whereDate('a.tanggal_order', $date)
            ->whereNotNull('a.jenis_layanan')
            ->where('a.jenis_layanan', '!=', '')
            ->groupBy('a.jenis_layanan')
            ->get();
        
        // Format untuk chart
        $labels = [];
        $dataOrders = [];
        $dataPendapatan = [];
        
        for ($i = 0; $i < 24; $i++) {
            $jam = sprintf('%02d:00', $i);
            $labels[] = $jam;
            
            $found = $ordersHourly->firstWhere('jam', $i);
            $dataOrders[] = $found ? (int)$found->total_order : 0;
            $dataPendapatan[] = $found ? (int)$found->total_pendapatan : 0;
        }
        
        // Format data pie chart
        $jenisOrderFormatted = $this->formatJenisOrderForPieChart($jenisOrder);
        
        header('Content-Type: application/json');
        return response()->json([
            'success' => true,
            'data' => [
                'orders' => [
                    'labels' => $labels,
                    'datasets' => [['data' => $dataOrders]]
                ],
                'pendapatan' => [
                    'labels' => $labels,
                    'datasets' => [['data' => $dataPendapatan]]
                ],
                'jenis_order' => $jenisOrderFormatted
            ],
            'date' => $date
        ]);
    }

    // Endpoint untuk data bulanan
    public function getOrdersMonthly(Request $request)
    {
        $no_hp = $request->input('no_hp');
        $year = $request->input('year', date('Y'));
        $month = $request->input('month', date('m'));
        
        // Data untuk bar chart (daily in month)
        $ordersDaily = $this->getOrders($no_hp, 'monthly', null, $year, $month);
        
        // Data untuk pie chart jenis order (full month)
        $firstDay = sprintf('%04d-%02d-01', $year, $month);
        $lastDay = date('Y-m-t', strtotime($firstDay));
        $jenisOrder = $this->getJenisOrderRange($no_hp, $firstDay, $lastDay);
        
        // Format untuk chart
        $labels = [];
        $dataOrders = [];
        $dataPendapatan = [];
        
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $labels[] = (string)$day;
            
            $tanggal = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $found = $ordersDaily->firstWhere('tanggal', $tanggal);
            
            $dataOrders[] = $found ? (int)$found->total_order : 0;
            $dataPendapatan[] = $found ? (int)$found->total_pendapatan : 0;
        }
        
        // Format data pie chart
        $jenisOrderFormatted = $this->formatJenisOrderForPieChart($jenisOrder);
        
        header('Content-Type: application/json');
        return response()->json([
            'success' => true,
            'data' => [
                'orders' => [
                    'labels' => $labels,
                    'datasets' => [['data' => $dataOrders]]
                ],
                'pendapatan' => [
                    'labels' => $labels,
                    'datasets' => [['data' => $dataPendapatan]]
                ],
                'jenis_order' => $jenisOrderFormatted
            ]
        ]);
    }
    
    // Helper method untuk get orders hourly/monthly
    private function getOrders($no_hp, $mode, $date = null, $year = null, $month = null)
    {
        $query = DB::table('kurir_order as a')
            ->leftJoin('rb_sopir as b', 'b.id_sopir', '=', 'a.id_sopir')
            ->leftJoin('rb_konsumen as c', 'c.id_konsumen', '=', 'b.id_konsumen')
            ->where('a.status', 'FINISH')
            ->where('c.no_hp', $no_hp);
        
        if ($mode === 'daily' || $mode === 'hourly') {
            $query->select([
                DB::raw('HOUR(a.tanggal_order) as jam'),
                DB::raw('COUNT(*) as total_order'),
                DB::raw('SUM(a.tarif) as total_pendapatan'),
            ])
            ->whereDate('a.tanggal_order', $date)
            ->groupBy(DB::raw('HOUR(a.tanggal_order)'))
            ->orderBy('jam', 'asc');
            
        } elseif ($mode === 'monthly') {
            $query->select([
                DB::raw('DATE(a.tanggal_order) as tanggal'),
                DB::raw('COUNT(*) as total_order'),
                DB::raw('SUM(a.tarif) as total_pendapatan'),
            ])
            ->whereYear('a.tanggal_order', $year)
            ->whereMonth('a.tanggal_order', $month)
            ->groupBy(DB::raw('DATE(a.tanggal_order)'))
            ->orderBy('tanggal', 'asc');
        }
        
        return $query->get();
    }

    // Helper: Get jenis order untuk range tanggal
    private function getJenisOrderRange($no_hp, $startDate, $endDate)
    {
        return DB::table('kurir_order as a')
            ->select([
                'a.jenis_layanan',
                DB::raw('COUNT(*) as total_order'),
                DB::raw('SUM(a.tarif) as total_pendapatan'),
            ])
            ->leftJoin('rb_sopir as b', 'b.id_sopir', '=', 'a.id_sopir')
            ->leftJoin('rb_konsumen as c', 'c.id_konsumen', '=', 'b.id_konsumen')
            ->where('a.status', 'FINISH')
            ->where('c.no_hp', $no_hp)
            ->whereBetween(DB::raw('DATE(a.tanggal_order)'), [$startDate, $endDate])
            ->whereNotNull('a.jenis_layanan')
            ->where('a.jenis_layanan', '!=', '')
            ->groupBy('a.jenis_layanan')
            ->get();
    }

    /**
     * Save transaksi manual kurir
     * 
     * Request body:
     * - no_hp: string (nomor HP kurir)
     * - no_hp_pelanggan: string (nomor HP pelanggan, "-" jika baru)
     * - no_hp_pelanggan_baru: string (nomor HP pelanggan baru jika no_hp_pelanggan = "-")
     * - nama_pelanggan: string (nama pelanggan baru)
     * - nama_layanan: string (RIDE|SEND|FOOD|SHOP)
     * - alamat_penjemputan: string
     * - alamat_tujuan: string
     * - biaya_antar: numeric
     * - nama_toko: string (required for SHOP/FOOD)
     * - agen_kurir: string (id_konsumen agen)
     * - tanggal_order: Y-m-d H:i:s
     * - btn_simpan: string (create|update|revisi|delete|approve)
     * - id_transaksi: int (required for update/revisi/delete/approve)
     * - alasan_revisi: string (required for revisi)
     * - alasan_pembatalan: string (required for delete)
     * - text_approve: string (must be "SETUJU" for approve)
     * - id_sopir: int (required for approve)
     * 
     * Response:
     * {
     *   success: true/false,
     *   message: "...",
     *   data: {...}
     * }
     */
    public function saveTransaksi(Request $req)
    {
        
        $messages = [
            'no_hp.required' => 'No HP Kurir Harus Diisi',
            'nama_layanan.required' => 'Nama Layanan Harus Dipilih',
            'alamat_penjemputan.required' => 'Alamat Penjemputan Harus Diisi',
            'alamat_tujuan.required' => 'Alamat Tujuan Harus Diisi',
            'biaya_antar.required' => 'Biaya Antar Harus Diisi',
            'biaya_antar.numeric' => 'Biaya Antar Harus Angka',
            'nama_toko.required' => 'Nama Toko/Resto Harus Diisi',
            'agen_kurir.required' => 'Agen Kurir Harus Diisi',
            'no_hp_pelanggan_baru.required' => 'No HP Pelanggan Harus Diisi',
            'no_hp_pelanggan_baru.numeric' => 'No HP Pelanggan Harus Angka Saja',
        ];

        $validator = \Validator::make($req->all(), [
            'no_hp' => 'required',
            'nama_layanan' => 'required',
            'alamat_penjemputan' => 'required',
            'alamat_tujuan' => 'required',
            'biaya_antar' => 'required|numeric',
            'agen_kurir' => 'required',
        ], $messages);

        if ($validator->fails()) {
            header('Content-Type: application/json');
            http_response_code(422);
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validasi untuk SHOP dan FOOD
        if ($req->nama_layanan == 'SHOP' || $req->nama_layanan == 'FOOD') {
            $validator_2 = \Validator::make($req->all(), [
                'nama_toko' => 'required',
            ], $messages);

            if ($validator_2->fails()) {
                header('Content-Type: application/json');
                http_response_code(422);
                return response()->json([
                    'success' => false,
                    'message' => 'Nama Toko/Resto Harus Diisi',
                    'errors' => $validator_2->errors()
                ], 422);
            }
        }

        // Validasi pelanggan baru
        if ($req->no_hp_pelanggan == '-' && $req->no_hp_pelanggan_baru == '') {
            header('Content-Type: application/json');
            http_response_code(422);
            return response()->json([
                'success' => false,
                'message' => 'No HP Pelanggan Harus Diisi'
            ], 422);
        }

        // Get kurir data from no_hp
        $getKurir = DB::table('rb_sopir as a')
            ->select('a.*')
            ->leftJoin('rb_konsumen as b', 'b.id_konsumen', 'a.id_konsumen')
            ->where('b.no_hp', $req->no_hp)
            ->first();

        if (!$getKurir) {
            header('Content-Type: application/json');
            http_response_code(404);
            return response()->json([
                'success' => false,
                'message' => 'Data kurir tidak ditemukan'
            ], 404);
        }

        // Hitung potongan komisi
        if ($getKurir->total_komisi != 0) {
            $potonganKomisi = $req->biaya_antar * ($getKurir->total_komisi / 100);
        } else {
            $potonganKomisi = $req->biaya_antar * ($this->getConfig('fee_kurir_total') / 100);
        }

        try {
            DB::beginTransaction();

            $id_pemesan = null;

            // Handle pelanggan
            $pemesan = DB::table('rb_konsumen')->where('no_hp', $req->no_hp_pelanggan)->first();
            
            if (!$pemesan && $req->btn_simpan == 'create') {
                // Buat pelanggan baru
                $dtKonsumen = [
                    'username' => $req->no_hp_pelanggan_baru,
                    'nama_lengkap' => $req->nama_pelanggan,
                    'email' => '-',
                    'jenis_kelamin' => 'Laki-laki',
                    'tanggal_lahir' => date('Y-m-d'),
                    'tempat_lahir' => '-',
                    'alamat_lengkap' => $req->alamat_tujuan,
                    'kecamatan_id' => '0',
                    'kota_id' => '0',
                    'provinsi_id' => '0',
                    'no_hp' => $req->no_hp_pelanggan_baru,
                    'foto' => '-',
                    'tanggal_daftar' => date('Y-m-d'),
                    'token' => 'N',
                    'referral_id' => $getKurir->id_konsumen,
                    'verifikasi' => 'N',
                    'password' => rand(1111111111, 9999999999),
                ];

                $id_pemesan = DB::table('rb_konsumen')->insertGetId($dtKonsumen);
            } else {
                $id_pemesan = $pemesan->id_konsumen;
            }

            // Prepare data transaksi
            $dtInsert = [
                'source' => 'MANUAL_KURIR',
                'tarif' => $req->biaya_antar,
                'id_agen' => $req->agen_kurir,
                'id_pemesan' => $id_pemesan,
                'alamat_jemput' => $req->alamat_penjemputan,
                'alamat_antar' => $req->alamat_tujuan,
                'jenis_layanan' => $req->nama_layanan,
                'metode_pembayaran' => 'CASH',
                'pemberi_barang' => $req->nama_toko ?? '',
                'tanggal_order' => $req->tanggal_order ?? date('Y-m-d H:i:s'),
            ];

            // Handle different actions
            if ($req->btn_simpan == 'create') {
                // Cek saldo kurir
                $getSaldo = $this->getSaldoKurir($getKurir->id_konsumen);

                if ($getSaldo < $potonganKomisi) {
                    DB::rollBack();
                    header('Content-Type: application/json');
                    http_response_code(400);
                    return response()->json([
                        'success' => false,
                        'message' => 'Maaf Saldo Kurang, Silahkan Top Up',
                        'saldo' => $getSaldo,
                        'potongan_komisi' => $potonganKomisi
                    ], 400);
                }

                $dtInsert['id_sopir'] = $getKurir->id_sopir;
                $dtInsert['kode_order'] = time() . rand(00, 99);
                $dtInsert['status'] = 'PENDING';
                
                $id_transaksi = DB::table('kurir_order')->insertGetId($dtInsert);

                DB::commit();

                header('Content-Type: application/json');
                return response()->json([
                    'success' => true,
                    'message' => 'Sukses Menyimpan Transaksi Manual',
                    'data' => [
                        'id_transaksi' => $id_transaksi,
                        'kode_order' => $dtInsert['kode_order']
                    ]
                ]);

            } elseif ($req->btn_simpan == 'update') {
                
                DB::table('kurir_order')->where('id', $req->id_transaksi)->update($dtInsert);
                DB::commit();

                header('Content-Type: application/json');
                return response()->json([
                    'success' => true,
                    'message' => 'Sukses Update Transaksi Manual',
                    'data' => ['id_transaksi' => $req->id_transaksi]
                ]);

            } elseif ($req->btn_simpan == 'revisi') {

                $dtInsert['status'] = 'RETURN';
                $dtInsert['alasan_pembatalan'] = $req->alasan_revisi;
                DB::table('kurir_order')->where('id', $req->id_transaksi)->update($dtInsert);
                DB::commit();

                header('Content-Type: application/json');
                return response()->json([
                    'success' => true,
                    'message' => 'Transaksi dikembalikan untuk revisi',
                    'data' => ['id_transaksi' => $req->id_transaksi]
                ]);

            } elseif ($req->btn_simpan == 'delete') {

                $dtInsert['status'] = 'CANCEL';
                $dtInsert['alasan_pembatalan'] = $req->alasan_pembatalan;
                DB::table('kurir_order')->where('id', $req->id_transaksi)->update($dtInsert);
                DB::commit();

                header('Content-Type: application/json');
                return response()->json([
                    'success' => true,
                    'message' => 'Transaksi dibatalkan',
                    'data' => ['id_transaksi' => $req->id_transaksi]
                ]);

            } elseif ($req->btn_simpan == 'approve') {

                if ($req->text_approve == 'SETUJU') {
                    $dtInsert['status'] = 'FINISH';
                    DB::table('kurir_order')->where('id', $req->id_transaksi)->update($dtInsert);

                    $getKurirOrang = DB::table('rb_sopir as a')
                        ->select('a.*')
                        ->leftJoin('rb_konsumen as b', 'b.id_konsumen', 'a.id_konsumen')
                        ->where('a.id_sopir', $req->id_sopir)
                        ->first();

                    

                    // Potong saldo untuk komisi
                    // $dtPotongan = [
                    //     'id_konsumen' => $getKurirOrang->id_konsumen,
                    //     'nominal' => $potonganKomisi,
                    //     'id_rekening' => 0,
                    //     'withdraw_fee' => 0,
                    //     'status' => 'Sukses',
                    //     'transaksi' => 'kredit',
                    //     'keterangan' => 'Potongan admin kurir transaksi manual',
                    //     'akun' => 'sopir',
                    //     'waktu_pendapatan' => date('Y-m-d H:i:s'),
                    // ];
                    // DB::table('rb_pendapatan_kurir')->insert($dtPotongan);

                    DB::table('rb_wallet_kurir')->insert([
                        'id_konsumen' => $getKurirOrang->id_konsumen,
                        'amount' => $potonganKomisi,
                        'trx_type' => 'debit',
                        'note' => 'Potongan admin kurir transaksi manual',
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);

                    // Bagi komisi
                    $bagiKomisi = $this->pembagianKomisiKurir($potonganKomisi, $req->id_sopir, $req->id_transaksi);
                    
                    if ($bagiKomisi) {
                        DB::commit();
                        header('Content-Type: application/json');
                        return response()->json([
                            'success' => true,
                            'message' => 'Sukses Approve Transaksi Manual, Komisi DiBagikan',
                            'data' => ['id_transaksi' => $req->id_transaksi]
                        ]);
                    } else {
                        DB::rollBack();
                        header('Content-Type: application/json');
                        http_response_code(500);
                        return response()->json([
                            'success' => false,
                            'message' => 'Gagal Menghitung Komisi'
                        ], 500);
                    }

                } else {
                    DB::rollBack();
                    header('Content-Type: application/json');
                    http_response_code(400);
                    return response()->json([
                        'success' => false,
                        'message' => 'Kata Yang Dimasukan Salah'
                    ], 400);
                }
            }

        } catch (\Exception $e) {
            DB::rollBack();
            header('Content-Type: application/json');
            http_response_code(500);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get downline data from rb_konsumen where referral_id = id_konsumen
     * 
     * Request params:
     * - id_konsumen: int (required)
     * 
     * Response:
     * {
     *   success: true,
     *   data: [{id_konsumen, nama_lengkap, no_hp, ...}],
     *   message: "...",
     *   total: int
     * }
     */
    public function getDownline(Request $req)
    {
        $validator = \Validator::make($req->all(), [
            'id_konsumen' => 'required|integer',
        ]);

        if ($validator->fails()) {
            header('Content-Type: application/json');
            http_response_code(422);
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $downline = DB::table('rb_konsumen')
            ->where('referral_id', $req->id_konsumen)
            ->get();

        header('Content-Type: application/json');
        return response()->json([
            'success' => true,
            'data' => $downline,
            'message' => 'Data downline berhasil diambil',
            'total' => $downline->count()
        ]);
    }

    /**
     * Check user by phone number
     * Request params:
     * - no_hp: string (required)
     *
     * Response:
     * - 422 on validation error
     * - 404 when user not found
     * - 200 with user data when found
     */
    public function checkUserByPhone(Request $req)
    {
        $validator = \Validator::make($req->all(), [
            'no_hp' => 'required',
        ]);

        if ($validator->fails()) {
            header('Content-Type: application/json');
            http_response_code(422);
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        // Normalize phone using existing helper
        $cleanNoHp = $this->cleanPhoneNumber($req->no_hp);

        $user = DB::table('rb_konsumen')->where('no_hp', $cleanNoHp)->first();

        header('Content-Type: application/json');

        if (!$user) {
            http_response_code(404);
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        // Normalize any 0 foreign keys for consistency
        $this->normalizeKonsumen($user);

        return response()->json([
            'success' => true,
            'data' => $user,
            'message' => 'User ditemukan'
        ]);
    }

    /**
     * Create transfer request between users
     * Request params:
     * - no_hp_sender: string (required)
     * - no_hp_receiver: string (required)
     * - amount: numeric (required, min 1)
     *
     * Response:
     * - 422 on validation error
     * - 404 when sender/receiver not found
     * - 400 when insufficient balance
     * - 200 on success
     */
    public function createTransferRequest(Request $req)
    {
        $validator = \Validator::make($req->all(), [
            'no_hp_sender' => 'required',
            'no_hp_receiver' => 'required',
            'amount' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            header('Content-Type: application/json');
            http_response_code(422);
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        // Normalize phones
        $cleanSenderHp = $this->cleanPhoneNumber($req->no_hp_sender);
        $cleanReceiverHp = $this->cleanPhoneNumber($req->no_hp_receiver);

        // Get sender
        $sender = DB::table('rb_konsumen')->where('no_hp', $cleanSenderHp)->first();
        if (!$sender) {
            header('Content-Type: application/json');
            http_response_code(404);
            return response()->json([
                'success' => false,
                'message' => 'Sender tidak ditemukan'
            ], 404);
        }

        // Get receiver
        $receiver = DB::table('rb_konsumen')->where('no_hp', $cleanReceiverHp)->first();
        if (!$receiver) {
            header('Content-Type: application/json');
            http_response_code(404);
            return response()->json([
                'success' => false,
                'message' => 'Receiver tidak ditemukan'
            ], 404);
        }

        // Check sender balance
        $saldoSender = DB::table('rb_wallet_users')
            ->where('id_konsumen', $sender->id_konsumen)
            ->selectRaw('SUM(CASE WHEN trx_type = ? THEN amount WHEN trx_type = ? THEN -amount ELSE 0 END) as saldo', ['credit', 'debit'])
            ->first()->saldo ?? 0;

        if ($saldoSender < $req->amount) {
            header('Content-Type: application/json');
            http_response_code(400);
            return response()->json([
                'success' => false,
                'message' => 'Saldo tidak cukup',
                'saldo' => $saldoSender
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Debit sender
            DB::table('rb_wallet_users')->insert([
                'id_konsumen' => $sender->id_konsumen,
                'amount' => $req->amount,
                'trx_type' => 'debit',
                'note' => 'Transfer ke ' . $cleanReceiverHp,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // Credit receiver
            DB::table('rb_wallet_users')->insert([
                'id_konsumen' => $receiver->id_konsumen,
                'amount' => $req->amount,
                'trx_type' => 'credit',
                'note' => 'Transfer dari ' . $cleanSenderHp,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            DB::commit();

            header('Content-Type: application/json');
            return response()->json([
                'success' => true,
                'message' => 'Transfer berhasil'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            header('Content-Type: application/json');
            http_response_code(500);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getPendingWithdrawRequestsOlderThan7Days()
    {
        
        $data = DB::table('rb_wallet_requests')
            ->where('status', 'pending')
            ->where('req_type', 'withdraw')
            ->where('created_at', '<', DB::raw('DATE_SUB(NOW(), INTERVAL 7 DAY)'))
            ->update(['status'=>'rejected']);
        return ['success' => true, 'data' => $data];
    }



}