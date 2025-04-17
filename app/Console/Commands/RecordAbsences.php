<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Absence;
use App\Models\OfficialHoliday;
use Carbon\Carbon;

class RecordAbsences extends Command
{
    protected $signature = 'absences:record';
    protected $description = 'Record absences for employees based on attendance';

    public function handle()
    {
        $yesterday = Carbon::yesterday('Africa/Cairo');

        // استثناء أيام الجمعة والسبت
        if ($yesterday->isSaturday() || $yesterday->isFriday()) {
            \Log::info('Skipping absence recording for weekend: ' . $yesterday->toDateString());
            $this->info('Skipping absence recording for weekend: ' . $yesterday->toDateString());
            return;
        }

        // التحقق مما إذا كان اليوم إجازة رسمية
        $isOfficialHoliday = OfficialHoliday::where('holiday_date', $yesterday->toDateString())->exists();

        // استرجاع جميع الموظفين (employee وcashier)
        $employeeRoleId = \App\Models\Role::where('name', 'employee')->first()->id;
        $cashierRoleId = \App\Models\Role::where('name', 'cashier')->first()->id;

        $employees = User::whereIn('role_id', [$employeeRoleId, $cashierRoleId])->get();

        foreach ($employees as $employee) {
            // التحقق مما إذا كان الموظف لم يسجل حضورًا
            $attendance = Attendance::where('employee_code', $employee->employee_code)
                ->whereDate('check_in', $yesterday)
                ->first();

            if (!$attendance) {
                // تسجيل الغياب
                Absence::create([
                    'employee_code' => $employee->employee_code,
                    'absence_date' => $yesterday,
                    'is_official_holiday' => $isOfficialHoliday,
                ]);

                \Log::info("Recorded absence for employee {$employee->employee_code} on {$yesterday->toDateString()}");
            }
        }

        \Log::info('Absences recorded successfully for ' . $yesterday->toDateString());
        $this->info('Absences recorded successfully for ' . $yesterday->toDateString());
    }
}