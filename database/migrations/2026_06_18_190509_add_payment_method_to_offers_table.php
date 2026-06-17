<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('offers', function (Blueprint $table) {
            $table->enum('payment_method', ['wallet', 'cash'])
                  ->default('cash')
                  ->after('amount');
        });
    }

    public function down(): void {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};