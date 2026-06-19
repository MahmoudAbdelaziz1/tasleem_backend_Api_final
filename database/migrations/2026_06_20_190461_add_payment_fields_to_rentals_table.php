<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('rentals', function (Blueprint $table) {
            $table->enum('payment_method', ['wallet', 'cash'])
                  ->default('cash')
                  ->after('total_price');
            
            $table->decimal('tasleem_fee', 10, 2)
                  ->default(0)
                  ->after('payment_method');
            
            $table->decimal('delivery_fee', 10, 2)
                  ->default(0)
                  ->after('tasleem_fee');
        });
    }

    public function down(): void {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'tasleem_fee', 'delivery_fee']);
        });
    }
};