<?php
// app/Http/Controllers/Api/WishlistController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use App\Models\Product;
use App\Http\Resources\WishlistResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Log;

class WishlistController extends BaseController
{
    /**
     * Display a listing of user's wishlist
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        $wishlist = Wishlist::with('product')
            ->where('user_id', $request->user_id)
            ->paginate($request->get('per_page', 15));

        
        LogController::addLog(
            auth()->id() ?? $request->user_id,
            'VIEW',
            'view_wishlist',
            'wishlist',
            null,
            null,
            null,
            $request->ip(),
            $request->userAgent(),
            'success',
            'User viewed their wishlist'
        );

        return $this->sendPaginated(
            $wishlist,
            WishlistResource::collection($wishlist),
            'Wishlist retrieved successfully'
        );
    }

    /**
     * Add product to wishlist
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'product_id' => 'required|exists:products,id',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

       
        $existing = Wishlist::where('user_id', $request->user_id)
            ->where('product_id', $request->product_id)
            ->first();

        if ($existing) {
            return $this->sendError('Product already in wishlist', [], 400);
        }

        $wishlist = Wishlist::create([
            'user_id' => $request->user_id,
            'product_id' => $request->product_id,
        ]);

        
        LogController::addLog(
            $request->user_id,
            'CREATE',
            'add_to_wishlist',
            'wishlist',
            $wishlist->wishlist_id,
            null,
            ['product_id' => $request->product_id],
            $request->ip(),
            $request->userAgent(),
            'success',
            'Product added to wishlist'
        );

        return $this->sendResponse(
            new WishlistResource($wishlist->load('product')),
            'Product added to wishlist successfully',
            201
        );
    }

    /**
     * Remove product from wishlist
     */
    public function destroy(Request $request, $id)
    {
        $wishlist = Wishlist::find($id);

        if (!$wishlist) {
            return $this->sendError('Wishlist item not found');
        }

       
        $oldData = $wishlist->toArray();
        $userId = $wishlist->user_id;
        $productId = $wishlist->product_id;

        $wishlist->delete();

       
        LogController::addLog(
            $userId,
            'DELETE',
            'remove_from_wishlist',
            'wishlist',
            $id,
            $oldData,
            null,
            $request->ip(),
            $request->userAgent(),
            'success',
            'Product removed from wishlist'
        );

        return $this->sendResponse(null, 'Product removed from wishlist successfully');
    }

    /**
     * Clear entire wishlist for a user
     */
    public function clear(Request $request, $userId)
    {
        $validator = Validator::make(['user_id' => $userId], [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        $count = Wishlist::where('user_id', $userId)->count();
        Wishlist::where('user_id', $userId)->delete();

        
        LogController::addLog(
            $userId,
            'DELETE',
            'clear_wishlist',
            'wishlist',
            null,
            null,
            null,
            $request->ip(),
            $request->userAgent(),
            'success',
            "User cleared their wishlist (removed {$count} items)"
        );

        return $this->sendResponse(null, 'Wishlist cleared successfully');
    }

    /**
     * Check if product is in user's wishlist
     */
    public function check(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'product_id' => 'required|exists:products,id',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        $exists = Wishlist::where('user_id', $request->user_id)
            ->where('product_id', $request->product_id)
            ->exists();

        return $this->sendResponse(
            ['in_wishlist' => $exists],
            'Check completed successfully'
        );
    }
}