<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIngredientIdToInventoryTable extends Migration
{
    public function up()
    {
        Schema::table('inventory', function (Blueprint $table) {
            $table->foreignId('ingredient_id')->nullable()->constrained()->onDelete('cascade')->after('item_id');
        });
    }

    public function down()
    {
        Schema::table('inventory', function (Blueprint $table) {
            $table->dropForeign(['ingredient_id']);
            $table->dropColumn('ingredient_id');
        });
    }
}