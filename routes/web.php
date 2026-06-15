<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\DashboardController; 
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\RentalController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\LogController;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// ✅ Home - بـ HomeController
Route::get('/', function () {
    return "Railway works";
});

// Dashboard 
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Admin Routes
Route::middleware(['auth', 'admin'])
     ->prefix('admin')
     ->name('admin.')
     ->group(function () {
    
    // Admin Dashboard
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    
    // Users Management
    Route::get('users/sellers', [UserController::class, 'sellers'])->name('users.sellers');
    Route::get('users/customers', [UserController::class, 'customers'])->name('users.customers');
    Route::patch('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
    Route::resource('users', UserController::class);
    
    // Products Management
    Route::delete('products/delete-image', [ProductController::class, 'deleteImage'])->name('products.delete-image');
    Route::resource('products', ProductController::class);

    // Categories Management
    Route::patch('categories/{category}/toggle-status', [CategoryController::class, 'toggleStatus'])->name('categories.toggle-status');
    Route::resource('categories', CategoryController::class);

    // Orders Management
    Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.update-status');
    Route::post('orders/bulk-update-status', [OrderController::class, 'bulkUpdateStatus'])->name('orders.bulk-update-status');
    Route::get('orders/{order}/print', [OrderController::class, 'print'])->name('orders.print');
    Route::get('orders/{order}/invoice', [OrderController::class, 'invoice'])->name('orders.invoice');
    Route::resource('orders', OrderController::class);

    // Rentals Management
    Route::patch('rentals/{rental}/status', [RentalController::class, 'updateStatus'])->name('rentals.update-status');
    Route::get('rentals/{rental}/print', [RentalController::class, 'print'])->name('rentals.print');
    Route::get('rentals/{rental}/contract', [RentalController::class, 'contract'])->name('rentals.contract');
    Route::resource('rentals', RentalController::class);
    
    // Payments
    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
    
    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/sales', [ReportController::class, 'sales'])->name('sales');
        Route::get('/rentals', [ReportController::class, 'rentals'])->name('rentals');
        Route::get('/users', [ReportController::class, 'users'])->name('users');
        Route::get('/products', [ReportController::class, 'products'])->name('products');
        Route::get('/revenue', [ReportController::class, 'revenue'])->name('revenue');
        Route::get('/financial', [ReportController::class, 'financial'])->name('financial');
        Route::get('/export', [ReportController::class, 'export'])->name('export');
    });

    // Logs
    Route::get('logs', [LogController::class, 'index'])->name('logs.index');
    Route::get('logs/{log}', [LogController::class, 'show'])->name('logs.show');
    Route::post('logs/clear', [LogController::class, 'clear'])->name('logs.clear');
    Route::get('logs/export', [LogController::class, 'export'])->name('logs.export');
    Route::get('logs/stats', [LogController::class, 'stats'])->name('logs.stats');
});

require __DIR__.'/auth.php';