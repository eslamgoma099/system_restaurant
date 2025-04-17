<?php

namespace App\Http\Controllers;
use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{

    public function index()
{
    return response()->json(Role::all());
}

public function store(Request $request)
{
    $data = $request->validate(['name' => 'required|string|unique:roles']);
    $role = Role::create($data);
    return response()->json(['message' => 'تم إنشاء الدور', 'role' => $role], 201);
}

public function update(Request $request, Role $role)
{
    $data = $request->validate(['name' => 'required|string|unique:roles,name,' . $role->id]);
    $role->update($data);
    return response()->json(['message' => 'تم تحديث الدور', 'role' => $role]);
}

public function destroy(Role $role)
{
    $role->delete();
    return response()->json(['message' => 'تم حذف الدور']);
}
}
