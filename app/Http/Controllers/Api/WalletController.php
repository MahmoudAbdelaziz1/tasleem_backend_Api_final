<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController;
use App\Services\WalletService;
use Illuminate\Http\Request;

class WalletController extends BaseController
{
    /**
     * عرض المحفظة + آخر 50 حركة.
     * GET /api/wallet
     */
    public function show()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $this->sendResponse([
            'balance'      => (float) $user->wallet_balance,
            'transactions' => $user->walletTransactions()->latest()->limit(50)->get(),
        ], 'Wallet retrieved successfully');
    }

    /**
     * شحن المحفظة (محاكاة - Simulated top-up).
     * POST /api/wallet/topup
     * Body: { "amount": 500 }
     */
    public function topup(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1|max:100000',
        ]);

        $tx = WalletService::move(
            auth()->user(),
            'topup',
            (float) $request->amount,
            null,
            null,
            'Wallet top-up'
        );

        return $this->sendResponse([
            'balance' => (float) $tx->balance_after,
        ], 'Funds added successfully');
    }
}