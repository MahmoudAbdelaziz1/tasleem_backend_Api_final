<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminRentalController;
use App\Http\Controllers\Admin\AdminPaymentController;
use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Controllers\Admin\AdminLogController;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// ✅ Home - Closure (مؤقت)
Route::get('/', function () {
    return view('welcome');
})->name('home');

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

        // ✅ Users Management - Routes المخصصة BEFORE resource
        Route::get('users/sellers', [AdminUserController::class, 'sellers'])->name('users.sellers');
        Route::get('users/customers', [AdminUserController::class, 'customers'])->name('users.customers');
        Route::patch('users/{user}/toggle-status', [AdminUserController::class, 'toggleStatus'])->name('users.toggle-status');

        // ✅ Users Resource - بعد الـ custom routes
        Route::resource('users', AdminUserController::class);

        // ✅ Products Management
        Route::delete('products/delete-image', [AdminProductController::class, 'deleteImage'])->name('products.delete-image');
        Route::resource('products', AdminProductController::class);

        // ✅ Categories Management
        Route::patch('categories/{category}/toggle-status', [AdminCategoryController::class, 'toggleStatus'])->name('categories.toggle-status');
        Route::resource('categories', AdminCategoryController::class);

        // ✅ Orders Management - Custom routes BEFORE resource
        Route::patch('orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.update-status');
        Route::post('orders/bulk-update-status', [AdminOrderController::class, 'bulkUpdateStatus'])->name('orders.bulk-update-status');
        Route::get('orders/{order}/print', [AdminOrderController::class, 'print'])->name('orders.print');
        Route::get('orders/{order}/invoice', [AdminOrderController::class, 'invoice'])->name('orders.invoice');
        Route::resource('orders', AdminOrderController::class);

        // ✅ Rentals Management - Custom routes BEFORE resource
        Route::patch('rentals/{rental}/status', [AdminRentalController::class, 'updateStatus'])->name('rentals.update-status');
        Route::get('rentals/{rental}/print', [AdminRentalController::class, 'print'])->name('rentals.print');
        Route::get('rentals/{rental}/contract', [AdminRentalController::class, 'contract'])->name('rentals.contract');
        Route::resource('rentals', AdminRentalController::class);

        // ✅ Payments
        Route::get('payments', [AdminPaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/{payment}', [AdminPaymentController::class, 'show'])->name('payments.show');

        // ✅ Reports - مرة واحدة فقط
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/', [AdminReportController::class, 'index'])->name('index');
            Route::get('/sales', [AdminReportController::class, 'sales'])->name('sales');
            Route::get('/rentals', [AdminReportController::class, 'rentals'])->name('rentals');
            Route::get('/users', [AdminReportController::class, 'users'])->name('users');
            Route::get('/products', [AdminReportController::class, 'products'])->name('products');
            Route::get('/revenue', [AdminReportController::class, 'revenue'])->name('revenue');
            Route::get('/financial', [AdminReportController::class, 'financial'])->name('financial');
            Route::get('/export', [AdminReportController::class, 'export'])->name('export');
        });

        // ✅ Logs - مرة واحدة فقط
        Route::get('logs', [AdminLogController::class, 'index'])->name('logs.index');
        Route::get('logs/{log}', [AdminLogController::class, 'show'])->name('logs.show');
        Route::post('logs/clear', [AdminLogController::class, 'clear'])->name('logs.clear');
        Route::get('logs/export', [AdminLogController::class, 'export'])->name('logs.export');
        Route::get('logs/stats', [AdminLogController::class, 'stats'])->name('logs.stats');
    });

require __DIR__.'/auth.php';
