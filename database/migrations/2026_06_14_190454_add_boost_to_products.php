<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_boosted')->default(false)->after('type');
            $table->timestamp('boost_expires_at')->nullable()->after('is_boosted');
        });
    }

    public function down(): void {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_boosted', 'boost_expires_at']);
        });
    }
};