<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\DailyClosureController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\LoyaltyController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\SupplyRequestController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\AdvanceController;
use App\Http\Controllers\OfficialHolidayController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\MainItemController;



/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Routes عامة (لا تتطلب تسجيل دخول)
Route::post('/register', [AuthController::class, 'register']); // التسجيل (Super Admin فقط)
Route::post('/login', [AuthController::class, 'login']); // تسجيل الدخول
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Routes محمية بتسجيل الدخول
Route::middleware('auth:sanctum')->group(function () {
    // Route للحصول على بيانات المستخدم المسجل
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::prefix('roles')->middleware('role:super_admin')->group(function () {
        Route::get('/', [RoleController::class, 'index']);
        Route::post('/', [RoleController::class, 'store']);
        Route::put('/{role}', [RoleController::class, 'update']);
        Route::delete('/{role}', [RoleController::class, 'destroy']);
    });
    // تسجيل الخروج
    Route::post('/logout', [AuthController::class, 'logout']); // محمي بـ auth:sanctum
    Route::get('/profile', [AuthController::class, 'showProfile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    // إدارة الفروع (Super Admin فقط)
    Route::prefix('branches')->group(function () {
        Route::post('/', [BranchController::class, 'store'])->middleware('role:super_admin,admin');
        Route::post('/{id}show', [BranchController::class, 'show'])->middleware('role:super_admin');
        Route::post('/{id}update', [BranchController::class, 'update'])->middleware('role:super_admin');

    });

    // إدارة الورديات
    Route::prefix('shifts')->group(function () {
        Route::post('/start', [ShiftController::class, 'startShift'])->middleware('role:admin,cashier');
        Route::post('/{id}/end', [ShiftController::class, 'endShift'])->middleware('role:admin,cashier');
        Route::post('/{shiftId}/expenses', [ShiftController::class, 'addExpense'])->middleware('role:cashier'); // الكاشير فقط
        Route::get('/report', [ShiftController::class, 'shiftReport'])->middleware('role:admin,cashier,super_admin');
        Route::get('/daily-report', [ShiftController::class, 'dailyReport'])->middleware('role:admin,cashier');
        Route::get('/', [ShiftController::class, 'index'])->middleware('role:admin,cashier');
    });
    Route::prefix('attendance')->group(function () {
        Route::post('/check-in', [AttendanceController::class, 'checkIn']);
        Route::post('/check-out', [AttendanceController::class, 'checkOut']);
        Route::get('/report', [AttendanceController::class, 'report']);
    });

    Route::prefix('leaves')->group(function () {
        Route::post('/request', [LeaveController::class, 'requestLeave']);
        Route::post('/{id}/approve', [LeaveController::class, 'approveLeave']);
        Route::post('/{id}/reject', [LeaveController::class, 'rejectLeave']);
        Route::get('/report', [LeaveController::class, 'report']);
    });

    Route::prefix('official-holidays')->group(function () {
        Route::get('/', [OfficialHolidayController::class, 'index']);
        Route::post('/', [OfficialHolidayController::class, 'store']);
        Route::put('/{id}', [OfficialHolidayController::class, 'update']);
        Route::delete('/{id}', [OfficialHolidayController::class, 'destroy']);
    });

    Route::prefix('advances')->group(function () {
        Route::post('/request', [AdvanceController::class, 'requestAdvance']);
        Route::get('/report', [AdvanceController::class, 'report']);
    });

    Route::prefix('payroll')->group(function () {
        Route::get('/', [PayrollController::class, 'index']);
        Route::post('/{userId}/pay', [PayrollController::class, 'payEmployee']);
        Route::post('/{id}/hourly-rate', [PayrollController::class, 'updateHourlyRate']);
        Route::post('/reset-leave-balances', [PayrollController::class, 'resetLeaveBalances']);
        Route::get('/absences/report', [PayrollController::class, 'absenceReport']);
    });
    // إدارة الطلبات
    Route::prefix('orders')->group(function () {
        Route::post('/', [OrderController::class, 'store'])->middleware('role:employee,super_admin,admin,cashier'); // الموظف، الكاشير، المدير
        Route::put('/{id}', [OrderController::class, 'update'])->middleware('role:employee,super_admin,admin,cashier'); // الموظف، الكاشير، المدير
        Route::delete('/{id}', [OrderController::class, 'destroy'])->middleware('role:super_admin,admin'); // مدير الفرع فقط
        Route::get('/', [OrderController::class, 'index'])->middleware('role:employee,cashier,super_admin,admin'); // عرض الطلبات
        Route::post('/payment', [OrderController::class, 'makePayment'])->middleware('role:cashier,admin'); // الكاشير والمدير فقط
        Route::get('/{id}/print', [OrderController::class, 'printOrder'])->middleware('role:employee,cashier,admin');
        Route::post('/refundPayment', [OrderController::class, 'refundPayment'])->middleware('role:cashier,admin'); // الكاشير والمدير فقط
        Route::post('/paymob/initiate', [OrderController::class, 'initiatePaymobPayment'])->middleware('role:cashier,admin,super_admin');
        Route::post('/paymob/callback', [OrderController::class, 'paymobCallback'])->middleware('role:cashier,admin,super_admin');


    });

    // إدارة المخزون
    Route::prefix('inventory')->group(function () {
        Route::post('/{ingredientId}/add-stock', [InventoryController::class, 'addStock'])->middleware('role:admin'); // إضافة المخزون
        Route::get('/', [InventoryController::class, 'index'])->middleware('role:admin,super_admin,cashier'); // عرض المخزون
        Route::put('/{itemId}', [InventoryController::class, 'updateStock'])->middleware('role:admin'); // تحديث المخزون (المدير فقط)
        Route::get('/low-stock', [InventoryController::class, 'lowStockAlerts'])->middleware('role:admin'); // تنبيهات المخزون (المدير فقط)
    });

    // إدارة الحسابات
    Route::prefix('accounts')->group(function () {
        Route::post('/', [AccountController::class, 'store'])->middleware('role:super_admin,admin'); // إضافة حسابات
        Route::get('/', [AccountController::class, 'index'])->middleware('role:super_admin,admin'); // عرض الحسابات
        Route::get('/summary', [AccountController::class, 'summary'])->middleware('role:super_admin,admin'); // ملخص الحسابات (Super Admin فقط)
        // Route::get('/export-financial-report', [AccountController::class, 'exportFinancialReport'])->middleware('role:super_admin');
    });

    // التسويات اليومية
    Route::prefix('daily-closures')->group(function () {
        Route::post('/', [DailyClosureController::class, 'store'])->middleware('role:cashier,admin'); // الكاشير ومدير الفرع
        Route::get('/', [DailyClosureController::class, 'index'])->middleware('role:admin,super_admin'); // عرض التسويات
    });

    // إدارة الموظفين
    Route::prefix('employees')->group(function () {
        Route::post('/', [EmployeeController::class, 'store'])->middleware('role:admin,super_admin'); // إضافة موظف
        Route::put('/{id}/hours', [EmployeeController::class, 'updateHours'])->middleware('role:admin,super_admin'); // تحديث الساعات
        Route::get('/{id}/salary', [EmployeeController::class, 'calculateSalary'])->middleware('role:admin,super_admin'); // حساب الراتب
    });

    // التقارير
    Route::prefix('reports')->group(function () {
        Route::get('/sales', [ReportController::class, 'salesReport'])->middleware('role:admin,super_admin'); // تقرير المبيعات
        Route::get('/financial', [ReportController::class, 'financialReport'])->middleware('role:super_admin,admin'); // تقرير مالي (Super Admin فقط)
        Route::get('/daily-closures', [ReportController::class, 'dailyClosureReport'])->middleware('role:admin,super_admin'); // تقرير التسويات
        Route::get('/item-sales', [ReportController::class, 'itemSalesAnalysis'])->middleware('role:admin,super_admin'); // تحليل مبيعات الأصناف
    });

    // العروض
    Route::prefix('offers')->group(function () {
        Route::post('/', [OfferController::class, 'store'])->middleware('role:admin,super_admin'); // إنشاء عرض
        Route::get('/', [OfferController::class, 'index'])->middleware('role:admin,cashier,employee'); // عرض العروض النشطة
    });

    Route::prefix('main-items')->group(function () {
        Route::post('/', [MainItemController::class, 'store'])->middleware('role:admin,super_admin'); // إضافة مقدمة
        Route::get('/', [MainItemController::class, 'index'])->middleware('role:admin,super_admin'); // عرض المقدمات
        Route::get('/{id}', [MainItemController::class, 'show'])->middleware('role:admin,super_admin'); // عرض تفاصيل المقدمة
        Route::put('/{id}', [MainItemController::class, 'update'])->middleware('role:admin,super_admin'); // تحديث المقدمة
        Route::delete('/{id}', [MainItemController::class, 'destroy'])->middleware('role:admin,super_admin'); // حذف المقدمة
    });
    // إدارة الأصناف
    Route::prefix('items')->group(function () {
        Route::get('/', [ItemController::class, 'index'])->middleware('role:admin,super_admin,cashier,employee'); // عرض الأصناف
        Route::post('/', [ItemController::class, 'store'])->middleware('role:admin'); // إضافة صنف
        Route::get('/{id}', [ItemController::class, 'show'])->middleware('role:admin,cashier,employee'); // عرض تفاصيل الصنف
        Route::post('/ingredients', [\App\Http\Controllers\IngredientController::class, 'store'])->middleware('role:admin,super_admin');
    });

    // برنامج الولاء
    Route::prefix('loyalty')->group(function () {
        Route::post('/customers', [LoyaltyController::class, 'registerCustomer'])->middleware('role:cashier,admin'); // تسجيل عميل
        Route::post('/points', [LoyaltyController::class, 'earnPoints'])->middleware('role:cashier,admin'); // إضافة نقاط
        Route::get('/customers/{customerId}/points', [LoyaltyController::class, 'getCustomerPoints'])->middleware('role:cashier,admin'); // عرض نقاط العميل
    });

    // إدارة العملاء
    Route::prefix('customers')->group(function () {
        Route::apiResource('/', CustomerController::class)->middleware('role:cashier,admin'); // إدارة العملاء (CRUD)
        Route::post('/{customer}/loyalty-points', [CustomerController::class, 'addLoyaltyPoints'])->middleware('role:cashier,admin'); // إضافة نقاط ولاء
    })->middleware('role:admin,cashier'); // تقييد إدارة العملاء للأدوار المناسبة

    // الحجوزات
    Route::prefix('reservations')->group(function () {
        Route::post('/', [ReservationController::class, 'store'])->middleware('role:employee,cashier,admin'); // إنشاء حجز
        Route::get('/', [ReservationController::class, 'index'])->middleware('role:employee,cashier,admin'); // عرض الحجوزات
        Route::put('/{id}/status', [ReservationController::class, 'updateStatus'])->middleware('role:employee,admin'); // تحديث حالة الحجز
    });

    // طلبات التوريد
    Route::prefix('supply-requests')->group(function () {
        Route::post('/', [SupplyRequestController::class, 'store'])->middleware('role:admin,super_admin'); // إنشاء طلب يدوي
        Route::post('/auto', [SupplyRequestController::class, 'autoRequest'])->middleware('role:admin,super_admin'); // طلب تلقائي
        Route::get('/', [SupplyRequestController::class, 'index'])->middleware('role:admin,super_admin'); // عرض الطلبات
        Route::put('/{id}/status', [SupplyRequestController::class, 'updateStatus'])->middleware('role:admin,super_admin'); // تحديث حالة الطلب

    });

    // إدارة الرواتب
    Route::prefix('payroll')->group(function () {
        Route::get('/', [PayrollController::class, 'index'])->middleware('role:admin,super_admin');
        Route::post('/{userId}/pay', [PayrollController::class, 'payEmployee'])->middleware('role:admin,super_admin');
        Route::post('/{userId}/hourly_rate', [PayrollController::class, 'updateHourlyRate'])->middleware('role:admin,super_admin');

    });

    // لوحة التحكم
    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('role:admin,super_admin,cashier,employee');

    // Route لاختبار البريد الإلكتروني (يُستخدم في التطوير فقط)
    if (app()->environment('local')) { // تقييد للبيئة المحلية فقط
        Route::get('/test-email', function () {
            \Log::info('Testing email with SendGrid', [
                'username' => env('MAIL_USERNAME'),
                'password' => env('MAIL_PASSWORD'),
                'host' => env('MAIL_HOST'),
                'port' => env('MAIL_PORT'),
            ]);
            $user = \App\Models\User::find(1);
            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }
            try {
                $user->notify(new \App\Notifications\LowStockNotification('Burger', 5));
                return response()->json(['message' => 'Email queued']);
            } catch (\Exception $e) {
                \Log::error('Email sending failed: ' . $e->getMessage());
                return response()->json(['message' => 'Email failed', 'error' => $e->getMessage()], 500);
            }
        })->middleware('role:super_admin'); // Super Admin فقط
    }
});