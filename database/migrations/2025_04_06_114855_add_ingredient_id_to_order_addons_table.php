<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIngredientIdToOrderAddonsTable extends Migration
{
    public function up()
    {
        Schema::table('order_addons', function (Blueprint $table) {
            $table->foreignId('ingredient_id')->nullable()->constrained()->onDelete('set null')->after('order_id');
            $table->dropColumn('addon_name'); // لأننا سنستخدم اسم المكون من جدول ingredients
        });
    }

    public function down()
    {
        Schema::table('order_addons', function (Blueprint $table) {
            $table->dropForeign(['ingredient_id']);
            $table->dropColumn('ingredient_id');
            $table->string('addon_name')->after('order_id');
        });
    }
}