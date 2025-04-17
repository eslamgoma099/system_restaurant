<?php
namespace App\Http\Controllers;

use App\Models\Reservation;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function store(Request $request)
    {
        $this->middleware('role:employee,cashier');

        $data = $request->validate([
            'table_number' => 'required|integer',
            'customer_name' => 'required|string',
            'reservation_time' => 'required|date|after:now',
        ]);

        // التحقق من توفر الطاولة
        $existing = Reservation::where('branch_id', auth()->user()->branch_id)
            ->where('table_number', $data['table_number'])
            ->where('reservation_time', $data['reservation_time'])
            ->where('status', '!=', 'cancelled')
            ->exists();

        if ($existing) {
            return response()->json(['message' => 'Table is already reserved at this time'], 400);
        }

        $reservation = Reservation::create([
            'table_number' => $data['table_number'],
            'customer_name' => $data['customer_name'],
            'branch_id' => auth()->user()->branch_id,
            'reservation_time' => $data['reservation_time'],
            'status' => 'pending',
        ]);

        return response()->json(['message' => 'Reservation created', 'reservation' => $reservation]);
    }

    public function index()
    {
        $this->middleware('role:employee,cashier,admin');

        $reservations = Reservation::where('branch_id', auth()->user()->branch_id)
            ->where('reservation_time', '>=', now())
            ->get();

        return response()->json(['reservations' => $reservations]);
    }

    public function updateStatus(Request $request, $id)
    {
        $this->middleware('role:employee,admin');

        $reservation = Reservation::findOrFail($id);
        $data = $request->validate([
            'status' => 'required|in:pending,confirmed,cancelled',
        ]);

        $reservation->update(['status' => $data['status']]);
        return response()->json(['message' => 'Reservation status updated', 'reservation' => $reservation]);
    }
}