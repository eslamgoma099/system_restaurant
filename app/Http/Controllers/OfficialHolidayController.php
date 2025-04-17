<?php
namespace App\Http\Controllers;

use App\Models\OfficialHoliday;
use Illuminate\Http\Request;

class OfficialHolidayController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:super_admin');
    }

    public function index()
    {
        $holidays = OfficialHoliday::with('branch')->get();
        return response()->json([
            'message' => 'قائمة الإجازات الرسمية',
            'holidays' => $holidays,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'date' => 'required|date',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $holiday = OfficialHoliday::create($data);

        return response()->json([
            'message' => 'تم إضافة الإجازة الرسمية بنجاح',
            'holiday' => $holiday,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $holiday = OfficialHoliday::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string',
            'date' => 'required|date',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $holiday->update($data);

        return response()->json([
            'message' => 'تم تحديث الإجازة الرسمية بنجاح',
            'holiday' => $holiday,
        ]);
    }

    public function destroy($id)
    {
        $holiday = OfficialHoliday::findOrFail($id);
        $holiday->delete();

        return response()->json([
            'message' => 'تم حذف الإجازة الرسمية بنجاح',
        ]);
    }
}