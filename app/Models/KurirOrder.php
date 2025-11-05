<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
// Hapus import ShouldBroadcast
// use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class KurirOrder extends Model // Hapus implements ShouldBroadcast
{
    use HasFactory;

    protected $table = 'kurir_order';
    public $timestamps = false;

    protected $fillable = [
        'titik_jemput',
        'alamat_jemput',
        'titik_antar',
        'alamat_antar',
        'service',
        'id_penjualan',
        'status',
        'source',
        'tarif',
        'id_sopir',
        'kode_order',
        'foto_ambil_barang',
        'waktu_ambil_barang',
        'foto_serah_terima_barang',
        'waktu_serah_terima_barang',
        'penerima_barang',
        'tanggal_order',
        'id_agen',
        'id_pemesan',
        'telp_penerima_barang',
        'catatan_penerima_barang',
        'pemberi_barang',
        'telp_pemberi_barang',
        'catatan_pemberi_barang',
        'jenis_layanan',
        'jarak',
        'metode_pembayaran',
        'id_reseller',
        'alasan_pembatalan',
    ];

    protected $casts = [
        'tanggal_order' => 'datetime',
        'waktu_ambil_barang' => 'datetime',
        'waktu_serah_terima_barang' => 'datetime',
        'tarif' => 'decimal:2',
        'jarak' => 'decimal:2',
    ];

    protected $appends = ['time_remaining'];

    // Status mapping untuk sistem rebutan
    const STATUS_AVAILABLE = 'NEW';
    const STATUS_ACCEPTED = 'ACCEPTED';
    const STATUS_IN_PROGRESS = 'PROCESS';
    const STATUS_COMPLETED = 'FINISH';
    const STATUS_CANCELLED = 'CANCEL';

    // Relationships
    public function sopir(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_sopir');
    }

    public function agen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_agen');
    }

    public function pemesan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pemesan');
    }

    // Accessor untuk waktu tersisa (untuk rebutan)
    public function getTimeRemainingAttribute()
    {
        if ($this->status === self::STATUS_AVAILABLE) {
            $expiresAt = $this->tanggal_order->addMinutes(2);
            return max(0, $expiresAt->diffInSeconds(now()));
        }
        return 0;
    }

    // Helper methods
    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE && 
               $this->tanggal_order->addMinutes(2)->isFuture();
    }

    public function acceptBy(User $kurir): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        return $this->update([
            'status' => self::STATUS_ACCEPTED,
            'id_sopir' => $kurir->id,
        ]);
    }

    // HAPUS SEMUA METHOD BROADCASTING DARI MODEL
    // Karena broadcasting dilakukan melalui Events
    // Bukan langsung dari model
}