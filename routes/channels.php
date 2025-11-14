<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Public channel untuk orders
Broadcast::channel('orders', function ($user) {
    // Allow authenticated users
    return $user !== null;
});

// Kurir orders channel
Broadcast::channel('kurir-orders', function ($user) {
    // Allow kurir and admin
    return $user && in_array($user->role, ['kurir', 'admin']);
});
