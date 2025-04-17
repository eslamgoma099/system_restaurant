<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class ShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cashier' => $this->cashier->name,
            'opening_balance' => number_format($this->opening_balance, 2),
            'start_time' => $this->start_time ? Carbon::parse($this->start_time)->toDateTimeString() : null,
            'end_time' => $this->end_time ? Carbon::parse($this->end_time)->toDateTimeString() : null,
            'total_payments' => number_format($this->total_payments, 2),
            'total_expenses' => number_format($this->total_expenses, 2),
            'final_balance' => number_format($this->calculateFinalBalance(), 2),  // استخدام طريقة الحساب
            'expenses' => $this->expenses->map(function ($expense) {
                return [
                    'type' => $expense->type,
                    'description' => $expense->description,
                    'amount' => number_format($expense->amount, 2),
                ];
            }),
            'payments' => $this->payments->map(function ($payment) {
                return [
                    'order_id' => $payment->order_id,
                    'amount' => number_format($payment->amount, 2),
                    'payment_method' => $payment->payment_method,
                    'payment_date' => $payment->payment_date ? Carbon::parse($payment->payment_date)->toDateTimeString() : null,
                ];
            }),
        ];
    }
}
