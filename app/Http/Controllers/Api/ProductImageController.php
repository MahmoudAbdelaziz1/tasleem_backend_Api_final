<?php
// app/Http/Controllers/Api/ProductImageController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductImage;
use App\Models\Product;
use App\Http\Resources\ProductImageResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ProductImageController extends BaseController
{
    /**
     * Display all images for a product
     */
    public function index(Request $request, $productId)
    {
        $product = Product::find($productId);
        
        if (!$product) {
            return $this->sendError('Product not found');
        }

        $images = ProductImage::where('product_id', $productId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->sendResponse(
            ProductImageResource::collection($images),
            'Product images retrieved successfully'
        );
    }

    /**
     * Upload new images for a product
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'images' => 'required|array|min:1|max:5',
            'images.*' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            'alt_text' => 'nullable|regex:/^[^<>{}]*$/|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        $productId = $request->product_id;
        $uploadedImages = [];

        foreach ($request->file('images') as $image) {
            $path = $image->store('products/' . $productId, 'public');

            $productImage = ProductImage::create([
                'product_id' => $productId,
                'image_url' => $path,
                'alt_text' => $request->alt_text ?? $image->getClientOriginalName(),
            ]);

            $uploadedImages[] = $productImage;
        }

        return $this->sendResponse(
            ProductImageResource::collection($uploadedImages),
            count($uploadedImages) . ' images uploaded successfully',
            201
        );
    }

    /**
     * Upload single image
     */
    public function uploadSingle(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            'alt_text' => 'nullable|regex:/^[^<>{}]*$/|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        $productId = $request->product_id;
        $path = $request->file('image')->store('products/' . $productId, 'public');

        $productImage = ProductImage::create([
            'product_id' => $productId,
            'image_url' => $path,
            'alt_text' => $request->alt_text ?? $request->file('image')->getClientOriginalName(),
        ]);

        return $this->sendResponse(
            new ProductImageResource($productImage),
            'Image uploaded successfully',
            201
        );
    }

    /**
     * Display specific image
     */
    public function show($id)
    {
        $image = ProductImage::find($id);

        if (!$image) {
            return $this->sendError('Image not found');
        }

        return $this->sendResponse(
            new ProductImageResource($image),
            'Image retrieved successfully'
        );
    }

    /**
     * Update image details
     */
    public function update(Request $request, $id)
    {
        $image = ProductImage::find($id);

        if (!$image) {
            return $this->sendError('Image not found');
        }

        $validator = Validator::make($request->all(), [
            'alt_text' => 'nullable|regex:/^[^<>{}]*$/|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        $image->update($request->only(['alt_text']));

        return $this->sendResponse(
            new ProductImageResource($image),
            'Image updated successfully'
        );
    }

    /**
     * Delete image
     */
    public function destroy(Request $request, $id)
    {
        $image = ProductImage::find($id);

        if (!$image) {
            return $this->sendError('Image not found');
        }

        $rawUrl = $image->getAttributes()['image_url'] ?? null;
        
        // Delete file from storage ONLY if it's a local file (not external URL)
        if ($rawUrl && !str_starts_with($rawUrl, 'http://') && !str_starts_with($rawUrl, 'https://')) {
            if (Storage::disk('public')->exists($rawUrl)) {
                Storage::disk('public')->delete($rawUrl);
            }
        }

        $image->delete();

        return $this->sendResponse(null, 'Image deleted successfully');
    }

    /**
     * Delete multiple images
     */
    public function destroyMultiple(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image_ids' => 'required|array|min:1',
            'image_ids.*' => 'required|integer|exists:product_images,image_id',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        $deletedCount = 0;
        $productId = null;

        foreach ($request->image_ids as $imageId) {
            $image = ProductImage::find($imageId);
            if ($image) {
                $productId = $image->product_id;
                
                $rawUrl = $image->getAttributes()['image_url'] ?? null;
                
                if ($rawUrl && !str_starts_with($rawUrl, 'http://') && !str_starts_with($rawUrl, 'https://')) {
                    if (Storage::disk('public')->exists($rawUrl)) {
                        Storage::disk('public')->delete($rawUrl);
                    }
                }
                
                $image->delete();
                $deletedCount++;
            }
        }

        return $this->sendResponse(
            ['deleted_count' => $deletedCount],
            $deletedCount . ' images deleted successfully'
        );
    }

    /**
     * Add image from URL (external link)
     */
    public function addFromUrl(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'image_url' => 'required|url|max:500',
            'alt_text' => 'nullable|regex:/^[^<>{}]*$/|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        $productImage = ProductImage::create([
            'product_id' => $request->product_id,
            'image_url' => $request->image_url,
            'alt_text' => $request->alt_text ?? 'Image from URL',
        ]);

        return $this->sendResponse(
            new ProductImageResource($productImage),
            'Image added successfully from URL',
            201
        );
    }

    /**
     * Add multiple images from URLs
     */
    public function addMultipleFromUrls(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'image_urls' => 'required|array|min:1|max:10',
            'image_urls.*' => 'required|url|max:500',
            'alt_texts' => 'nullable|array',
            'alt_texts.*' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        $productId = $request->product_id;
        $uploadedImages = [];

        foreach ($request->image_urls as $index => $imageUrl) {
            $altText = null;
            if ($request->has('alt_texts') && isset($request->alt_texts[$index])) {
                $altText = $request->alt_texts[$index];
            } else {
                $altText = 'Image from URL ' . ($index + 1);
            }
            
            $productImage = ProductImage::create([
                'product_id' => $productId,
                'image_url' => $imageUrl,
                'alt_text' => $altText,
            ]);
            
            $uploadedImages[] = $productImage;
        }

        return $this->sendResponse(
            ProductImageResource::collection($uploadedImages),
            count($uploadedImages) . ' images added successfully from URLs',
            201
        );
    }
}