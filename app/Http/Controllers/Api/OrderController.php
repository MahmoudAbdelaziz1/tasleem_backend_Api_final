<?php
// app/Http/Controllers/Api/OrderController.php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Http\Resources\OrderResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Api\LogController;  
use App\Services\OrderService;
use App\Services\WalletService;
use App\Services\Notify;
use App\Models\Payment;
use RuntimeException;

class OrderController extends BaseController
{
    public function index(Request $request)
    {
        
        $query = Order::with(['user', 'product.images', 'payment']);

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->paginate($request->get('per_page', 15));

        LogController::addLog(
            userId: auth()->id(),
            actionType: 'VIEW',
            actionName: 'view_orders',
            module: 'orders',
            entityId: null,
            oldData: null,
            newData: ['filters' => $request->only(['user_id', 'status'])],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            status: 'success',
            message: 'User viewed orders list'
        );

        return $this->sendPaginated(
            $orders,
            OrderResource::collection($orders),
            'Orders retrieved successfully'
        );
    }

public function store(Request $request)
{
    $request->validate([
        'product_id' => 'required|exists:products,id',
        'quantity'   => 'required|integer|min:1',
    ]);

    $product = \App\Models\Product::findOrFail($request->product_id);

    // منع شراء المنتج من صاحبه
    if ($product->owner_id === auth()->id()) {
        return $this->sendError('You cannot buy your own product', null, 400);
    }

    try {
        // استخدام OrderService لإنشاء الطلب + خصم الأموال + تحديث المخزون
        $order = OrderService::placeOrder(
            auth()->id(),
            $product->id,
            $request->quantity,
            (float) $product->price,
            'pending' // حالة أولية: بانتظار تأكيد البائع
        );

        return $this->sendResponse(
            new \App\Http\Resources\OrderResource($order->load('product.images', 'payment')),
            'Order placed successfully. Awaiting seller confirmation.',
            201
        );

    } catch (RuntimeException $e) {
        // إذا كان الرصيد غير كافي أو الكمية غير متوفرة
        return $this->sendError($e->getMessage(), null, 402);
    }
}

    public function show($id)
    {
       
        $order = Order::with(['user', 'product.images', 'payment'])->find($id);

        if (!$order) {
            LogController::addLog(
                userId: auth()->id(),
                actionType: 'ERROR',
                actionName: 'order_not_found',
                module: 'orders',
                entityId: $id,
                oldData: null,
                newData: null,
                ipAddress: request()->ip(),
                userAgent: request()->userAgent(),
                status: 'failed',
                message: 'Attempted to view non-existent order #' . $id,
                errorCode: 404
            );
            return $this->sendError('Order not found');
        }

        LogController::addLog(
            userId: auth()->id(),
            actionType: 'VIEW',
            actionName: 'view_order_details',
            module: 'orders',
            entityId: $order->id,
            oldData: null,
            newData: null,
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
            status: 'success',
            message: 'User viewed order #' . $order->id
        );

        return $this->sendResponse(
            new OrderResource($order),
            'Order retrieved successfully'
        );
    }

    public function update(Request $request, $id)
    {
        $order = Order::find($id);

        if (!$order) {
            LogController::addLog(
                userId: auth()->id(),
                actionType: 'ERROR',
                actionName: 'order_update_failed',
                module: 'orders',
                entityId: $id,
                oldData: null,
                newData: null,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
                status: 'failed',
                message: 'Attempted to update non-existent order #' . $id,
                errorCode: 404
            );
            return $this->sendError('Order not found');
        }

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:pending,confirmed,shipped,delivered,cancelled,returned',
            'quantity' => 'sometimes|integer|min:1',
            'unit_price' => 'sometimes|numeric|min:0',
        ]);

        if ($validator->fails()) {
            LogController::addLog(
                userId: auth()->id(),
                actionType: 'ERROR',
                actionName: 'order_update_validation_failed',
                module: 'orders',
                entityId: $order->id,
                oldData: null,
                newData: $request->all(),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
                status: 'failed',
                message: 'Validation failed: ' . json_encode($validator->errors()),
                errorCode: 422
            );
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        $oldData = $order->toArray();
        
        $order->update($request->only(['status', 'quantity', 'unit_price']));
        
        if ($request->has('quantity') || $request->has('unit_price')) {
            $order->total_price = $order->quantity * $order->unit_price;
            $order->save();
        }

        LogController::addLog(
            userId: auth()->id(),
            actionType: 'UPDATE',
            actionName: 'order_update',
            module: 'orders',
            entityId: $order->id,
            oldData: $oldData,
            newData: $order->fresh()->toArray(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            status: 'success',
            message: 'Order #' . $order->id . ' updated'
        );

        return $this->sendResponse(
            new OrderResource($order),
            'Order updated successfully'
        );
    }

    public function destroy($id)
    {
        $order = Order::find($id);

        if (!$order) {
            LogController::addLog(
                userId: auth()->id(),
                actionType: 'ERROR',
                actionName: 'order_delete_failed',
                module: 'orders',
                entityId: $id,
                oldData: null,
                newData: null,
                ipAddress: request()->ip(),
                userAgent: request()->userAgent(),
                status: 'failed',
                message: 'Attempted to delete non-existent order #' . $id,
                errorCode: 404
            );
            return $this->sendError('Order not found');
        }

        if ($order->status !== 'pending') {
            LogController::addLog(
                userId: auth()->id(),
                actionType: 'ERROR',
                actionName: 'order_delete_failed',
                module: 'orders',
                entityId: $order->id,
                oldData: null,
                newData: ['status' => $order->status],
                ipAddress: request()->ip(),
                userAgent: request()->userAgent(),
                status: 'failed',
                message: 'Cannot delete order #' . $order->id . ' with status: ' . $order->status,
                errorCode: 400
            );
            return $this->sendError('Cannot delete order that is not pending');
        }

        $oldData = $order->toArray();
        $order->delete();

        LogController::addLog(
            userId: auth()->id(),
            actionType: 'DELETE',
            actionName: 'order_delete',
            module: 'orders',
            entityId: $id,
            oldData: $oldData,
            newData: null,
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
            status: 'success',
            message: 'Order #' . $id . ' deleted successfully'
        );

        return $this->sendResponse(null, 'Order deleted successfully');
    }




    public function sellerConfirm($id)
    {
        $order = \App\Models\Order::with('product')->find($id);

        if (!$order) {
            return $this->sendError('Order not found', null, 404);
        }

        // التحقق من أن المستخدم هو صاحب المنتج
        if (auth()->id() !== $order->product->owner_id) {
            return $this->sendError('Not your sale', null, 403);
        }

        // التحقق من حالة الطلب
        if ($order->status !== 'pending') {
            return $this->sendError('Order already handled', null, 400);
        }

        $order->update(['status' => 'confirmed']);

        // إشعار المشتري
        Notify::send(
            $order->user_id,
            'order_confirmed',
            'Seller confirmed',
            'Your order is confirmed — Tasleem is processing it.',
            'order',
            $order->id
        );

        return $this->sendResponse(
            new \App\Http\Resources\OrderResource($order->fresh()->load('user', 'product.images', 'payment')),
            'Order confirmed by seller'
        );
    }





    public function complete($id)
    {
        $order = \App\Models\Order::with('product', 'payment')->find($id);

        if (!$order) {
            return $this->sendError('Order not found', null, 404);
        }

        /** @var \App\Models\User $currentUser */
        $currentUser = auth()->user();
        
        if (!$currentUser->isAdmin()) {
            return $this->sendError('Admin only', null, 403);
        }

        // التحقق من حالة الطلب
        if ($order->status !== 'confirmed') {
            return $this->sendError('Order is not confirmed', null, 400);
        }

        // التحقق من أن الدفع لم يتم تحريره بعد
        if (!$order->payment || $order->payment->status !== 'pending') {
            return $this->sendError('Nothing to release', null, 400);
        }

        $sellerId = $order->product->owner_id;
        $seller   = \App\Models\User::find($sellerId);

        // حساب المبلغ الذي سيحصل عليه البائع (السعر - عمولة المنصة)
        $payout = (float) $order->total_price - (float) $order->tasleem_fee;

        // تحرير الأموال للبائع
        WalletService::move(
            $seller,
            'release',
            $payout,
            'order',
            $order->id,
            'Escrow released for order #' . $order->id
        );

        // تحديث حالة الدفع والطلب
        $order->payment->update(['status' => 'completed']);
        $order->update(['status' => 'delivered']); // delivered = Completed في التطبيق

        // إشعار البائع
        Notify::send(
            $sellerId,
            'order_completed',
            'You got paid',
            'EGP ' . number_format($payout, 2) . ' added to your wallet.',
            'order',
            $order->id
        );

        // إشعار المشتري
        Notify::send(
            $order->user_id,
            'order_completed',
            'Order complete',
            'Your order is complete. Enjoy!',
            'order',
            $order->id
        );

        return $this->sendResponse(
            new \App\Http\Resources\OrderResource($order->fresh()->load('user', 'product.images', 'payment')),
            'Order completed and seller paid'
        );
    }    



    /**
     * إلغاء الطلب (بواسطة المشتري أو الأدمن) واسترداد الأموال.
     * POST /api/orders/{id}/cancel
     */
    public function cancel($id)
    {
        $order = \App\Models\Order::with('product', 'payment')->find($id);

        if (!$order) {
            return $this->sendError('Order not found', null, 404);
        }

        // التحقق من الصلاحيات (المشتري أو الأدمن فقط)
        /** @var \App\Models\User|null $currentUser */
        $currentUser = auth()->user();
        $isOwnerOrAdmin = auth()->id() === $order->user_id || ($currentUser && $currentUser->isAdmin());

        // التحقق من أن الطلب لم يتم إتمامه بعد
        if (!in_array($order->status, ['pending', 'confirmed'])) {
            return $this->sendError('Too late to cancel', null, 400);
        }

        // استرداد الأموال إذا كانت محتجزة
        if ($order->payment && $order->payment->status === 'pending') {
            WalletService::move(
                $order->user,
                'refund',
                (float) $order->payment->amount,
                'order',
                $order->id,
                'Order cancelled — refund'
            );
            $order->payment->update(['status' => 'refunded']);
        }

        $order->update(['status' => 'cancelled']);

        // إعادة المنتج للمخزون (إلا إذا كان Order model بيعملها تلقائياً في boot())
        // ⚠️ إذا كان عندك restock تلقائي في Order model، احذف السطور التالية
        $product = $order->product;
        $product->increment('quantity', $order->quantity);
        if ($product->status === '0') {
            $product->status = '1';
            $product->save();
        }

        // إشعار المشتري
        Notify::send(
            $order->user_id,
            'order_refunded',
            'Order cancelled',
            'EGP ' . number_format($order->payment->amount ?? 0, 2) . ' returned to your wallet.',
            'order',
            $order->id
        );

        // إشعار البائع
        Notify::send(
            $product->owner_id,
            'order_refunded',
            'Order cancelled',
            null,
            'order',
            $order->id
        );

        return $this->sendResponse(null, 'Order cancelled and refunded');
    }


}