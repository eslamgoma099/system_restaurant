<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyUsersTableToUseRoleId extends Migration
{
    public function up()
    {
        // الخطوة 1: إضافة العمود role_id أولاً
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->nullable()->after('email');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('set null');
        });

        // الخطوة 2: تحويل قيم role الحالية إلى role_id بعد إنشاء العمود
        $superAdminRole = \App\Models\Role::where('name', 'super_admin')->first();
        $adminRole = \App\Models\Role::where('name', 'admin')->first();
        $cashierRole = \App\Models\Role::where('name', 'cashier')->first();
        $employeeRole = \App\Models\Role::where('name', 'employee')->first();

        if (!$superAdminRole || !$adminRole || !$cashierRole || !$employeeRole) {
            throw new \Exception('الأدوار المطلوبة غير موجودة في جدول roles. تأكد من تشغيل migration جدول roles أولاً.');
        }

        $roleMap = [
            'super_admin' => $superAdminRole->id,
            'admin' => $adminRole->id,
            'cashier' => $cashierRole->id,
            'employee' => $employeeRole->id,
        ];

        foreach (\App\Models\User::all() as $user) {
            if (isset($roleMap[$user->role])) {
                $user->role_id = $roleMap[$user->role];
                $user->save();
            } else {
                $user->role_id = null; // أو يمكنك تعيين دور افتراضي
                $user->save();
            }
        }

        // الخطوة 3: حذف العمود role بعد تحويل القيم
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down()
    {
        // إعادة العمود role
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->nullable()->after('email');
        });

        // تحويل role_id إلى role
        $superAdminRole = \App\Models\Role::where('name', 'super_admin')->first();
        $adminRole = \App\Models\Role::where('name', 'admin')->first();
        $cashierRole = \App\Models\Role::where('name', 'cashier')->first();
        $employeeRole = \App\Models\Role::where('name', 'employee')->first();

        if ($superAdminRole && $adminRole && $cashierRole && $employeeRole) {
            $roleMap = [
                $superAdminRole->id => 'super_admin',
                $adminRole->id => 'admin',
                $cashierRole->id => 'cashier',
                $employeeRole->id => 'employee',
            ];

            foreach (\App\Models\User::all() as $user) {
                if (isset($roleMap[$user->role_id])) {
                    $user->role = $roleMap[$user->role_id];
                    $user->save();
                }
            }
        }

        // حذف العمود role_id
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');
        });
    }
}