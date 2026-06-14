<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('tasleem_fee', 12, 2)->default(0)->after('total_price');
            $table->decimal('delivery_fee', 12, 2)->default(0)->after('tasleem_fee');
        });

        // إضافة 'wallet' إلى ENUM الخاص بـ payment_method
        DB::statement("ALTER TABLE payments MODIFY COLUMN payment_method ENUM('credit_card','paypal','bank_transfer','cash','wallet')");
    }

    public function down(): void {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['tasleem_fee', 'delivery_fee']);
        });

        DB::statement("ALTER TABLE payments MODIFY COLUMN payment_method ENUM('credit_card','paypal','bank_transfer','cash')");
    }
};