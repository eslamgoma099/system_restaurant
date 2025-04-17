<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        // تحديد اللغة من الـ Header أو الافتراضي إلى 'en'
        $locale = $request->header('Accept-Language', 'en');
        if (in_array($locale, ['en', 'ar'])) {
            App::setLocale($locale);
        }

        // بناء الروابط بناءً على الصلاحيات
        $links = [];

        if ($user->role === 'super_admin' || $user->role === 'admin') {
            $links['inventory'] = [
                'low_stock_alerts' => url('/api/inventory/low-stock'),
                'supply_requests' => url('/api/supply-requests'),
                'auto_request' => url('/api/supply-requests/auto'),
            ];
            $links['payroll'] = [
                'payroll_report' => url('/api/payroll'),
                'pay_employee' => url('/api/payroll/' . $user->id . '/pay'),
            ];
        }

        if ($user->role === 'admin' || $user->role === 'employee' || $user->role === 'cashier') {
            $links['orders'] = [
                'create_order' => url('/api/orders'),
                'process_payment' => url('/api/orders/1/payment'), // مثال، يمكن جعله ديناميكيًا
            ];
        }

        if ($user->role === 'cashier') {
            $links['shifts'] = [
                'start_shift' => url('/api/shifts/start'),
                'end_shift' => url('/api/shifts/1/end'), // مثال، يمكن جعله ديناميكيًا
                'add_expense' => url('/api/shifts/1/expenses'),
                'shift_report' => url('/api/shifts/report'),
            ];
        }

        // بناء الرد مع الترجمة
        $response = [
            'message' => trans('dashboard.welcome', ['name' => $user->name]),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'branch_id' => $user->branch_id,
            ],
            'links' => [
                'shifts' => isset($links['shifts']) ? [
                    'title' => trans('dashboard.shifts'),
                    'items' => [
                        ['name' => trans('dashboard.start_shift'), 'url' => $links['shifts']['start_shift'], 'method' => 'POST'],
                        ['name' => trans('dashboard.end_shift'), 'url' => $links['shifts']['end_shift'], 'method' => 'POST'],
                        ['name' => trans('dashboard.add_expense'), 'url' => $links['shifts']['add_expense'], 'method' => 'POST'],
                        ['name' => trans('dashboard.shift_report'), 'url' => $links['shifts']['shift_report'], 'method' => 'GET'],
                    ],
                ] : null,
                'orders' => isset($links['orders']) ? [
                    'title' => trans('dashboard.orders'),
                    'items' => [
                        ['name' => trans('dashboard.create_order'), 'url' => $links['orders']['create_order'], 'method' => 'POST'],
                        ['name' => trans('dashboard.process_payment'), 'url' => $links['orders']['process_payment'], 'method' => 'POST'],
                    ],
                ] : null,
                'inventory' => isset($links['inventory']) ? [
                    'title' => trans('dashboard.inventory'),
                    'items' => [
                        ['name' => trans('dashboard.low_stock_alerts'), 'url' => $links['inventory']['low_stock_alerts'], 'method' => 'GET'],
                        ['name' => trans('dashboard.supply_requests'), 'url' => $links['inventory']['supply_requests'], 'method' => 'GET'],
                        ['name' => trans('dashboard.auto_request'), 'url' => $links['inventory']['auto_request'], 'method' => 'POST'],
                    ],
                ] : null,
                'payroll' => isset($links['payroll']) ? [
                    'title' => trans('dashboard.payroll'),
                    'items' => [
                        ['name' => trans('dashboard.payroll_report'), 'url' => $links['payroll']['payroll_report'], 'method' => 'GET'],
                        ['name' => trans('dashboard.pay_employee'), 'url' => $links['payroll']['pay_employee'], 'method' => 'POST'],
                    ],
                ] : null,
            ],
            'logout' => [
                'name' => trans('dashboard.logout'),
                'url' => url('/api/logout'),
                'method' => 'POST',
            ],
            'current_language' => App::getLocale(),
            'available_languages' => [
                ['name' => trans('dashboard.english'), 'code' => 'en'],
                ['name' => trans('dashboard.arabic'), 'code' => 'ar'],
            ],
        ];

        return response()->json($response);
    }
}