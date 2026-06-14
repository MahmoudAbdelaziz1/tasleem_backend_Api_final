
<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController; 
use App\Http\Controllers\Api\ProductController; 
use App\Http\Controllers\Api\RentalController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Dashboard 
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    
   
    // Profile routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});


Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
    
    // Admin Dashboard
    Route::get('/dashboard', [App\Http\Controllers\Admin\DashboardController::class, 'index'])
        ->name('dashboard');
    
    // Users
    Route::resource('users', App\Http\Controllers\Admin\UserController::class);
    Route::get('users/sellers', [App\Http\Controllers\Admin\UserController::class, 'sellers'])
        ->name('users.sellers');
    Route::get('users/customers', [App\Http\Controllers\Admin\UserController::class, 'customers'])
        ->name('users.customers');
    Route::patch('users/{user}/toggle-status', [App\Http\Controllers\Admin\UserController::class, 'toggleStatus'])
        ->name('users.toggle-status');         
    
    // Products
    Route::resource('products', App\Http\Controllers\Admin\ProductController::class);
    Route::delete('products/delete-image', [App\Http\Controllers\Admin\ProductController::class, 'deleteImage'])
        ->name('products.delete-image');

    // Categories
    Route::resource('categories', App\Http\Controllers\Admin\CategoryController::class);
    Route::patch('categories/{category}/toggle-status', [App\Http\Controllers\Admin\CategoryController::class, 'toggleStatus'])
        ->name('categories.toggle-status');

    
    Route::resource('rentals', App\Http\Controllers\Admin\RentalController::class);
    Route::patch('rentals/{rental}/status', [App\Http\Controllers\Admin\RentalController::class, 'updateStatus'])
        ->name('rentals.update-status');
    Route::get('rentals/{rental}/print', [App\Http\Controllers\Admin\RentalController::class, 'print'])
        ->name('rentals.print');
    Route::get('rentals/{rental}/contract', [App\Http\Controllers\Admin\RentalController::class, 'contract'])
        ->name('rentals.contract');

    // Orders (بدون resource لتجنب التضارب مع api)
        Route::get('orders', [App\Http\Controllers\Admin\OrderController::class, 'index'])
    ->name('orders.index');
Route::get('orders/{order}', [App\Http\Controllers\Admin\OrderController::class, 'show'])
    ->name('orders.show');
        
    Route::patch('orders/{order}/status', [App\Http\Controllers\Admin\OrderController::class, 'updateStatus'])
        ->name('orders.update-status');
    Route::post('orders/bulk-update-status', [App\Http\Controllers\Admin\OrderController::class, 'bulkUpdateStatus'])
        ->name('orders.bulk-update-status');
    Route::get('orders/{order}/print', [App\Http\Controllers\Admin\OrderController::class, 'print'])
        ->name('orders.print');
    Route::get('orders/{order}/invoice', [App\Http\Controllers\Admin\OrderController::class, 'invoice'])
        ->name('orders.invoice');    
    
    // Payments
    Route::get('payments', [App\Http\Controllers\Admin\PaymentController::class, 'index'])
        ->name('payments.index');
    Route::get('payments/{payment}', [App\Http\Controllers\Admin\PaymentController::class, 'show'])
        ->name('payments.show');
    
    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\ReportController::class, 'index'])->name('index');
        Route::get('/sales', [App\Http\Controllers\Admin\ReportController::class, 'sales'])->name('sales');
        Route::get('/rentals', [App\Http\Controllers\Admin\ReportController::class, 'rentals'])->name('rentals');
        Route::get('/users', [App\Http\Controllers\Admin\ReportController::class, 'users'])->name('users');
        Route::get('/products', [App\Http\Controllers\Admin\ReportController::class, 'products'])->name('products');
        Route::get('/revenue', [App\Http\Controllers\Admin\ReportController::class, 'revenue'])->name('revenue');
        Route::get('/financial', [App\Http\Controllers\Admin\ReportController::class, 'financial'])->name('financial');
        Route::get('/export', [App\Http\Controllers\Admin\ReportController::class, 'export'])->name('export');
    });

    
    Route::get('logs', [App\Http\Controllers\Admin\LogController::class, 'index'])->name('logs.index');
    Route::get('logs/{log}', [App\Http\Controllers\Admin\LogController::class, 'show'])->name('logs.show');
    Route::post('logs/clear', [App\Http\Controllers\Admin\LogController::class, 'clear'])->name('logs.clear');
    Route::get('logs/export', [App\Http\Controllers\Admin\LogController::class, 'export'])->name('logs.export');
    Route::get('logs/stats', [App\Http\Controllers\Admin\LogController::class, 'stats'])->name('logs.stats');         
});

require __DIR__.'/auth.php';
