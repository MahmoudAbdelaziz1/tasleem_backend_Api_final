<?php
// routes/api.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\RentalController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\CartItemController;
use App\Http\Controllers\Api\AiRecommendationController;
use App\Http\Controllers\Api\LogController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\ProductImageController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\OfferController;
use App\Http\Controllers\Api\NotificationController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::prefix('v1')->group(function () {
    // Auth
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    
    // Public products
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{id}', [ProductController::class, 'show']);
    Route::get('products/{id}/similar', [ProductController::class, 'similar']);
    
    // Public categories
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{id}', [CategoryController::class, 'show']);
    
    // Public reviews
    Route::get('reviews', [ReviewController::class, 'index']);
    Route::get('reviews/{id}', [ReviewController::class, 'show']);
});

// Protected routes (require authentication)
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
    
    // Users
    Route::apiResource('users', UserController::class);
    Route::get('users/{id}/products', [UserController::class, 'products']);
    Route::get('users/{id}/orders', [UserController::class, 'orders']);
    Route::get('users/{id}/rentals', [UserController::class, 'rentals']);
    
    // Products (full CRUD)
    Route::apiResource('products', ProductController::class)->except(['index', 'show']);
    
    // Categories (full CRUD)
    Route::apiResource('categories', CategoryController::class)->except(['index', 'show']);
    
    // Orders
    Route::apiResource('orders', OrderController::class);
    Route::post('orders/{id}/seller-confirm', [OrderController::class, 'sellerConfirm']);
    Route::post('orders/{id}/complete', [OrderController::class, 'complete'])->middleware('admin');
    Route::post('orders/{id}/cancel', [OrderController::class, 'cancel']);
    
    // Rentals
    Route::apiResource('rentals', RentalController::class);
    
    // Reviews
    Route::apiResource('reviews', ReviewController::class)->except(['index', 'show']);
    
    // Payments
    Route::apiResource('payments', PaymentController::class);
    
    // Cart
    Route::apiResource('cart', CartItemController::class);
    Route::delete('cart/clear/{user_id}', [CartItemController::class, 'clear']);
    
    // AI Recommendations
    Route::apiResource('recommendations', AiRecommendationController::class);

    // Wallet
    Route::get('wallet', [WalletController::class, 'show']);
    Route::post('wallet/topup', [WalletController::class, 'topup']);

    // Offers
    Route::get('offers', [OfferController::class, 'index']);
    Route::post('offers', [OfferController::class, 'store']);
    Route::post('offers/{id}/accept', [OfferController::class, 'accept']);
    Route::post('offers/{id}/reject', [OfferController::class, 'reject']);

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);

    // Boost
    Route::post('products/{id}/boost', [ProductController::class, 'boost']);
    
    // Logs (admin only) 
    Route::middleware('admin')->prefix('logs')->group(function () {
        Route::get('/stats', [LogController::class, 'stats']);                    
        Route::get('/entity/{entityType}/{entityId}', [LogController::class, 'entityLogs']);  
        Route::get('/user/{userId}', [LogController::class, 'userLogs']);         
        Route::get('/', [LogController::class, 'index']);                        
        Route::get('/{id}', [LogController::class, 'show']);      
    });
});

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // wishlist Routes
    Route::get('wishlist', [WishlistController::class, 'index']);
    Route::post('wishlist', [WishlistController::class, 'store']);
    Route::delete('wishlist/{id}', [WishlistController::class, 'destroy']);
    Route::delete('wishlist/clear/{userId}', [WishlistController::class, 'clear']);
    Route::get('wishlist/check', [WishlistController::class, 'check']);
});

Route::prefix('v1')->group(function () {
    // Public routes 
    Route::get('products/{productId}/images', [ProductImageController::class, 'index']);
    Route::get('product-images/{id}', [ProductImageController::class, 'show']);

    // Protected routes 
    Route::middleware('auth:sanctum')->group(function () {
        // Upload files
        Route::post('product-images/upload', [ProductImageController::class, 'store']);
        Route::post('product-images/upload-single', [ProductImageController::class, 'uploadSingle']);
        
        // Add from URLs (NEW)
        Route::post('product-images/add-from-url', [ProductImageController::class, 'addFromUrl']);
        Route::post('product-images/add-multiple-urls', [ProductImageController::class, 'addMultipleFromUrls']);
        
        // Update and delete
        Route::put('product-images/{id}', [ProductImageController::class, 'update']);
        Route::delete('product-images/{id}', [ProductImageController::class, 'destroy']);
        Route::delete('product-images/delete-multiple', [ProductImageController::class, 'destroyMultiple']);
    });
});
