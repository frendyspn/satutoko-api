<?php

namespace App\Events;

use App\Models\KurirOrder;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KurirOrderAccepted implements ShouldBroadcast
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
        return 'order.accepted';
    }

    public function broadcastWith()
    {
        return [
            'order_id' => $this->order->id,
            'id_sopir' => $this->order->id_sopir,
        ];
    }
}