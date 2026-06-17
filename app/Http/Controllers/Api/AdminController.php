<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\Rental;

class AdminController extends BaseController
{
    public function stats()
    {
        $tasleemUserId = 1; // معرف حساب تسليم الرسمي

        $stats = [
            'products' => [
                'tasleem' => Product::where('owner_id', $tasleemUserId)->count(),
                'c2c'     => Product::where('owner_id', '!=', $tasleemUserId)->count(),
                'total'   => Product::count(),
            ],
            'orders' => [
                'tasleem'   => Order::whereHas('product', fn($q) => $q->where('owner_id', $tasleemUserId))->count(),
                'c2c'       => Order::whereHas('product', fn($q) => $q->where('owner_id', '!=', $tasleemUserId))->count(),
                'total'     => Order::count(),
                'pending'   => Order::where('status', 'pending')->count(),
                'confirmed' => Order::where('status', 'confirmed')->count(),
                'delivered' => Order::where('status', 'delivered')->count(),
                'cancelled' => Order::where('status', 'cancelled')->count(),
            ],
            'rentals' => [
                'total'     => Rental::count(),
                'pending'   => Rental::where('status', 'pending')->count(),
                'active'    => Rental::where('status', 'active')->count(),
                'completed' => Rental::where('status', 'completed')->count(),
            ],
            'revenue' => [
                'tasleem_sales' => Order::where('status', 'delivered')
                    ->whereHas('product', fn($q) => $q->where('owner_id', $tasleemUserId))
                    ->sum('total_price'),
                'c2c_sales' => Order::where('status', 'delivered')
                    ->whereHas('product', fn($q) => $q->where('owner_id', '!=', $tasleemUserId))
                    ->sum('total_price'),
                'tasleem_fees'  => Order::where('status', 'delivered')->sum('tasleem_fee'),
                'delivery_fees' => Order::where('status', 'delivered')->sum('delivery_fee'),
                'boost_revenue' => WalletTransaction::where('type', 'boost_fee')->sum('amount'),
            ],
            'users' => [
                'total'    => User::count(),
                'active'   => User::where('status', '1')->count(),
                'inactive' => User::where('status', '0')->count(),
            ],
        ];

        return $this->sendResponse($stats, 'Admin stats retrieved');
    }
}