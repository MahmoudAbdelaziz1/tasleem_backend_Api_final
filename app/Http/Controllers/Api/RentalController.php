<?php

namespace App\Http\Controllers\Api;

use App\Models\Rental;
use App\Models\Product;
use App\Models\User;
use App\Http\Resources\RentalResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Http\Controllers\Api\LogController;
use App\Services\WalletService;

class RentalController extends BaseController
{
    public function index(Request $request)
    {
        $query = Rental::with(['product', 'renter']);

        if ($request->has('renter_id')) {
            $query->where('renter_id', $request->renter_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $rentals = $query->paginate($request->get('per_page', 15));

        LogController::addLog(
            userId: auth()->id(),
            actionType: 'VIEW',
            actionName: 'view_rentals',
            module: 'rentals',
            entityId: null,
            oldData: null,
            newData: ['filters' => $request->only(['renter_id', 'status'])],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            status: 'success',
            message: 'User viewed rentals list'
        );

        return $this->sendPaginated(
            $rentals,
            RentalResource::collection($rentals),
            'Rentals retrieved successfully'
        );
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id'     => 'required|exists:products,id',
            'renter_id'      => 'required|exists:users,id',
            'start_date'     => 'required|date|after:today',
            'end_date'       => 'required|date|after:start_date',
            'daily_price'    => 'required|numeric|min:0',
            'payment_method' => 'sometimes|in:wallet,cash',
        ]);

        if ($validator->fails()) {
            LogController::addLog(
                userId: auth()->id() ?? $request->renter_id,
                actionType: 'ERROR',
                actionName: 'rental_create_failed',
                module: 'rentals',
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

        // Check product availability
        $conflictingRental = Rental::where('product_id', $request->product_id)
            ->where('status', '!=', 'cancelled')
            ->where(function($query) use ($request) {
                $query->whereBetween('start_date', [$request->start_date, $request->end_date])
                      ->orWhereBetween('end_date', [$request->start_date, $request->end_date]);
            })
            ->exists();

        if ($conflictingRental) {
            LogController::addLog(
                userId: auth()->id() ?? $request->renter_id,
                actionType: 'ERROR',
                actionName: 'rental_create_failed',
                module: 'rentals',
                entityId: $request->product_id,
                oldData: null,
                newData: ['start_date' => $request->start_date, 'end_date' => $request->end_date],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
                status: 'failed',
                message: 'Product not available for selected dates',
                errorCode: 400
            );
            return $this->sendError('Product not available for selected dates');
        }

        $start = Carbon::parse($request->start_date);
        $end = Carbon::parse($request->end_date);
        $days = $start->diffInDays($end) + 1;
        $total = $request->daily_price * $days;

        $paymentMethod = $request->input('payment_method', 'cash');

        if ($paymentMethod === 'wallet') {
            $charge = (float) $total + (float) config('tasleem.delivery_fee');
            $renter = User::find($request->renter_id);

            if ((float) $renter->wallet_balance < $charge) {
                return $this->sendError(
                    'Not enough wallet balance — top up or use Cash on Delivery.',
                    null,
                    400
                );
            }

            WalletService::move(
                $renter,
                'rental_hold',
                $charge,
                'rental',
                null,
                'Rental escrow for product #' . $request->product_id
            );
        }

        $rental = Rental::create([
            'product_id'     => $request->product_id,
            'renter_id'      => $request->renter_id,
            'start_date'     => $request->start_date,
            'end_date'       => $request->end_date,
            'daily_price'    => $request->daily_price,
            'total_days'     => $days,
            'total_price'    => $total,
            'payment_method' => $paymentMethod,
            'status'         => 'pending',
        ]);

        LogController::addLog(
            userId: $rental->renter_id,
            actionType: 'CREATE',
            actionName: 'rental_create',
            module: 'rentals',
            entityId: $rental->id,
            oldData: null,
            newData: $rental->toArray(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            status: 'success',
            message: 'Rental created: #' . $rental->id . ' for product: ' . ($rental->product->name ?? 'Unknown') . ' (' . $days . ' days, ' . $paymentMethod . ')'
        );

        return $this->sendResponse(
            new RentalResource($rental->load(['product', 'renter'])),
            'Rental created successfully',
            201
        );
    }

    public function show(int $id) // ✅ int
    {
        $rental = Rental::with(['product', 'renter', 'payment'])->find($id);

        if (!$rental) {
            LogController::addLog(
                userId: auth()->id(),
                actionType: 'ERROR',
                actionName: 'rental_not_found',
                module: 'rentals',
                entityId: $id,
                oldData: null,
                newData: null,
                ipAddress: request()->ip(),
                userAgent: request()->userAgent(),
                status: 'failed',
                message: 'Attempted to view non-existent rental #' . $id,
                errorCode: 404
            );
            return $this->sendError('Rental not found');
        }

        LogController::addLog(
            userId: auth()->id(),
            actionType: 'VIEW',
            actionName: 'view_rental_details',
            module: 'rentals',
            entityId: $rental->id,
            oldData: null,
            newData: null,
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
            status: 'success',
            message: 'User viewed rental #' . $rental->id
        );

        return $this->sendResponse(
            new RentalResource($rental),
            'Rental retrieved successfully'
        );
    }

    public function update(Request $request, int $id) // ✅ int
    {
        $rental = Rental::find($id);

        if (!$rental) {
            LogController::addLog(
                userId: auth()->id(),
                actionType: 'ERROR',
                actionName: 'rental_update_failed',
                module: 'rentals',
                entityId: $id,
                oldData: null,
                newData: null,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
                status: 'failed',
                message: 'Attempted to update non-existent rental #' . $id,
                errorCode: 404
            );
            return $this->sendError('Rental not found');
        }

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:pending,confirmed,active,completed,cancelled',
        ]);

        if ($validator->fails()) {
            LogController::addLog(
                userId: auth()->id(),
                actionType: 'ERROR',
                actionName: 'rental_update_validation_failed',
                module: 'rentals',
                entityId: $rental->id,
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

        $oldData = $rental->toArray();
        $rental->update($request->only('status'));

        LogController::addLog(
            userId: auth()->id(),
            actionType: 'UPDATE',
            actionName: 'rental_update',
            module: 'rentals',
            entityId: $rental->id,
            oldData: $oldData,
            newData: $rental->fresh()->toArray(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            status: 'success',
            message: 'Rental #' . $rental->id . ' status updated to: ' . $rental->status
        );

        return $this->sendResponse(
            new RentalResource($rental),
            'Rental updated successfully'
        );
    }

    public function destroy(int $id) // ✅ int
    {
        $rental = Rental::find($id);

        if (!$rental) {
            LogController::addLog(
                userId: auth()->id(),
                actionType: 'ERROR',
                actionName: 'rental_delete_failed',
                module: 'rentals',
                entityId: $id,
                oldData: null,
                newData: null,
                ipAddress: request()->ip(),
                userAgent: request()->userAgent(),
                status: 'failed',
                message: 'Attempted to delete non-existent rental #' . $id,
                errorCode: 404
            );
            return $this->sendError('Rental not found');
        }

        if ($rental->status !== 'pending') {
            LogController::addLog(
                userId: auth()->id(),
                actionType: 'ERROR',
                actionName: 'rental_delete_failed',
                module: 'rentals',
                entityId: $rental->id,
                oldData: null,
                newData: ['status' => $rental->status],
                ipAddress: request()->ip(),
                userAgent: request()->userAgent(),
                status: 'failed',
                message: 'Cannot delete rental #' . $rental->id . ' with status: ' . $rental->status,
                errorCode: 400
            );
            return $this->sendError('Cannot delete rental that is not pending');
        }

        $oldData = $rental->toArray();
        $rental->delete();

        LogController::addLog(
            userId: auth()->id(),
            actionType: 'DELETE',
            actionName: 'rental_delete',
            module: 'rentals',
            entityId: $id,
            oldData: $oldData,
            newData: null,
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
            status: 'success',
            message: 'Rental #' . $id . ' deleted successfully'
        );

        return $this->sendResponse(null, 'Rental deleted successfully');
    }
}