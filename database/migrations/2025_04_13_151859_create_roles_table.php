<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRolesTable extends Migration
{
    public function up()
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // إضافة الأدوار الافتراضية
        \App\Models\Role::create(['name' => 'super_admin']);
        \App\Models\Role::create(['name' => 'admin']);
        \App\Models\Role::create(['name' => 'cashier']);
        \App\Models\Role::create(['name' => 'employee']);
    }

    public function down()
    {
        Schema::dropIfExists('roles');
    }
}