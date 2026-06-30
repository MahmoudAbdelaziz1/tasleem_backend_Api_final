<?php
// app/Http/Controllers/Api/CartItemController.php

namespace App\Http\Controllers\Api;

use App\Models\CartItem;
use App\Http\Resources\CartItemResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Api\LogController;  

class CartItemController extends BaseController
{
    public function index(Request $request)
    {
        $query = CartItem::with(['user', 'product']);

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $items = $query->paginate($request->get('per_page', 15));

        
        LogController::addLog(
            userId: auth()->id(),
            actionType: 'VIEW',
            actionName: 'view_cart_items',
            module: 'cart',
            entityId: null,
            oldData: null,
            newData: ['filters' => $request->only(['user_id'])],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            status: 'success',
            message: 'User viewed cart items list'
        );

        return $this->sendPaginated(
            $items,
            CartItemResource::collection($items),
            'Cart items retrieved successfully'
        );
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'rental_start_date' => 'nullable|date',
            'rental_end_date' => 'nullable|date|after:rental_start_date',
            'item_type' => 'required|in:purchase,rental',
        ]);

        if ($validator->fails()) {
      
            LogController::addLog(
                userId: auth()->id() ?? $request->user_id,
                actionType: 'ERROR',
                actionName: 'cart_add_failed',
                module: 'cart',
                entityId: null,
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

        // Check if item already in cart
        $existing = CartItem::where('user_id', $request->user_id)
            ->where('product_id', $request->product_id)
            ->where('item_type', $request->item_type)
            ->first();

        if ($existing) {
            $existing->increment('quantity', $request->quantity);
            
            
            LogController::addLog(
                userId: $request->user_id,
                actionType: 'UPDATE',
                actionName: 'cart_item_quantity_updated',
                module: 'cart',
                entityId: $existing->id,
                oldData: ['quantity' => $existing->quantity - $request->quantity],
                newData: ['quantity' => $existing->quantity],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
                status: 'success',
                message: 'Cart item quantity updated: Product #' . $request->product_id . ' + ' . $request->quantity
            );
            
            return $this->sendResponse(
                new CartItemResource($existing),
                'Cart item updated successfully'
            );
        }

        $item = CartItem::create($request->all());

        
        LogController::addLog(
            userId: $item->user_id,
            actionType: 'CREATE',
            actionName: 'cart_item_add',
            module: 'cart',
            entityId: $item->id,
            oldData: null,
            newData: $item->toArray(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            status: 'success',
            message: 'Item added to cart: Product #' . $item->product_id . ' (Qty: ' . $item->quantity . ', Type: ' . $item->item_type . ')'
        );

        return $this->sendResponse(
            new CartItemResource($item),
            'Item added to cart successfully',
            201
        );
    }

    public function show($id)
    {
        $item = CartItem::with(['user', 'product'])->find($id);

        if (!$item) {
            
            LogController::addLog(
                userId: auth()->id(),
                actionType: 'ERROR',
                actionName: 'cart_item_not_found',
                module: 'cart',
                entityId: $id,
                oldData: null,
                newData: null,
                ipAddress: request()->ip(),
                userAgent: request()->userAgent(),
                status: 'failed',
                message: 'Attempted to view non-existent cart item #' . $id,
                errorCode: 404
            );
            return $this->sendError('Cart item not found');
        }

       
        LogController::addLog(
            userId: auth()->id(),
            actionType: 'VIEW',
            actionName: 'view_cart_item_details',
            module: 'cart',
            entityId: $item->id,
            oldData: null,
            newData: null,
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
            status: 'success',
            message: 'User viewed cart item #' . $item->id . ' for product: ' . ($item->product->name ?? 'Unknown')
        );

        return $this->sendResponse(
            new CartItemResource($item),
            'Cart item retrieved successfully'
        );
    }

    public function update(Request $request, $id)
    {
        $item = CartItem::find($id);

        if (!$item) {
           
            LogController::addLog(
                userId: auth()->id(),
                actionType: 'ERROR',
                actionName: 'cart_item_update_failed',
                module: 'cart',
                entityId: $id,
                oldData: null,
                newData: null,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
                status: 'failed',
                message: 'Attempted to update non-existent cart item #' . $id,
                errorCode: 404
            );
            return $this->sendError('Cart item not found');
        }

        $validator = Validator::make($request->all(), [
            'quantity' => 'sometimes|integer|min:1',
            'rental_start_date' => 'nullable|date',
            'rental_end_date' => 'nullable|date|after:rental_start_date',
        ]);

        if ($validator->fails()) {
          
            LogController::addLog(
                userId: auth()->id(),
                actionType: 'ERROR',
                actionName: 'cart_item_update_validation_failed',
                module: 'cart',
                entityId: $item->id,
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

        $oldData = $item->toArray();
        $item->update($request->all());

        LogController::addLog(
            userId: auth()->id(),
            actionType: 'UPDATE',
            actionName: 'cart_item_update',
            module: 'cart',
            entityId: $item->id,
            oldData: $oldData,
            newData: $item->fresh()->toArray(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            status: 'success',
            message: 'Cart item #' . $item->id . ' updated - New quantity: ' . $item->quantity
        );

        return $this->sendResponse(
            new CartItemResource($item),
            'Cart item updated successfully'
        );
    }

    public function destroy($id)
    {
        $item = CartItem::find($id);

        if (!$item) {
         
            LogController::addLog(
                userId: auth()->id(),
                actionType: 'ERROR',
                actionName: 'cart_item_delete_failed',
                module: 'cart',
                entityId: $id,
                oldData: null,
                newData: null,
                ipAddress: request()->ip(),
                userAgent: request()->userAgent(),
                status: 'failed',
                message: 'Attempted to delete non-existent cart item #' . $id,
                errorCode: 404
            );
            return $this->sendError('Cart item not found');
        }

        $oldData = $item->toArray();
        $productName = $item->product->name ?? 'Unknown';
        $item->delete();

        LogController::addLog(
            userId: auth()->id(),
            actionType: 'DELETE',
            actionName: 'cart_item_delete',
            module: 'cart',
            entityId: $id,
            oldData: $oldData,
            newData: null,
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
            status: 'success',
            message: 'Cart item deleted: Product "' . $productName . '" removed from cart'
        );

        return $this->sendResponse(null, 'Item removed from cart successfully');
    }

    /**
     * Clear user's cart
     */
    public function clear($user_id)  
    {
        $user = \App\Models\User::find($user_id);
        
        if (!$user) {
           
            LogController::addLog(
                userId: auth()->id(),
                actionType: 'ERROR',
                actionName: 'cart_clear_failed',
                module: 'cart',
                entityId: $user_id,
                oldData: null,
                newData: null,
                ipAddress: request()->ip(),
                userAgent: request()->userAgent(),
                status: 'failed',
                message: 'Attempted to clear cart for non-existent user #' . $user_id,
                errorCode: 404
            );
            return $this->sendError('User not found', [], 404);
        }
        
       
        $count = \App\Models\CartItem::where('user_id', $user_id)->count();
        
        \App\Models\CartItem::where('user_id', $user_id)->delete();
        
  
        LogController::addLog(
            userId: auth()->id(),
            actionType: 'DELETE',
            actionName: 'cart_clear',
            module: 'cart',
            entityId: $user_id,
            oldData: ['items_count' => $count],
            newData: null,
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
            status: 'success',
            message: 'Cart cleared for user #' . $user_id . ' - Removed ' . $count . ' items'
        );
        
        return $this->sendResponse(null, 'Cart cleared successfully');
    }
}