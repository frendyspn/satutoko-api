<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $orderData;

    public function __construct($orderData)
    {
        $this->orderData = $orderData;
    }

    public function broadcastOn()
    {
        return new Channel('orders');
    }

    public function broadcastAs()
    {
        return 'order.status.updated';
    }

    public function broadcastWith()
    {
        return [
            'order_id' => $this->orderData['id'] ?? null,
            'kode_order' => $this->orderData['kode_order'] ?? null,
            'status' => $this->orderData['status'] ?? 'unknown',
            'status_text' => $this->orderData['status_text'] ?? $this->orderData['status'],
            'updated_at' => now()->toISOString(),
            'message' => 'Order status updated'
        ];
    }
}