<?php

namespace App\Events;

use App\Models\KurirOrder;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KurirOrderCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $order;

    public function __construct(KurirOrder $order)
    {
        $this->order = $order;
    }

    public function broadcastOn()
    {
        return new PresenceChannel('kurir-orders');
    }

    public function broadcastAs()
    {
        return 'order.created';
    }

    public function broadcastWith()
    {
        return [
            'order' => [
                'id' => $this->order->id,
                'kode_order' => $this->order->kode_order,
                'service' => $this->order->service,
                'tarif' => $this->order->tarif,
                'time_remaining' => $this->order->time_remaining,
                'titik_jemput' => $this->order->titik_jemput,
                'alamat_jemput' => $this->order->alamat_jemput,
                'titik_antar' => $this->order->titik_antar,
                'alamat_antar' => $this->order->alamat_antar,
            ]
        ];
    }

    /**
     * Override serialize untuk mencegah error
     */
    public function __serialize(): array
    {
        return [
            'order' => $this->order ? $this->order->toArray() : null,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->order = isset($data['order']) ? new KurirOrder($data['order']) : null;
    }
}