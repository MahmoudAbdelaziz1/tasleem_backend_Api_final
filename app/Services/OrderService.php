<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderService
{
    /**
     * إنشاء طلب جديد (يُستخدم للشراء المباشر وقبول العروض).
     *
     * @param int $buyerId معرف المشتري
     * @param int $productId معرف المنتج
     * @param int $quantity الكمية
     * @param float $unitPrice سعر الوحدة
     * @param string $status حالة الطلب الأولية (pending أو confirmed)
     * @return Order
     * @throws RuntimeException
     */
    public static function placeOrder(
        int $buyerId,
        int $productId,
        int $quantity,
        float $unitPrice,
        string $status = 'pending'
    ): Order {
        
        return DB::transaction(function () use ($buyerId, $productId, $quantity, $unitPrice, $status) {
            $buyer   = User::lockForUpdate()->find($buyerId);
            $product = Product::lockForUpdate()->find($productId);

            if (!$buyer || !$product) {
                throw new RuntimeException('Buyer or product not found');
            }

            if ($product->quantity < $quantity) {
                throw new RuntimeException('Insufficient product quantity');
            }

            // حساب الرسوم
            $itemTotal   = $quantity * $unitPrice;
            $deliveryFee = (float)config('tasleem.delivery_fee');
            $buyerCharge = $itemTotal + $deliveryFee;
            $tasleemFee  = round($itemTotal * (float)config('tasleem.commission_rate'), 2);

            // خصم المبلغ من المحفظة (Hold)
            WalletService::move(
                $buyer,
                'hold',
                -$buyerCharge,
                'order',
                null, // سيتم تحديثه بمعرف الطلب بعد الإنشاء
                'Order payment held'
            );

            // إنشاء الطلب
            $order = Order::create([
                'user_id'       => $buyerId,
                'product_id'    => $productId,
                'quantity'      => $quantity,
                'unit_price'    => $unitPrice,
                'total_price'   => $itemTotal,
                'tasleem_fee'   => $tasleemFee,
                'delivery_fee'  => $deliveryFee,
                'status'        => $status,
            ]);

            
        
        $buyer->walletTransactions()
            ->where('ref_type', 'order')
            ->whereNull('ref_id')
            ->latest()
            ->first()
            ?->update(['ref_id' => $order->order_id]); 


            // إنشاء سجل الدفع
            Payment::create([
                'order_id'       => $order->order_id, 
                'rental_id'      => null,              
                'user_id'        => $buyerId,
                'amount'         => $buyerCharge,
                'payment_method' => 'wallet',
                'status'         => 'pending',
            ]);

            // خصم الكمية من المنتج
            $product->decrement('quantity', $quantity);

            // إذا نفد المخزون، تغيير حالة المنتج
            if ($product->quantity <= 0) {
                $product->update(['status' => '0']); // 0 = sold out
            }

                // إرسال إشعار للبائع
        Notify::send(
            $product->owner_id,
            'order_placed',
            'New order',
            'Someone bought "' . $product->name . '". Confirm to proceed.',
            'order',
            $order->order_id  // ← غيّر من id إلى order_id
        );

            return $order;
        });
    }
}