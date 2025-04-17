<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddQuantityToOrderAddonsTable extends Migration
{
    public function up()
    {
        Schema::table('order_addons', function (Blueprint $table) {
            $table->integer('quantity')->default(1)->after('addon_price');
        });
    }

    public function down()
    {
        Schema::table('order_addons', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });
    }
}