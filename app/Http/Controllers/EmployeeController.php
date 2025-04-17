<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\Role;

class EmployeeController extends Controller
{
    public function store(Request $request)
    {
        // $this->middleware('role:admin');

        $data = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:6',
            'role_id' => 'required|exists:roles,id',
            'branch_id' => 'nullable|exists:branches,id',
            'hourly_rate' => 'nullable|numeric|min:0',
        ]);

        $employee = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role_id' => $data['role_id'],
            'branch_id' => $data['branch_id'] ?? null,
            'hourly_rate' => $data['hourly_rate'] ?? null,
            'employee_code' => 'EMP-' . str_pad(User::count() + 1, 6, '0', STR_PAD_LEFT), // توليد employee_code تلقائيًا
            'leave_balance' => 4.00,
        ]);

        return response()->json(['message' => 'Employee added', 'employee' => $employee]);
    }

    public function updateHours(Request $request, $id)
    {
        $this->middleware('role:admin');

        $employee = User::findOrFail($id);
        $data = $request->validate([
            'work_hours' => 'required|integer|min:0',
        ]);

        $employee->update(['work_hours' => $data['work_hours']]);
        return response()->json(['message' => 'Work hours updated', 'employee' => $employee]);
    }

    public function calculateSalary($id)
    {
        $this->middleware('role:admin,super_admin');

        $employee = User::findOrFail($id);
        $salary = $employee->hourly_rate * $employee->work_hours;

        // تسجيل المصروف في الحسابات
        if ($salary > 0) {
            \App\Models\Account::create([
                'branch_id' => $employee->branch_id,
                'type' => 'expense',
                'description' => "Salary for {$employee->name}",
                'amount' => $salary,
                'date' => now(),
                'created_by' => auth()->id(),
            ]);
        }

        return response()->json(['employee' => $employee, 'salary' => $salary]);
    }
}