<?php
// app/Http/Controllers/Api/ProductController.php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Http\Resources\ProductResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductController extends BaseController
{
    /**
     * Display a listing of products
     */
    public function index(Request $request)
    {
        $query = Product::with(['category', 'owner', 'images']);

        
        if ($request->filled('source')) {
            $isAdmin = $request->source === 'tasleem';
            $query->whereHas('owner', fn ($q) =>
                $isAdmin ? $q->where('role', 'admin') : $q->where('role', '!=', 'admin')
            );
        }        

        // فلتر IDs
        if ($request->has('ids')) {
            $ids = array_values(array_filter(array_map('intval', explode(',', $request->ids))));
            if (!empty($ids)) {
                $query->whereIn('id', $ids);
            }
        }

        
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('owner_id')) {
            $query->where('owner_id', $request->owner_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }
    
        if ($request->has('search')) {
            $query->where('name', 'LIKE', "%{$request->search}%");
        }
    
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

       
        $query->boostedFirst();

       
        $allowedSortFields = ['id', 'name', 'price', 'created_at', 'updated_at', 'view_count', 'rate', 'pay_count', 'addingToCart_count', 'quantity'];
        
        $sortField = $request->get('sort_by', 'created_at');
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'created_at';
        }
        
        $sortOrder = $request->get('sort_order', 'desc');
        $sortOrder = in_array(strtolower($sortOrder), ['asc', 'desc']) ? strtolower($sortOrder) : 'desc';
        
        $query->orderBy($sortField, $sortOrder);

        $products = $query->paginate($request->get('per_page', 15));

        return $this->sendPaginated(
            $products,
            ProductResource::collection($products),
            'Products retrieved successfully'
        );
    }

    /**
     * Store a newly created product
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|regex:/^[^<>{}]*$/|string|max:255',
            'description' => 'nullable|regex:/^[^<>{}]*$/|string',
            'price' => 'required|numeric|min:0',
            'category_id' => 'required|exists:categories,category_id',
            'owner_id' => 'required|exists:users,id',
            'quantity' => 'required|integer|min:1',
            'status' => 'required|in:1,0',
            'type' => 'required|in:sale,rental,both',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        $product = Product::create($request->all());

        \App\Models\Log::create([
            'user_id' => auth()->id(),
            'action_type' => 'CREATE',
            'action_name' => 'product_create',
            'module' => 'products',
            'entity_type' => 'product',
            'entity_id' => $product->id,
            'new_data' => $product->toArray(),
            'status' => 'success',
        ]);

        return $this->sendResponse(
            new ProductResource($product->load(['category', 'owner'])),
            'Product created successfully',
            201
        );
    }

    /**
     * Display the specified product
     */
    public function show($id)
    {
        $product = Product::with(['category', 'owner', 'images', 'reviews.user'])->find($id);

        if (!$product) {
            return $this->sendError('Product not found');
        }

        // Increment view count
        $product->increment('view_count');

        return $this->sendResponse(
            new ProductResource($product),
            'Product retrieved successfully'
        );
    }

    /**
     * Update the specified product
     */
    public function update(Request $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return $this->sendError('Product not found');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|regex:/^[^<>{}]*$/|string|max:255',
            'description' => 'nullable|regex:/^[^<>{}]*$/|string',
            'price' => 'sometimes|numeric|min:0',
            'category_id' => 'sometimes|exists:categories,category_id',
            'owner_id' => 'sometimes|exists:users,id',
            'quantity' => 'sometimes|integer|min:0',
             'status' => 'required|in:1,0',
            'type' => 'sometimes|in:sale,rental,both',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        $oldData = $product->toArray();
        $product->update($request->all());

        // Log the action
        \App\Models\Log::create([
            'user_id' => auth()->id(),
            'action_type' => 'UPDATE',
            'action_name' => 'product_update',
            'module' => 'products',
            'entity_type' => 'product',
            'entity_id' => $product->id,
            'old_data' => $oldData,
            'new_data' => $product->toArray(),
            'status' => 'success',
        ]);

        return $this->sendResponse(
            new ProductResource($product),
            'Product updated successfully'
        );
    }

    /**
     * Remove the specified product
     */
    public function destroy($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return $this->sendError('Product not found');
        }

        $product->delete();

        // Log the action
        \App\Models\Log::create([
            'user_id' => auth()->id(),
            'action_type' => 'DELETE',
            'action_name' => 'product_delete',
            'module' => 'products',
            'entity_type' => 'product',
            'entity_id' => $id,
            'status' => 'success',
        ]);

        return $this->sendResponse(null, 'Product deleted successfully');
    }

    /**
     * Get similar products
     */
    public function similar($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return $this->sendError('Product not found');
        }

        $similar = Product::where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('status', '1')
            ->with(['category', 'images'])
            ->limit(10)
            ->get();

        return $this->sendResponse(
            ProductResource::collection($similar),
            'Similar products retrieved successfully'
        );
    }








    public function boost(Request $request, $id)
    {
        $request->validate([
            'days' => 'required|integer|min:1|max:30',
        ]);

        $product = \App\Models\Product::findOrFail($id);

        
        if ($product->owner_id !== auth()->id()) {
            return $this->sendError('Not your product', null, 403);
        }

        $cost = (float)config('tasleem.boost_fee_per_day') * $request->days;

       
        try {
            \App\Services\WalletService::move(
                auth()->user(),
                'boost_fee',
                -$cost,
                'product',
                $product->id,
                'Listing boost for ' . $request->days . ' days'
            );
        } catch (\RuntimeException $e) {
            return $this->sendError($e->getMessage(), null, 402);
        }

        
        $product->update([
            'is_boosted'       => true,
            'boost_expires_at' => now()->addDays($request->days),
        ]);

       
        \App\Services\Notify::send(
            auth()->id(),
            'boost_activated',
            'Listing boosted',
            'Your listing "' . $product->name . '" has been boosted until ' . $product->boost_expires_at->toDayDateTimeString() . '.',
            'product',
            $product->id
        );

        return $this->sendResponse(
            new \App\Http\Resources\ProductResource($product),
            'Listing boosted successfully'
        );
    }


}