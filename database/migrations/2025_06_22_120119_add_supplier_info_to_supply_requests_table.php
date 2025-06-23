<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('supply_requests', function (Blueprint $table) {
            $table->string('supplier_name')->nullable()->after('status');
            $table->string('supplier_address')->nullable()->after('supplier_name');
            $table->string('supplier_phone')->nullable()->after('supplier_address');
            $table->string('supplier_email')->nullable()->after('supplier_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supply_requests', function (Blueprint $table) {
            $table->dropColumn(['supplier_name', 'supplier_address', 'supplier_phone', 'supplier_email']);
        });
    }
};
