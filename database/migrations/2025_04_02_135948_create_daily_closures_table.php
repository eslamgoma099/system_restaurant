<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDailyClosuresTable extends Migration
{
    public function up()
    {
        Schema::create('daily_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->foreignId('cashier_id')->constrained('users')->onDelete('cascade');
            $table->decimal('total_cash', 10, 2)->default(0);
            $table->decimal('total_card', 10, 2)->default(0);
            $table->decimal('total_revenue', 10, 2)->default(0);
            $table->timestamp('closure_date');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('daily_closures');
    }
}