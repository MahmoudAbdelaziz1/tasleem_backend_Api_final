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

 
    public function store(Request $request)
    {
        $request->validate([
            'product_id'     => 'required|exists:products,id',
            'amount'         => 'required|numeric|min:1',
            'payment_method' => 'sometimes|in:wallet,cash', 
        ]);

        $product = Product::findOrFail($request->product_id);

       
        if ($product->owner_id === auth()->id()) {
            return $this->sendError('Cannot offer on your own item', null, 400);
        }

        
        if ($product->type !== 'sale') {
            return $this->sendError('Offers are only allowed on sale items', null, 400);
        }

        
        $method = $request->input('payment_method', 'cash');

        
        if ($method === 'wallet') {
            $charge = (float)$request->amount + (float)config('tasleem.delivery_fee');
            if ((float)auth()->user()->wallet_balance < $charge) {
                return $this->sendError(
                    'Not enough wallet balance for a wallet offer — top up or use Cash on Delivery.',
                    null,
                    400
                );
            }
        }

        
        $offer = Offer::create([
            'product_id'     => $product->id,
            'buyer_id'       => auth()->id(),
            'seller_id'      => $product->owner_id,
            'amount'         => $request->amount,
            'payment_method' => $method, 
            'status'         => 'pending',
        ]);

       
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


    public function accept($id)
    {
        $offer = Offer::with('product')->findOrFail($id);

        
        if (auth()->id() !== $offer->seller_id) {
            return $this->sendError('Not your offer', null, 403);
        }

        
        if ($offer->status !== 'pending') {
            return $this->sendError('Offer already handled', null, 400);
        }

        $buyer = User::find($offer->buyer_id);

        
        $method = $offer->payment_method ?? 'cash';

        
        if ($method === 'wallet') {
            $charge = (float)$offer->amount + (float)config('tasleem.delivery_fee');
            if ((float)$buyer->wallet_balance < $charge) {
                
                $method = 'cash';
            }
        }

        try {
            
            $offer->update(['status' => 'accepted']);

            
            $order = OrderService::placeOrder(
                $offer->buyer_id,
                $offer->product_id,
                1,
                (float)$offer->amount,
                'confirmed',
                $method 
            );

          
            $charge = (float)$offer->amount + (float)config('tasleem.delivery_fee');
            $message = $method === 'wallet'
                ? 'Your offer of EGP ' . number_format($offer->amount, 2) . ' on "' . $order->product->name . '" was accepted. EGP ' . number_format($charge, 2) . ' has been held.'
                : 'Your offer of EGP ' . number_format($offer->amount, 2) . ' on "' . $order->product->name . '" was accepted. Please pay EGP ' . number_format($charge, 2) . ' upon delivery.';

            
            Notify::send(
                $offer->buyer_id,
                'offer_accepted',
                'Your offer was accepted!',
                $message,
                'order',
                $order->order_id
            );

            return $this->sendResponse([
                'offer' => $offer->fresh(),
                'order' => $order->load('payment'),
            ], 'Offer accepted — order created' . ($method === 'wallet' ? ' and money held' : ''));

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

        if (auth()->id() !== $offer->seller_id) {
            return $this->sendError('Not your offer', null, 403);
        }

        if ($offer->status !== 'pending') {
            return $this->sendError('Offer already handled', null, 400);
        }

        $offer->update(['status' => 'rejected']);

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