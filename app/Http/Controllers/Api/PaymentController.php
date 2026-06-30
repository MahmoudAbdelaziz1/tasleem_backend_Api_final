<?php
// app/Http/Controllers/Api/PaymentController.php

namespace App\Http\Controllers\Api;

use App\Models\Payment;
use App\Http\Resources\PaymentResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Api\LogController;  

class PaymentController extends BaseController
{
    public function index(Request $request)
    {
        $query = Payment::with(['user', 'order', 'rental']);

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $payments = $query->latest()->paginate($request->get('per_page', 15));

       
        LogController::addLog(
            userId: auth()->id(),
            actionType: 'VIEW',
            actionName: 'view_payments',
            module: 'payments',
            entityId: null,
            oldData: null,
            newData: ['filters' => $request->only(['user_id', 'status'])],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            status: 'success',
            message: 'User viewed payments list'
        );

        return $this->sendPaginated(
            $payments,
            PaymentResource::collection($payments),
            'Payments retrieved successfully'
        );
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'nullable|exists:orders,order_id',
            'rental_id' => 'nullable|exists:rentals,rental_id',
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:credit_card,paypal,bank_transfer,cash',
            'transaction_id' => 'nullable|regex:/^[^<>{}]*$/|string|unique:payments',
        ]);

        if ($validator->fails()) {
          
            LogController::addLog(
                userId: auth()->id() ?? $request->user_id,
                actionType: 'ERROR',
                actionName: 'payment_create_failed',
                module: 'payments',
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

        if (!$request->order_id && !$request->rental_id) {
           
            LogController::addLog(
                userId: auth()->id() ?? $request->user_id,
                actionType: 'ERROR',
                actionName: 'payment_create_failed',
                module: 'payments',
                entityId: null,
                oldData: null,
                newData: $request->all(),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
                status: 'failed',
                message: 'Either order_id or rental_id is required',
                errorCode: 400
            );
            return $this->sendError('Either order_id or rental_id is required');
        }

        $payment = Payment::create($request->all());

      
        LogController::addLog(
            userId: $payment->user_id,
            actionType: 'CREATE',
            actionName: 'payment_create',
            module: 'payments',
            entityId: $payment->payment_id,
            oldData: null,
            newData: $payment->toArray(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            status: 'success',
            message: 'Payment created: #' . $payment->payment_id . ' - Amount: $' . number_format($payment->amount, 2) . ' via ' . $payment->payment_method
        );

        return $this->sendResponse(
            new PaymentResource($payment->load(['user', 'order', 'rental'])),
            'Payment created successfully',
            201
        );
    }

    public function show($id)
    {
        $payment = Payment::with(['user', 'order', 'rental'])->find($id);

        if (!$payment) {
           
            LogController::addLog(
                userId: auth()->id(),
                actionType: 'ERROR',
                actionName: 'payment_not_found',
                module: 'payments',
                entityId: $id,
                oldData: null,
                newData: null,
                ipAddress: request()->ip(),
                userAgent: request()->userAgent(),
                status: 'failed',
                message: 'Attempted to view non-existent payment #' . $id,
                errorCode: 404
            );
            return $this->sendError('Payment not found');
        }

       
        LogController::addLog(
            userId: auth()->id(),
            actionType: 'VIEW',
            actionName: 'view_payment_details',
            module: 'payments',
            entityId: $payment->payment_id,
            oldData: null,
            newData: null,
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
            status: 'success',
            message: 'User viewed payment #' . $payment->payment_id . ' - Amount: $' . number_format($payment->amount, 2)
        );

        return $this->sendResponse(
            new PaymentResource($payment),
            'Payment retrieved successfully'
        );
    }

    public function update(Request $request, $id)
    {
        $payment = Payment::find($id);

        if (!$payment) {
            
            LogController::addLog(
                userId: auth()->id(),
                actionType: 'ERROR',
                actionName: 'payment_update_failed',
                module: 'payments',
                entityId: $id,
                oldData: null,
                newData: null,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
                status: 'failed',
                message: 'Attempted to update non-existent payment #' . $id,
                errorCode: 404
            );
            return $this->sendError('Payment not found');
        }

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:pending,completed,failed,refunded',
            'transaction_id' => 'nullable|regex:/^[^<>{}]*$/|string|unique:payments,transaction_id,' . $id . ',payment_id',
        ]);

        if ($validator->fails()) {
          
            LogController::addLog(
                userId: auth()->id(),
                actionType: 'ERROR',
                actionName: 'payment_update_validation_failed',
                module: 'payments',
                entityId: $payment->payment_id,
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

        $oldData = $payment->toArray();
        $payment->update($request->only(['status', 'transaction_id']));

      
        LogController::addLog(
            userId: auth()->id(),
            actionType: 'UPDATE',
            actionName: 'payment_update',
            module: 'payments',
            entityId: $payment->payment_id,
            oldData: $oldData,
            newData: $payment->fresh()->toArray(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            status: 'success',
            message: 'Payment #' . $payment->payment_id . ' updated - Status: ' . $payment->status
        );

        return $this->sendResponse(
            new PaymentResource($payment),
            'Payment updated successfully'
        );
    }

    public function destroy($id)
    {
        $payment = Payment::find($id);

        if (!$payment) {
           
            LogController::addLog(
                userId: auth()->id(),
                actionType: 'ERROR',
                actionName: 'payment_delete_failed',
                module: 'payments',
                entityId: $id,
                oldData: null,
                newData: null,
                ipAddress: request()->ip(),
                userAgent: request()->userAgent(),
                status: 'failed',
                message: 'Attempted to delete non-existent payment #' . $id,
                errorCode: 404
            );
            return $this->sendError('Payment not found');
        }

        $oldData = $payment->toArray();
        $paymentName = 'Payment #' . $payment->payment_id . ' - $' . number_format($payment->amount, 2);
        $payment->delete();

      
        LogController::addLog(
            userId: auth()->id(),
            actionType: 'DELETE',
            actionName: 'payment_delete',
            module: 'payments',
            entityId: $id,
            oldData: $oldData,
            newData: null,
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
            status: 'success',
            message: 'Payment deleted: ' . $paymentName
        );

        return $this->sendResponse(null, 'Payment deleted successfully');
    }
}