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
     * 
     *
     * @param int $buyerId 
     * @param int $productId 
     * @param int $quantity
     * @param float $unitPrice
     * @param string $status (pending أو confirmed)
     * @param string $paymentMethod (wallet أو cash)
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

            
            $itemTotal   = $quantity * $unitPrice;
            $deliveryFee = (float)config('tasleem.delivery_fee');
            $buyerCharge = $itemTotal + $deliveryFee;
            
           
            $seller = $product->owner;
            $sellerIsAdmin = $seller->role === 'admin';
            $hasFreeSales = !$sellerIsAdmin && $seller->free_sales_remaining > 0;
            
         
            $tasleemFee = ($sellerIsAdmin || $hasFreeSales) 
                ? 0 
                : round($itemTotal * (float)config('tasleem.commission_rate'), 2);

            
            if ($paymentMethod === 'wallet') {
                if ($buyer->wallet_balance < $buyerCharge) {
                    throw new RuntimeException('Insufficient wallet balance');
                }

                
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

            
            if ($paymentMethod === 'wallet') {
                $buyer->walletTransactions()
                    ->where('ref_type', 'order')
                    ->whereNull('ref_id')
                    ->latest()
                    ->first()
                    ?->update(['ref_id' => $order->order_id]);
            }

           
            Payment::create([
                'order_id'       => $order->order_id, 
                'rental_id'      => null,              
                'user_id'        => $buyerId,
                'amount'         => $buyerCharge,
                'payment_method' => $paymentMethod,
                'status'         => 'pending',
            ]);

       
            $product->decrement('quantity', $quantity);

       
            if ($product->quantity <= 0) {
                $product->update(['status' => '0']);
            }

            
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