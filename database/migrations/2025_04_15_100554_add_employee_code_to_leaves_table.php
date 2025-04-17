<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEmployeeCodeToLeavesTable extends Migration
{
    public function up()
    {
        Schema::table('leaves', function (Blueprint $table) {
            if (!Schema::hasColumn('leaves', 'employee_code')) {
                $table->string('employee_code')->after('id');
                $table->foreign('employee_code')->references('employee_code')->on('users')->onDelete('cascade');
            }
        });

    }

    public function down()
    {
        Schema::table('leaves', function (Blueprint $table) {
            $table->dropForeign(['employee_code']);
            $table->dropColumn('employee_code');
        });
    }
}