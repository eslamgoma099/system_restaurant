<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderAddonsTable extends Migration
{
    public function up()
    {
        Schema::create('order_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->string('addon_name'); // اسم الإضافة (مثل "جبنة إضافية")
            $table->decimal('addon_price', 8, 2); // سعر الإضافة
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('order_addons');
    }
}