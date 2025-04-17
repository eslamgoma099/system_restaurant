<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('branches', function (Blueprint $table) {
        $table->decimal('price_per_km', 8, 2)->default(2.00);
        $table->decimal('max_delivery_distance', 8, 2)->default(10.00);
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            //
        });
    }
};
