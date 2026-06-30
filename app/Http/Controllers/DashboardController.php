<?php
// app/Http/Controllers/DashboardController.php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Order;
use App\Models\Rental;
use App\Models\CartItem;
use App\Models\Payment;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $cacheKey = "dashboard_user_{$user->id}";
        
     
        $data = Cache::remember($cacheKey, 900, function () use ($user) {
            return $this->buildDashboardData($user);
        });
        
        return view('dashboard', $data);
    }
    
    private function buildDashboardData($user)
    {
       
        $buyer_stats = $this->getBuyerStats($user->id);
        
      
        $seller_stats = null;
        $has_products = Product::where('owner_id', $user->id)->exists();
        
        if ($has_products || $user->role === 'Admin') {
            $seller_stats = $this->getSellerStats($user->id);
        }
        
       
        $revenue_chart = $this->getRevenueChartData($user->id, 30);
        $order_status_chart = $this->getOrderStatusData($user->id);
        
        
        $recent_orders = Order::where('user_id', $user->id)
            ->with(['product:id,name,price', 'product.images:id,product_id,image_url'])
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($order) => $this->formatOrderData($order));
        
    
        $my_products = null;
        if ($has_products || $user->role === 'Admin') {
            $my_products = $this->getMyProductsPerformance($user->id);
        }
        
       
        $recommended = $this->getRecommendedProducts($user->id);
        
      
        $alerts = $this->getUserAlerts($user->id, $user->role);
        
        return [
            'buyer_stats' => $buyer_stats,
            'seller_stats' => $seller_stats,
            'has_products' => $has_products,
            'revenue_chart' => $revenue_chart,
            'order_status_chart' => $order_status_chart,
            'recent_orders' => $recent_orders,
            'my_products' => $my_products,
            'recommended' => $recommended,
            'alerts' => $alerts,
            'user' => $user,
        ];
    }
    
    private function getBuyerStats($userId): array
    {
        return [
            'orders_count' => Order::where('user_id', $userId)->count(),
            'rentals_count' => Rental::where('renter_id', $userId)
                ->where('status', 'active')
                ->count(),
            'cart_count' => CartItem::where('user_id', $userId)->count(),
            'wishlist_count' => $this->getWishlistCount($userId),
            'total_spent' => Payment::whereHas('order', fn($q) => $q->where('user_id', $userId))
                                    ->where('status', 'completed')
                                    ->sum('amount') ?? 0,
            'pending_orders' => Order::where('user_id', $userId)
                ->whereIn('status', ['pending', 'processing'])
                ->count(),
        ];
    }
    
    private function getSellerStats($userId): array
    {
        return [
            'products_count' => Product::where('owner_id', $userId)->count(),
            'available_products' => Product::where('owner_id', $userId)->where('status', 'available')->count(),
            'sold_products' => Product::where('owner_id', $userId)->where('status', 'sold')->count(),
            'rented_products' => Rental::whereHas('product', fn($q) => $q->where('owner_id', $userId))
                ->where('status', 'active')
                ->count(),
            'total_revenue' => Order::whereHas('product', fn($q) => $q->where('owner_id', $userId))
                                    ->where('status', 'delivered')
                                    ->sum('total_price') ?? 0,
            'total_rental_income' => Rental::whereHas('product', fn($q) => $q->where('owner_id', $userId))
                ->where('status', 'completed')
                ->sum('total_price') ?? 0,
            'avg_rating' => round(Review::whereHas('product', fn($q) => $q->where('owner_id', $userId))
                                        ->avg('rating') ?? 0, 1),
            'total_views' => Product::where('owner_id', $userId)->sum('view_count') ?? 0,
            'conversion_rate' => $this->calculateConversionRate($userId),
        ];
    }
    
    private function getWishlistCount($userId): int
    {
        
        $table = DB::getSchemaBuilder()->hasTable('wishlists') ? 'wishlists' : 'wishlist';
        return DB::table($table)->where('user_id', $userId)->count();
    }
    
    private function getRevenueChartData($userId, $days = 30): array
    {
        
        $query = Order::where('status', 'delivered')
            ->whereDate('created_at', '>=', Carbon::now()->subDays($days));
            
        if ($userId && auth()->user()->role !== 'Admin') {
            $query->whereHas('product', fn($q) => $q->where('owner_id', $userId));
        }
        
        $data = $query->selectRaw('DATE(created_at) as date, SUM(total_price) as total, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        
        $labels = [];
        $revenue = [];
        $ordersCount = [];
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $labels[] = Carbon::parse($date)->locale('ar')->isoFormat('DD MMM');
            
            $found = $data->firstWhere('date', $date);
            $revenue[] = $found ? round($found->total, 2) : 0;
            $ordersCount[] = $found ? $found->count : 0;
        }
        
        return [
            'labels' => $labels,
            'revenue' => $revenue,
            'orders_count' => $ordersCount,
        ];
    }
    
    private function getOrderStatusData($userId): array
    {
        
        $query = Order::query();
        
        if ($userId && auth()->user()->role !== 'Admin') {
            $query->whereHas('product', fn($q) => $q->where('owner_id', $userId));
        }
        
        $data = $query->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
        
        
        $statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'returned'];
        foreach ($statuses as $status) {
            if (!isset($data[$status])) {
                $data[$status] = 0;
            }
        }
        
        return $data;
    }
    
    private function getMyProductsPerformance($userId, $limit = 5)
    {
        $query = Product::query();
        
        
        if (auth()->user()->role !== 'Admin') {
            $query->where('owner_id', $userId);
        }
        
        return $query->with(['images' => fn($q) => $q->take(1)])
            ->withCount(['orders' => fn($q) => $q->where('status', 'delivered')])
            ->select(['id', 'name', 'price', 'status', 'type', 'view_count', 'pay_count', 'created_at'])
            ->latest()
            ->take($limit)
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => $p->price,
                'type' => $p->type,
                'status' => $p->status,
                'image' => $p->images->first()?->image_url,
                'orders_count' => $p->orders_count,
                'views' => $p->view_count,
                'conversion' => $p->view_count > 0 ? round(($p->pay_count / $p->view_count) * 100, 1) : 0,
            ]);
    }
    
    private function getRecommendedProducts($userId, $limit = 4)
    {
       
        $aiRecs = DB::table('ai_recommendations')
            ->where('user_id', $userId)
            ->where(function($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('score')
            ->take($limit * 2)
            ->pluck('product_id');
        
        $query = Product::where('status', 'available')
            ->where('owner_id', '!=', $userId) 
        
            ->with(['images' => fn($q) => $q->take(1)])
            ->select(['id', 'name', 'price', 'rate', 'pay_count']);
        
        if ($aiRecs->count() > 0) {
            $query->whereIn('id', $aiRecs);
        } else {
           
            $query->orderByRaw('(view_count * 0.2 + rate * 0.4 + pay_count * 0.4) DESC');
        }
        
        return $query->take($limit)->get();
    }
    
    private function getUserAlerts($userId, $userRole): array
    {
        $alerts = [];
        
        
        $endingRentals = Rental::where('renter_id', $userId)
            ->where('status', 'active')
            ->whereBetween('end_date', [now(), now()->addDays(3)])
            ->count();
            
        if ($endingRentals > 0) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => 'bi bi-clock',
                'message' => "لديك {$endingRentals} عقد إيجار ينتهي قريباً",
                'action' => route('rentals.index'),
            ];
        }
        
        
        if ($userRole !== 'Admin') {
            $pendingOrders = Order::whereHas('product', fn($q) => $q->where('owner_id', $userId))
                ->where('status', 'pending')
                ->count();
                
            if ($pendingOrders > 0) {
                $alerts[] = [
                    'type' => 'info',
                    'icon' => 'bi bi-bell',
                    'message' => "{$pendingOrders} طلب جديد على منتجاتك في انتظار التأكيد",
                    'action' => route('orders.index'),
                ];
            }
        }
        
        // تنبيه للأدمن: مستخدمين جدد أو طلبات معلقة للمنصة
        if ($userRole === 'Admin') {
            $newUsers = \App\Models\User::whereDate('created_at', '>=', now()->subDays(1))->count();
            if ($newUsers > 0) {
                $alerts[] = [
                    'type' => 'success',
                    'icon' => 'bi bi-people',
                    'message' => "{$newUsers} مستخدم جديد انضم اليوم",
                    'action' => route('admin.users.index'),
                ];
            }
        }
        
        return $alerts;
    }
    
    private function calculateConversionRate($userId): float
    {
        $query = Product::where('owner_id', $userId);
        $totalViews = $query->sum('view_count') ?? 0;
        $totalSales = $query->sum('pay_count') ?? 0;
        
        return $totalViews > 0 ? round(($totalSales / $totalViews) * 100, 2) : 0.0;
    }
    
    private function formatOrderData($order)
    {
        return [
            'id' => $order->id,
            'product_name' => $order->product->name ?? 'منتج محذوف',
            'product_image' => $order->product->images->first()?->image_url,
            'total_price' => $order->total_price,
            'status' => $order->status,
            'status_label' => $this->getStatusLabel($order->status),
            'created_at' => $order->created_at,
            'created_at_formatted' => $order->created_at?->format('Y-m-d'),
            'created_at_human' => $order->created_at?->diffForHumans(['short' => true]),
        ];
    }
    
    private function getStatusLabel($status): array
    {
        return match($status) {
            'delivered' => ['success', 'مكتمل', 'bi bi-check-circle'],
            'pending' => ['warning', 'انتظار', 'bi bi-hourglass-split'],
            'processing' => ['info', 'جاري المعالجة', 'bi bi-gear'],
            'shipped' => ['primary', 'تم الشحن', 'bi bi-truck'],
            'cancelled' => ['danger', 'ملغي', 'bi bi-x-circle'],
            'returned' => ['secondary', 'مرتجع', 'bi bi-arrow-return-left'],
            default => ['secondary', $status, 'bi bi-circle'],
        };
    }
    
    // دالة لمسح الكاش عند الحاجة
    public function clearCache()
    {
        Cache::forget("dashboard_user_" . auth()->id());
        return redirect()->back()->with('success', 'تم تحديث البيانات');
    }
}