<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEmployeeCodeAndLeaveBalanceToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'employee_code')) {
                $table->string('employee_code', 191)
                      ->collation('utf8mb4_unicode_ci')
                      ->unique()
                      ->after('id');
            }

            if (!Schema::hasColumn('users', 'leave_balance')) {
                $table->decimal('leave_balance', 5, 2)
                      ->default(4.00)
                      ->after('employee_code');
            }
        });

        // Generate employee codes in a separate statement
        \App\Models\User::whereNull('employee_code')->get()->each(function ($user) {
            $user->employee_code = 'EMP-' . str_pad($user->id, 6, '0', STR_PAD_LEFT);
            $user->save();
        });
    }
}