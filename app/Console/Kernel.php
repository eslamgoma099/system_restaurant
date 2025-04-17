<?php
namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use App\Http\Controllers\PayrollController;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        // جدولة تسجيل الغياب يوميًا في منتصف الليل بتوقيت القاهرة
        $schedule->command('absences:record')
                 ->dailyAt('00:00');
        $schedule->call(function () {
            PayrollController::resetLeaveBalances();
        })->monthlyOn(1, '00:00'); // تشغيل في اليوم الأول من كل شهر بتوقيت القاهرة (إذا قمت بضبط timezone في config/app.php)
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}