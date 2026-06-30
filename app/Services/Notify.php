<?php

namespace App\Services;

use App\Models\Notification;

class Notify
{
    /**
     * 
     *
     * @param int $userId 
     * @param string $type (order_placed, offer_received, etc.)
     * @param string $title 
     * @param string|null $body 
     * @param string|null $refType(order, offer, product)
     * @param int|null $refId 
     * @return void
     */
    public static function send(
        int $userId,
        string $type,
        string $title,
        ?string $body = null,
        ?string $refType = null,
        ?int $refId = null
    ): void {
        Notification::create([
            'user_id'  => $userId,
            'type'     => $type,
            'title'    => $title,
            'body'     => $body,
            'ref_type' => $refType,
            'ref_id'   => $refId,
        ]);
    }
}