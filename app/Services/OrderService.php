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
     * @param string $paymentMethod طريقة الدفع (wallet أو cash)
     * @return Order
     * @throws RuntimeException
     */
    public static function placeOrder(
        int $buyerId,
        int $productId,
        int $quantity,
        float $unitPrice,
        string $status = 'pending',
        string $paymentMethod = 'wallet'
    ): Order {
        
        return DB::transaction(function () use ($buyerId, $productId, $quantity, $unitPrice, $status, $paymentMethod) {
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
            
            // ✅ حساب عمولة تسليط - لو البائع لسه عنده مبيعات مجانية، العمولة = 0
            $seller = $product->owner;
            $sellerIsAdmin = $seller->role === 'admin';
            $hasFreeSales = !$sellerIsAdmin && $seller->free_sales_remaining > 0;
            
            $tasleemFee = $hasFreeSales 
                ? 0 
                : round($itemTotal * (float)config('tasleem.commission_rate'), 2);

            // ✅ التحقق من الرصيد وحجز الأموال فقط إذا كان الدفع بالمحفظة
            if ($paymentMethod === 'wallet') {
                if ($buyer->wallet_balance < $buyerCharge) {
                    throw new RuntimeException('Insufficient wallet balance');
                }

                // خصم المبلغ من المحفظة (Hold)
                WalletService::move(
                    $buyer,
                    'hold',
                    -$buyerCharge,
                    'order',
                    null,
                    'Order payment held'
                );
            }

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

            // ✅ تحديث ref_id للمعاملة فقط إذا كان الدفع بالمحفظة
            if ($paymentMethod === 'wallet') {
                $buyer->walletTransactions()
                    ->where('ref_type', 'order')
                    ->whereNull('ref_id')
                    ->latest()
                    ->first()
                    ?->update(['ref_id' => $order->order_id]);
            }

            // ✅ إنشاء سجل الدفع بطريقة الدفع المحددة
            Payment::create([
                'order_id'       => $order->order_id, 
                'rental_id'      => null,              
                'user_id'        => $buyerId,
                'amount'         => $buyerCharge,
                'payment_method' => $paymentMethod,
                'status'         => 'pending',
            ]);

            // خصم الكمية من المنتج
            $product->decrement('quantity', $quantity);

            // إذا نفد المخزون، تغيير حالة المنتج
            if ($product->quantity <= 0) {
                $product->update(['status' => '0']);
            }

            // إرسال إشعار للبائع
            Notify::send(
                $product->owner_id,
                'order_placed',
                'New order',
                'Someone bought "' . $product->name . '". Confirm to proceed.',
                'order',
                $order->order_id
            );

            return $order;
        });
    }
}