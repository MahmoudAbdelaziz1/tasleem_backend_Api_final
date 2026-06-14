<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\Product;
use App\Models\User;
use App\Services\Notify;
use App\Services\OrderService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use RuntimeException;

class OfferController extends BaseController
{
    /**
     * عرض العروض (للمشتري أو البائع).
     * GET /api/offers?buyer_id= أو ?seller_id=
     */
    public function index(Request $request)
    {
        $query = Offer::with(['product.images', 'buyer', 'seller']);

        if ($request->has('buyer_id')) {
            $query->where('buyer_id', $request->buyer_id);
        }

        if ($request->has('seller_id')) {
            $query->where('seller_id', $request->seller_id);
        }

        $offers = $query->latest()->paginate($request->get('per_page', 15));

        return $this->sendPaginated(
            $offers,
            \App\Http\Resources\OfferResource::collection($offers),
            'Offers retrieved successfully'
        );
    }

    /**
     * إرسال عرض على منتج.
     * POST /api/offers
     * Body: { "product_id": 1, "amount": 20000 }
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'amount'     => 'required|numeric|min:1',
        ]);

        $product = Product::findOrFail($request->product_id);

        // منع إرسال عرض على منتجك الخاص
        if ($product->owner_id === auth()->id()) {
            return $this->sendError('Cannot offer on your own item', null, 400);
        }

        // التحقق من أن المنتج للبيع (sale) وليس للإيجار
        if ($product->type !== 'sale') {
            return $this->sendError('Offers are only allowed on sale items', null, 400);
        }

        $offer = Offer::create([
            'product_id' => $product->id,
            'buyer_id'   => auth()->id(),
            'seller_id'  => $product->owner_id,
            'amount'     => $request->amount,
            'status'     => 'pending',
        ]);

        // إشعار البائع
        Notify::send(
            $product->owner_id,
            'offer_received',
            'New offer received',
            'Offer of EGP ' . number_format($request->amount, 2) . ' on "' . $product->name . '".',
            'offer',
            $offer->id
        );

        return $this->sendResponse($offer, 'Offer sent successfully', 201);
    }

    /**
     * البائع يقبل العرض (ينشئ طلب confirmed).
     * POST /api/offers/{id}/accept
     */
    public function accept($id)
    {
        $offer = Offer::with('product')->findOrFail($id);

        // التحقق من أن المستخدم هو البائع
        if (auth()->id() !== $offer->seller_id) {
            return $this->sendError('Not your offer', null, 403);
        }

        // التحقق من حالة العرض
        if ($offer->status !== 'pending') {
            return $this->sendError('Offer already handled', null, 400);
        }

        $buyer = User::find($offer->buyer_id);
        $charge = (float)$offer->amount + (float)config('tasleem.delivery_fee');

        // التحقق من رصيد المشتري
        if ((float)$buyer->wallet_balance < $charge) {
            return $this->sendError('Buyer has insufficient funds. Please ask buyer to top up.', null, 400);
        }

        try {
            // تحديث حالة العرض
            $offer->update(['status' => 'accepted']);

            // إنشاء الطلب باستخدام OrderService (بالحالة confirmed)
            $order = OrderService::placeOrder(
                $offer->buyer_id,
                $offer->product_id,
                1, // الكمية دائماً 1 في العروض
                (float)$offer->amount,
                'confirmed' // ← مهم جداً: الطلب يبدأ بحالة confirmed
            );

            // إشعار المشتري
            Notify::send(
                $offer->buyer_id,
                'offer_accepted',
                'Your offer was accepted!',
                'Your offer of EGP ' . number_format($offer->amount, 2) . ' on "' . $offer->product->name . '" was accepted. EGP ' . number_format($charge, 2) . ' has been held.',
                'order',
                $order->order_id
            );

            return $this->sendResponse([
                'offer' => $offer->fresh(),
                'order' => $order->load('payment'),
            ], 'Offer accepted — order created and money held');

        } catch (RuntimeException $e) {
            return $this->sendError($e->getMessage(), null, 400);
        }
    }

    /**
     * البائع يرفض العرض.
     * POST /api/offers/{id}/reject
     */
    public function reject($id)
    {
        $offer = Offer::findOrFail($id);

        // التحقق من أن المستخدم هو البائع
        if (auth()->id() !== $offer->seller_id) {
            return $this->sendError('Not your offer', null, 403);
        }

        if ($offer->status !== 'pending') {
            return $this->sendError('Offer already handled', null, 400);
        }

        $offer->update(['status' => 'rejected']);

        // إشعار المشتري
        Notify::send(
            $offer->buyer_id,
            'offer_rejected',
            'Offer declined',
            'Your offer of EGP ' . number_format($offer->amount, 2) . ' was declined by the seller.',
            'offer',
            $offer->id
        );

        return $this->sendResponse($offer->fresh(), 'Offer rejected');
    }
}