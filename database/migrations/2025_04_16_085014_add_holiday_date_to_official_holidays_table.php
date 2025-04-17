<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHolidayDateToOfficialHolidaysTable extends Migration
{
    public function up()
    {
        Schema::table('official_holidays', function (Blueprint $table) {
            $table->date('holiday_date')->nullable()->after('id');
                });
    }

    public function down()
    {
        Schema::table('official_holidays', function (Blueprint $table) {
            $table->dropColumn('holiday_date');
        });
    }
}