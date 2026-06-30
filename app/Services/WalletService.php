<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WalletService
{
    /**
     * 
     *
     * @param User $user 
     * @param string $type (topup, hold, release, refund, boost_fee, payout)
     * @param float $signedAmount 
     * @param string|null $refType (order, rental, offer, boost)
     * @param int|null $refId 
     * @param string|null $desc 
     * @return WalletTransaction
     * @throws RuntimeException 
     */
    public static function move(
        User $user,
        string $type,
        float $signedAmount,
        ?string $refType = null,
        ?int $refId = null,
        ?string $desc = null
    ): WalletTransaction {
        
        return DB::transaction(function () use ($user, $type, $signedAmount, $refType, $refId, $desc) {
           
            $user = User::lockForUpdate()->find($user->id);

           
            $newBalance = (float)$user->wallet_balance + $signedAmount;

        
            if ($newBalance < 0) {
                throw new RuntimeException('Insufficient wallet balance');
            }

            
            $user->wallet_balance = $newBalance;
            $user->save();

         
            return WalletTransaction::create([
                'user_id'       => $user->id,
                'type'          => $type,
                'amount'        => $signedAmount,
                'balance_after' => $newBalance,
                'ref_type'      => $refType,
                'ref_id'        => $refId,
                'description'   => $desc,
            ]);
        });
    }
}