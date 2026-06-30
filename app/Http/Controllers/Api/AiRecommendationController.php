<?php
// app/Http/Controllers/Api/AiRecommendationController.php

namespace App\Http\Controllers\Api;

use App\Models\AiRecommendation;
use App\Http\Resources\AiRecommendationResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Api\LogController;  

class AiRecommendationController extends BaseController
{
    public function index(Request $request)
    {
        $query = AiRecommendation::with(['user', 'product']);

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('algorithm_type')) {
            $query->where('algorithm_type', $request->algorithm_type);
        }

        // Get only valid recommendations
        $query->where(function($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });

        $recommendations = $query->orderBy('score', 'desc')
            ->paginate($request->get('per_page', 20));

       
        LogController::addLog(
            userId: auth()->id(),
            actionType: 'VIEW',
            actionName: 'view_recommendations',
            module: 'recommendations',
            entityId: null,
            oldData: null,
            newData: ['filters' => $request->only(['user_id', 'algorithm_type'])],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            status: 'success',
            message: 'User viewed AI recommendations list'
        );

        return $this->sendPaginated(
            $recommendations,
            AiRecommendationResource::collection($recommendations),
            'Recommendations retrieved successfully'
        );
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|exists:users,id',
            'product_id' => 'required|exists:products,id',
            'score' => 'required|numeric|min:0|max:1',
            'algorithm_type' => 'required|in:collaborative,content,hybrid,popularity,location',
            'reason' => 'nullable|regex:/^[^<>{}]*$/|string',
            'metadata' => 'nullable|array',
            'expires_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            
            LogController::addLog(
                userId: auth()->id(),
                actionType: 'ERROR',
                actionName: 'recommendation_create_failed',
                module: 'recommendations',
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

        $recommendation = AiRecommendation::updateOrCreate(
            [
                'user_id' => $request->user_id,
                'product_id' => $request->product_id,
                'algorithm_type' => $request->algorithm_type,
            ],
            $request->all()
        );

        
        LogController::addLog(
            userId: $recommendation->user_id ?? auth()->id(),
            actionType: 'CREATE',
            actionName: 'recommendation_save',
            module: 'recommendations',
            entityId: $recommendation->id,
            oldData: null,
            newData: $recommendation->toArray(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            status: 'success',
            message: 'Recommendation saved: Product #' . $recommendation->product_id . ' - Score: ' . $recommendation->score . ' (' . $recommendation->algorithm_type . ')'
        );

        return $this->sendResponse(
            new AiRecommendationResource($recommendation),
            'Recommendation saved successfully',
            201
        );
    }

    public function show($id)
    {
        $recommendation = AiRecommendation::with(['user', 'product'])->find($id);

        if (!$recommendation) {
          
            LogController::addLog(
                userId: auth()->id(),
                actionType: 'ERROR',
                actionName: 'recommendation_not_found',
                module: 'recommendations',
                entityId: $id,
                oldData: null,
                newData: null,
                ipAddress: request()->ip(),
                userAgent: request()->userAgent(),
                status: 'failed',
                message: 'Attempted to view non-existent recommendation #' . $id,
                errorCode: 404
            );
            return $this->sendError('Recommendation not found');
        }

      
        LogController::addLog(
            userId: auth()->id(),
            actionType: 'VIEW',
            actionName: 'view_recommendation_details',
            module: 'recommendations',
            entityId: $recommendation->id,
            oldData: null,
            newData: null,
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
            status: 'success',
            message: 'User viewed recommendation #' . $recommendation->id . ' for product: ' . ($recommendation->product->name ?? 'Unknown')
        );

        return $this->sendResponse(
            new AiRecommendationResource($recommendation),
            'Recommendation retrieved successfully'
        );
    }

    public function update(Request $request, $id)
    {
        $recommendation = AiRecommendation::find($id);

        if (!$recommendation) {
            
            LogController::addLog(
                userId: auth()->id(),
                actionType: 'ERROR',
                actionName: 'recommendation_update_failed',
                module: 'recommendations',
                entityId: $id,
                oldData: null,
                newData: null,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
                status: 'failed',
                message: 'Attempted to update non-existent recommendation #' . $id,
                errorCode: 404
            );
            return $this->sendError('Recommendation not found');
        }

        $validator = Validator::make($request->all(), [
            'score' => 'sometimes|numeric|min:0|max:1',
            'reason' => 'nullable|regex:/^[^<>{}]*$/|string',
            'metadata' => 'nullable|array',
            'expires_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
           
            LogController::addLog(
                userId: auth()->id(),
                actionType: 'ERROR',
                actionName: 'recommendation_update_validation_failed',
                module: 'recommendations',
                entityId: $recommendation->id,
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

        $oldData = $recommendation->toArray();
        $recommendation->update($request->all());

        
        LogController::addLog(
            userId: auth()->id(),
            actionType: 'UPDATE',
            actionName: 'recommendation_update',
            module: 'recommendations',
            entityId: $recommendation->id,
            oldData: $oldData,
            newData: $recommendation->fresh()->toArray(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            status: 'success',
            message: 'Recommendation #' . $recommendation->id . ' updated - New score: ' . $recommendation->score
        );

        return $this->sendResponse(
            new AiRecommendationResource($recommendation),
            'Recommendation updated successfully'
        );
    }

    public function destroy($id)
    {
        $recommendation = AiRecommendation::find($id);

        if (!$recommendation) {
         
            LogController::addLog(
                userId: auth()->id(),
                actionType: 'ERROR',
                actionName: 'recommendation_delete_failed',
                module: 'recommendations',
                entityId: $id,
                oldData: null,
                newData: null,
                ipAddress: request()->ip(),
                userAgent: request()->userAgent(),
                status: 'failed',
                message: 'Attempted to delete non-existent recommendation #' . $id,
                errorCode: 404
            );
            return $this->sendError('Recommendation not found');
        }

        $oldData = $recommendation->toArray();
        $recommendationName = 'Recommendation #' . $recommendation->id . ' for product: ' . ($recommendation->product->name ?? 'Unknown');
        $recommendation->delete();

       
        LogController::addLog(
            userId: auth()->id(),
            actionType: 'DELETE',
            actionName: 'recommendation_delete',
            module: 'recommendations',
            entityId: $id,
            oldData: $oldData,
            newData: null,
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
            status: 'success',
            message: 'Recommendation deleted: ' . $recommendationName
        );

        return $this->sendResponse(null, 'Recommendation deleted successfully');
    }
}