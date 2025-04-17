<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUserIdAndTypeToExpensesTable extends Migration
{
    public function up()
    {
        Schema::table('expenses', function (Blueprint $table) {
            // إضافة user_id إذا لم يكن موجودًا
            if (!Schema::hasColumn('expenses', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('shift_id');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            }

            // إضافة type إذا لم يكن موجودًا
            if (!Schema::hasColumn('expenses', 'type')) {
                $table->enum('type', ['general', 'advance'])->default('general')->after('shift_id');
            }
        });
    }

    public function down()
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::hasColumn('expenses', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
            if (Schema::hasColumn('expenses', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
}