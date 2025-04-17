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
        // إضافة العمود أولاً
        if (!Schema::hasColumn('order_addons', 'order_item_id')) {
            Schema::table('order_addons', function (Blueprint $table) {
                $table->unsignedBigInteger('order_item_id')->nullable();
            });
        }

        // الآن إضافة المفتاح الخارجي
        Schema::table('order_addons', function (Blueprint $table) {
            $table->foreign('order_item_id')->references('id')->on('order_items')->onDelete('cascade');
        });
    }

    public function down()
    {
        // في حالة التراجع، يجب أولاً حذف المفتاح الخارجي ثم العمود
        Schema::table('order_addons', function (Blueprint $table) {
            $table->dropForeign(['order_item_id']); // حذف المفتاح الخارجي
            $table->dropColumn('order_item_id');    // حذف العمود
        });
    }
};
