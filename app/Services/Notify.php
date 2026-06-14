<?php

namespace App\Services;

use App\Models\Notification;

class Notify
{
    /**
     * إرسال إشعار لمستخدم.
     *
     * @param int $userId معرف المستخدم المرسل إليه
     * @param string $type نوع الإشعار (order_placed, offer_received, etc.)
     * @param string $title عنوان الإشعار
     * @param string|null $body نص الإشعار
     * @param string|null $refType نوع المرجع (order, offer, product)
     * @param int|null $refId معرف المرجع
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