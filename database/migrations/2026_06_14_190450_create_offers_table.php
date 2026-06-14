<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('buyer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['pending','accepted','rejected','cancelled'])->default('pending');
            $table->timestamps();
            $table->index(['product_id','status']);
            $table->index('buyer_id');
            $table->index('seller_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('offers');
    }
};