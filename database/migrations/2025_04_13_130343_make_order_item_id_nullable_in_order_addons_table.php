<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeOrderItemIdNullableInOrderAddonsTable extends Migration
{
    public function up()
    {
        Schema::table('order_addons', function (Blueprint $table) {
            $table->unsignedBigInteger('order_item_id')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('order_addons', function (Blueprint $table) {
            $table->unsignedBigInteger('order_item_id')->nullable(false)->change();
        });
    }
}