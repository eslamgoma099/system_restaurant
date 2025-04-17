<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAttendanceTable extends Migration
{
    public function up()
    {
        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code', 191)->collation('utf8mb4_unicode_ci');
            $table->dateTime('check_in')->nullable();
            $table->dateTime('check_out')->nullable();
            $table->decimal('hours_worked', 5, 2)->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->timestamps();
        });

        // Add foreign key in a separate statement
        Schema::table('attendance', function (Blueprint $table) {
            $table->foreign('employee_code')
                  ->references('employee_code')
                  ->on('users')
                  ->onDelete('cascade');

            $table->foreign('branch_id')
                  ->references('id')
                  ->on('branches')
                  ->onDelete('set null');
        });
    }
        public function down()
    {
        Schema::dropIfExists('attendance');
    }
}