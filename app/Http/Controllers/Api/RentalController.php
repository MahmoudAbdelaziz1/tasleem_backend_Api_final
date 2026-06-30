<?php

namespace App\Http\Controllers\Api;

use App\Models\Rental;
use App\Models\Product;
use App\Models\User;
use App\Models\Payment;
use App\Http\Resources\RentalResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Http\Controllers\Api\LogController;
use App\Services\WalletService;
use App\Services\Notify;

class RentalController extends BaseController
{
    public function index(Request $request)
    {
        
        $query = Rental::with(['product.owner', 'renter']);

        if ($request->filled('renter_id')) {
            $query->where('renter_id', $request->renter_id); // My Rentals
        }

        
        if ($request->filled('owner_id')) {
            $query->whereHas('product', fn($q) => $q->where('owner_id', $request->owner_id));
        }

        if ($request->filled('status')) {
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
            newData: ['filters' => $request->only(['renter_id', 'owner_id', 'status'])],
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

        
        $conflictingRental = Rental::where('product_id', $request->product_id)
            ->where('status', '!=', 'cancelled')
            ->where(function($query) use ($request) {
                $query->whereBetween('start_date', [$request->start_date, $request->end_date])
                      ->orWhereBetween('end_date', [$request->start_date, $request->end_date]);
            })
            ->exists();

        if ($conflictingRental) {
            return $this->sendError('Product not available for selected dates');
        }

        $start = Carbon::parse($request->start_date);
        $end = Carbon::parse($request->end_date);
        $days = $start->diffInDays($end) + 1;
        $dailyPrice = (float) $request->daily_price;
        $rent = $dailyPrice * $days;
        $deliveryFee = (float) config('tasleem.delivery_fee');
        $tasleemFee = round($rent * (float) config('tasleem.commission_rate'), 2);

        $paymentMethod = $request->input('payment_method', 'cash');
        $renter = User::find($request->renter_id);

        
        if ($paymentMethod === 'wallet') {
            $charge = $rent + $deliveryFee;

            if ((float) $renter->wallet_balance < $charge) {
                return $this->sendError(
                    'Not enough wallet balance — top up or use Cash on Delivery.',
                    null,
                    400
                );
            }

            
            WalletService::move(
                $renter,
                'hold',
                -$charge, 
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
            'daily_price'    => $dailyPrice,
            'total_days'     => $days,
            'total_price'    => $rent,
            'payment_method' => $paymentMethod,
            'tasleem_fee'    => $tasleemFee,
            'delivery_fee'   => $deliveryFee,
            'status'         => 'pending',
        ]);

        
        if ($paymentMethod === 'wallet') {
            $renter->walletTransactions()
                ->where('ref_type', 'rental')
                ->whereNull('ref_id')
                ->latest()
                ->first()
                ?->update(['ref_id' => $rental->rental_id]);
        }

        
        Payment::create([
            'order_id'       => null,
            'rental_id'      => $rental->rental_id,
            'user_id'        => $renter->id,
            'amount'         => $rent + $deliveryFee,
            'payment_method' => $paymentMethod,
            'status'         => 'pending',
        ]);

        LogController::addLog(
            userId: $rental->renter_id,
            actionType: 'CREATE',
            actionName: 'rental_create',
            module: 'rentals',
            entityId: $rental->rental_id,
            oldData: null,
            newData: $rental->toArray(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            status: 'success',
            message: 'Rental created: #' . $rental->rental_id . ' for product: ' . ($rental->product->name ?? 'Unknown') . ' (' . $days . ' days, ' . $paymentMethod . ')'
        );

       
        Notify::send(
            $rental->product->owner_id,
            'rental_requested',
            'New rental request',
            'Someone wants to rent "' . $rental->product->name . '" for ' . $days . ' days.',
            'rental',
            $rental->rental_id
        );

        return $this->sendResponse(
            new RentalResource($rental->load(['product.owner', 'renter', 'payment'])),
            'Rental created successfully',
            201
        );
    }

    public function show(int $id)
    {
        $rental = Rental::with(['product.owner', 'renter', 'payment'])->find($id);

        if (!$rental) {
            return $this->sendError('Rental not found');
        }

        LogController::addLog(
            userId: auth()->id(),
            actionType: 'VIEW',
            actionName: 'view_rental_details',
            module: 'rentals',
            entityId: $rental->rental_id,
            oldData: null,
            newData: null,
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
            status: 'success',
            message: 'User viewed rental #' . $rental->rental_id
        );

        return $this->sendResponse(
            new RentalResource($rental),
            'Rental retrieved successfully'
        );
    }

    public function update(Request $request, int $id)
    {
        $rental = Rental::find($id);

        if (!$rental) {
            return $this->sendError('Rental not found');
        }

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:pending,confirmed,active,completed,cancelled',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        $oldData = $rental->toArray();
        $rental->update($request->only('status'));

        LogController::addLog(
            userId: auth()->id(),
            actionType: 'UPDATE',
            actionName: 'rental_update',
            module: 'rentals',
            entityId: $rental->rental_id,
            oldData: $oldData,
            newData: $rental->fresh()->toArray(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            status: 'success',
            message: 'Rental #' . $rental->rental_id . ' status updated to: ' . $rental->status
        );

        return $this->sendResponse(
            new RentalResource($rental),
            'Rental updated successfully'
        );
    }

    public function destroy(int $id)
    {
        $rental = Rental::find($id);

        if (!$rental) {
            return $this->sendError('Rental not found');
        }

        if ($rental->status !== 'pending') {
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

   
    public function confirm(int $id)
    {
        $rental = Rental::with('product')->find($id);

        if (!$rental) {
            return $this->sendError('Rental not found', null, 404);
        }

        /** @var \App\Models\User $currentUser */
        $currentUser = auth()->user();
        
        
        if (auth()->id() !== $rental->product->owner_id && !$currentUser->isAdmin()) {
            return $this->sendError('Unauthorized', null, 403);
        }

        if ($rental->status !== 'pending') {
            return $this->sendError('Rental already handled', null, 400);
        }

        $rental->update(['status' => 'confirmed']);

        Notify::send(
            $rental->renter_id,
            'rental_confirmed',
            'Rental confirmed',
            'Your rental for "' . $rental->product->name . '" has been confirmed.',
            'rental',
            $rental->rental_id
        );

        return $this->sendResponse(
            new RentalResource($rental->load(['product.owner', 'renter'])),
            'Rental confirmed'
        );
    }

    
    public function complete(int $id)
    {
        $rental = Rental::with(['product', 'payment'])->find($id);

        if (!$rental) {
            return $this->sendError('Rental not found', null, 404);
        }

        /** @var \App\Models\User $currentUser */
        $currentUser = auth()->user();
        
        
        if (!$currentUser->isAdmin()) {
            return $this->sendError('Admin only', null, 403);
        }

        if (!in_array($rental->status, ['confirmed', 'active'])) {
            return $this->sendError('Rental is not confirmed or active', null, 400);
        }

        if (!$rental->payment || $rental->payment->status !== 'pending') {
            return $this->sendError('Nothing to release', null, 400);
        }

        $owner = User::find($rental->product->owner_id);
        $payout = (float) $rental->total_price - (float) $rental->tasleem_fee;

        
        WalletService::move(
            $owner,
            'release',
            $payout,
            'rental',
            $rental->rental_id,
            'Rental payment released for rental #' . $rental->rental_id
        );

        $rental->payment->update(['status' => 'completed']);
        $rental->update(['status' => 'completed']);

        Notify::send(
            $owner->id,
            'rental_completed',
            'You got paid',
            'EGP ' . number_format($payout, 2) . ' added to your wallet for rental #' . $rental->rental_id,
            'rental',
            $rental->rental_id
        );

        Notify::send(
            $rental->renter_id,
            'rental_completed',
            'Rental complete',
            'Your rental for "' . $rental->product->name . '" is complete.',
            'rental',
            $rental->rental_id
        );

        return $this->sendResponse(
            new RentalResource($rental->load(['product.owner', 'renter', 'payment'])),
            'Rental completed and owner paid'
        );
    }

    
    public function cancel(int $id)
    {
        $rental = Rental::with(['product', 'payment'])->find($id);

        if (!$rental) {
            return $this->sendError('Rental not found', null, 404);
        }

        /** @var \App\Models\User $currentUser */
        $currentUser = auth()->user();
        
        
        $isRenterOrAdmin = auth()->id() === $rental->renter_id || $currentUser->isAdmin();

        if (!$isRenterOrAdmin) {
            return $this->sendError('Unauthorized', null, 403);
        }

        if (!in_array($rental->status, ['pending', 'confirmed'])) {
            return $this->sendError('Too late to cancel', null, 400);
        }

        
        if ($rental->payment && $rental->payment->status === 'pending') {
            if ($rental->payment->payment_method === 'wallet') {
                WalletService::move(
                    User::find($rental->renter_id),
                    'refund',
                    (float) $rental->payment->amount,
                    'rental',
                    $rental->rental_id,
                    'Rental cancelled — refund'
                );
                $rental->payment->update(['status' => 'refunded']);
            } else {
                $rental->payment->update(['status' => 'cancelled']);
            }
        }

        $rental->update(['status' => 'cancelled']);

        $refundMessage = $rental->payment && $rental->payment->payment_method === 'wallet'
            ? 'EGP ' . number_format($rental->payment->amount ?? 0, 2) . ' returned to your wallet.'
            : 'Your rental has been cancelled.';

        Notify::send(
            $rental->renter_id,
            'rental_cancelled',
            'Rental cancelled',
            $refundMessage,
            'rental',
            $rental->rental_id
        );

        Notify::send(
            $rental->product->owner_id,
            'rental_cancelled',
            'Rental cancelled',
            null,
            'rental',
            $rental->rental_id
        );

        return $this->sendResponse(null, 'Rental cancelled');
    }
}