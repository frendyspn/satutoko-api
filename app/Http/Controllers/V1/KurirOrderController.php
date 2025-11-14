<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\KurirOrder;
use App\Events\KurirOrderCreated;
use App\Events\KurirOrderAccepted;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class KurirOrderController extends Controller
{
    /**
     * Get available orders for kurir (status = 'new' dan belum expired)
     */
    public function getAvailableOrders(Request $request): JsonResponse
    {
        try {
            $orders = KurirOrder::where('status', KurirOrder::STATUS_AVAILABLE)
                ->whereRaw('DATE_ADD(tanggal_order, INTERVAL 2 MINUTE) > NOW()')
                ->orderBy('tanggal_order', 'desc')
                ->get()
                ->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'kode_order' => $order->kode_order,
                        'service' => $order->service,
                        'titik_jemput' => $order->titik_jemput,
                        'alamat_jemput' => $order->alamat_jemput,
                        'titik_antar' => $order->titik_antar,
                        'alamat_antar' => $order->alamat_antar,
                        'tarif' => $order->tarif,
                        'jarak' => $order->jarak,
                        'jenis_layanan' => $order->jenis_layanan,
                        'time_remaining' => $order->time_remaining,
                        'tanggal_order' => $order->tanggal_order,
                        'telp_penerima_barang' => $order->telp_penerima_barang,
                        'catatan_penerima_barang' => $order->catatan_penerima_barang,
                        'telp_pemberi_barang' => $order->telp_pemberi_barang,
                        'catatan_pemberi_barang' => $order->catatan_pemberi_barang,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $orders,
                'message' => 'Available orders retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Accept order (rebutan system)
     */
    public function acceptOrder(Request $request, $orderId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'kurir_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $order = KurirOrder::where('id', $orderId)
                ->where('status', KurirOrder::STATUS_AVAILABLE)
                ->whereRaw('DATE_ADD(tanggal_order, INTERVAL 2 MINUTE) > NOW()')
                ->lockForUpdate()
                ->first();

            if (!$order) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Order not available or expired'
                ], 404);
            }

            // Update order status
            $order->update([
                'status' => KurirOrder::STATUS_ACCEPTED,
                'id_sopir' => $request->kurir_id,
            ]);

            DB::commit();

            // Broadcast update
            // broadcast(new KurirOrderAccepted($order))->toOthers();
            broadcast(new OrderStatusUpdated([
                'id' => $order->id,
                'kode_order' => $order->kode_order,
                'status' => 'PROCESS',
                'status_text' => 'Sedang Diproses',
                'id_kurir' => $user->id,
                'kurir_name' => $user->name,
            ]))->toOthers();

            return response()->json([
                'success' => true,
                'data' => $order,
                'message' => 'Order accepted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to accept order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new order (dari POS)
     */
    public function createOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'titik_jemput' => 'required|string',
            'alamat_jemput' => 'required|string',
            'titik_antar' => 'required|string',
            'alamat_antar' => 'required|string',
            'service' => 'required|string',
            'tarif' => 'required|numeric|min:0',
            'id_agen' => 'required|integer',
            'id_pemesan' => 'required|integer',
            'telp_penerima_barang' => 'nullable|string',
            'catatan_penerima_barang' => 'nullable|string',
            'telp_pemberi_barang' => 'nullable|string',
            'catatan_pemberi_barang' => 'nullable|string',
            'jenis_layanan' => 'nullable|string',
            'jarak' => 'nullable|numeric',
            'metode_pembayaran' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $order = KurirOrder::create([
                'kode_order' => 'ORD-' . strtoupper(Str::random(8)),
                'titik_jemput' => $request->titik_jemput,
                'alamat_jemput' => $request->alamat_jemput,
                'titik_antar' => $request->titik_antar,
                'alamat_antar' => $request->alamat_antar,
                'service' => $request->service,
                'tarif' => $request->tarif,
                'status' => KurirOrder::STATUS_AVAILABLE,
                'tanggal_order' => now(),
                'id_agen' => $request->id_agen,
                'id_pemesan' => $request->id_pemesan,
                'telp_penerima_barang' => $request->telp_penerima_barang,
                'catatan_penerima_barang' => $request->catatan_penerima_barang,
                'telp_pemberi_barang' => $request->telp_pemberi_barang,
                'catatan_pemberi_barang' => $request->catatan_pemberi_barang,
                'jenis_layanan' => $request->jenis_layanan ?? 'KURIR',
                'jarak' => $request->jarak ?? 0,
                'metode_pembayaran' => $request->metode_pembayaran,
            ]);

            // Broadcast new order dengan error handling
            try {
                broadcast(new KurirOrderCreated($order))->toOthers();
            } catch (\Exception $broadcastError) {
                // Log error tapi jangan fail request
                \Log::warning('Broadcast failed: ' . $broadcastError->getMessage());
            }

            return response()->json([
                'success' => true,
                'data' => $order,
                'message' => 'Order created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get kurir's accepted orders
     */
    public function getMyOrders(Request $request): JsonResponse
    {
        try {
            $orders = KurirOrder::where('id_sopir', $request->user()->id)
                ->whereIn('status', [KurirOrder::STATUS_ACCEPTED, 'proses'])
                ->orderBy('tanggal_order', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $orders,
                'message' => 'Orders retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update order status (ambil barang, serah terima, dll)
     */
    public function updateOrderStatus(Request $request, $orderId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:proses,selesai,batal',
            'foto_ambil_barang' => 'nullable|string',
            'foto_serah_terima_barang' => 'nullable|string',
            'penerima_barang' => 'nullable|string',
            'alasan_pembatalan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $order = KurirOrder::where('id', $orderId)
                ->where('id_sopir', $request->user()->id)
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            $updateData = ['status' => $request->status];

            // Update berdasarkan status
            switch ($request->status) {
                case 'proses':
                    $updateData['waktu_ambil_barang'] = now();
                    if ($request->foto_ambil_barang) {
                        $updateData['foto_ambil_barang'] = $request->foto_ambil_barang;
                    }
                    break;

                case 'selesai':
                    $updateData['waktu_serah_terima_barang'] = now();
                    if ($request->foto_serah_terima_barang) {
                        $updateData['foto_serah_terima_barang'] = $request->foto_serah_terima_barang;
                    }
                    if ($request->penerima_barang) {
                        $updateData['penerima_barang'] = $request->penerima_barang;
                    }
                    break;

                case 'batal':
                    if ($request->alasan_pembatalan) {
                        $updateData['alasan_pembatalan'] = $request->alasan_pembatalan;
                    }
                    break;
            }

            $order->update($updateData);

            return response()->json([
                'success' => true,
                'data' => $order,
                'message' => 'Order status updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update order status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}