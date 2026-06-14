<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WalletService
{
    /**
     * تحريك الأموال من/إلى محفظة المستخدم بشكل آمن.
     *
     * @param User $user المستخدم
     * @param string $type نوع العملية (topup, hold, release, refund, boost_fee, payout)
     * @param float $signedAmount المبلغ (موجب للإيداع، سالب للخصم)
     * @param string|null $refType نوع المرجع (order, rental, offer, boost)
     * @param int|null $refId معرف المرجع
     * @param string|null $desc وصف العملية
     * @return WalletTransaction
     * @throws RuntimeException إذا كان الرصيد غير كافٍ
     */
    public static function move(
        User $user,
        string $type,
        float $signedAmount,
        ?string $refType = null,
        ?int $refId = null,
        ?string $desc = null
    ): WalletTransaction {
        
        return DB::transaction(function () use ($user, $type, $signedAmount, $refType, $refId, $desc) {
            // قفل صف المستخدم لمنع التعديل المتزامن
            $user = User::lockForUpdate()->find($user->id);

            // حساب الرصيد الجديد
            $newBalance = (float)$user->wallet_balance + $signedAmount;

            // إذا كان المبلغ سالباً (خصم) والرصيد الجديد سيكون أقل من صفر، ارفض العملية
            if ($newBalance < 0) {
                throw new RuntimeException('Insufficient wallet balance');
            }

            // تحديث رصيد المستخدم
            $user->wallet_balance = $newBalance;
            $user->save();

            // تسجيل العملية في جدول الحركات
            return WalletTransaction::create([
                'user_id'       => $user->id,
                'type'          => $type,
                'amount'        => $signedAmount,
                'balance_after' => $newBalance,
                'ref_type'      => $refType,
                'ref_id'        => $refId,
                'description'   => $desc,
            ]);
        });
    }
}