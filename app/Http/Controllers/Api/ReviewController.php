<?php
// app/Http/Controllers/Api/ReviewController.php

namespace App\Http\Controllers\Api;

use App\Models\Review;
use App\Http\Resources\ReviewResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Api\LogController;  

class ReviewController extends BaseController
{
    public function index(Request $request)
    {
        $query = Review::with(['user', 'product']);

        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('rating')) {
            $query->where('rating', $request->rating);
        }

        $reviews = $query->latest()->paginate($request->get('per_page', 15));

        
        LogController::addLog(
            userId: auth()->id(),
            actionType: 'VIEW',
            actionName: 'view_reviews',
            module: 'reviews',
            entityId: null,
            oldData: null,
            newData: ['filters' => $request->only(['product_id', 'user_id', 'rating'])],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            status: 'success',
            message: 'User viewed reviews list'
        );

        return $this->sendPaginated(
            $reviews,
            ReviewResource::collection($reviews),
            'Reviews retrieved successfully'
        );
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'user_id' => 'required|exists:users,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|regex:/^[^<>{}]*$/|string',
        ]);

        if ($validator->fails()) {
            
            LogController::addLog(
                userId: auth()->id() ?? $request->user_id,
                actionType: 'ERROR',
                actionName: 'review_create_failed',
                module: 'reviews',
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

        
        $existing = Review::where('product_id', $request->product_id)
            ->where('user_id', $request->user_id)
            ->first();

        if ($existing) {
            
            LogController::addLog(
                userId: $request->user_id,
                actionType: 'ERROR',
                actionName: 'review_create_failed',
                module: 'reviews',
                entityId: $request->product_id,
                oldData: null,
                newData: ['user_id' => $request->user_id, 'product_id' => $request->product_id],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
                status: 'failed',
                message: 'User #' . $request->user_id . ' already reviewed product #' . $request->product_id,
                errorCode: 400
            );
            return $this->sendError('User already reviewed this product');
        }

        $review = Review::create($request->all());

        
        LogController::addLog(
            userId: $review->user_id,
            actionType: 'CREATE',
            actionName: 'review_create',
            module: 'reviews',
            entityId: $review->id,
            oldData: null,
            newData: $review->toArray(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            status: 'success',
            message: 'Review created: #' . $review->id . ' for product: ' . ($review->product->name ?? 'Unknown') . ' - Rating: ' . $review->rating . '/5'
        );

        return $this->sendResponse(
            new ReviewResource($review->load(['user', 'product'])),
            'Review created successfully',
            201
        );
    }

    public function show($id)
    {
        $review = Review::with(['user', 'product'])->find($id);

        if (!$review) {
            
            LogController::addLog(
                userId: auth()->id(),
                actionType: 'ERROR',
                actionName: 'review_not_found',
                module: 'reviews',
                entityId: $id,
                oldData: null,
                newData: null,
                ipAddress: request()->ip(),
                userAgent: request()->userAgent(),
                status: 'failed',
                message: 'Attempted to view non-existent review #' . $id,
                errorCode: 404
            );
            return $this->sendError('Review not found');
        }

        
        LogController::addLog(
            userId: auth()->id(),
            actionType: 'VIEW',
            actionName: 'view_review_details',
            module: 'reviews',
            entityId: $review->id,
            oldData: null,
            newData: null,
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
            status: 'success',
            message: 'User viewed review #' . $review->id . ' for product: ' . ($review->product->name ?? 'Unknown')
        );

        return $this->sendResponse(
            new ReviewResource($review),
            'Review retrieved successfully'
        );
    }

    public function update(Request $request, $id)
    {
        $review = Review::find($id);

        if (!$review) {
            
            LogController::addLog(
                userId: auth()->id(),
                actionType: 'ERROR',
                actionName: 'review_update_failed',
                module: 'reviews',
                entityId: $id,
                oldData: null,
                newData: null,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
                status: 'failed',
                message: 'Attempted to update non-existent review #' . $id,
                errorCode: 404
            );
            return $this->sendError('Review not found');
        }

        $validator = Validator::make($request->all(), [
            'rating' => 'sometimes|integer|min:1|max:5',
            'comment' => 'nullable|regex:/^[^<>{}]*$/|string',
        ]);

        if ($validator->fails()) {
         
            LogController::addLog(
                userId: auth()->id(),
                actionType: 'ERROR',
                actionName: 'review_update_validation_failed',
                module: 'reviews',
                entityId: $review->id,
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

        $oldData = $review->toArray();
        $review->update($request->only(['rating', 'comment']));

   
        LogController::addLog(
            userId: auth()->id(),
            actionType: 'UPDATE',
            actionName: 'review_update',
            module: 'reviews',
            entityId: $review->id,
            oldData: $oldData,
            newData: $review->fresh()->toArray(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            status: 'success',
            message: 'Review #' . $review->id . ' updated - New rating: ' . $review->rating . '/5'
        );

        return $this->sendResponse(
            new ReviewResource($review),
            'Review updated successfully'
        );
    }

    public function destroy($id)
    {
        $review = Review::find($id);

        if (!$review) {
           
            LogController::addLog(
                userId: auth()->id(),
                actionType: 'ERROR',
                actionName: 'review_delete_failed',
                module: 'reviews',
                entityId: $id,
                oldData: null,
                newData: null,
                ipAddress: request()->ip(),
                userAgent: request()->userAgent(),
                status: 'failed',
                message: 'Attempted to delete non-existent review #' . $id,
                errorCode: 404
            );
            return $this->sendError('Review not found');
        }

        $oldData = $review->toArray();
        $reviewName = 'Review #' . $review->id . ' for product: ' . ($review->product->name ?? 'Unknown');
        $review->delete();

   
        LogController::addLog(
            userId: auth()->id(),
            actionType: 'DELETE',
            actionName: 'review_delete',
            module: 'reviews',
            entityId: $id,
            oldData: $oldData,
            newData: null,
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
            status: 'success',
            message: 'Review deleted: ' . $reviewName
        );

        return $this->sendResponse(null, 'Review deleted successfully');
    }
}