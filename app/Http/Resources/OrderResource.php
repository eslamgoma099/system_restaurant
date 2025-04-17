<?php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray($request)
    {
        $subTotal = $this->items->sum(function ($orderItem) {
            return $orderItem->quantity * $orderItem->price;
        }) + $this->addons->sum('addon_price');

        $discount = 0;
        $activeOffers = \App\Models\Offer::where('branch_id', $this->branch_id)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->first();
        if ($activeOffers) {
            $discount = $subTotal * ($activeOffers->discount_percentage / 100);
        }

        $serviceFee = $this->order_type === 'dine_in' ? ($subTotal - $discount) * 0.10 : 0;

        $deliveryFee = 0;
        if ($this->order_type === 'delivery' && $this->customerLocation && $this->branch) {
            $earthRadius = 6371; // نصف قطر الأرض بالكيلومترات
            $latFrom = deg2rad($this->branch->latitude);
            $lonFrom = deg2rad($this->branch->longitude);
            $latTo = deg2rad($this->customerLocation->latitude);
            $lonTo = deg2rad($this->customerLocation->longitude);

            $latDelta = $latTo - $latFrom;
            $lonDelta = $lonTo - $lonFrom;

            $a = sin($latDelta / 2) * sin($latDelta / 2) +
                 cos($latFrom) * cos($latTo) *
                 sin($lonDelta / 2) * sin($lonDelta / 2);
            $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
            $distance = $earthRadius * $c; // المسافة بالكيلومترات

            $deliveryFee = $distance * 2; // 2 ريال لكل كيلومتر
        }

        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'order_type' => $this->order_type,
            'payment_status' => $this->payment_status,
            'table_number' => $this->table_number,
            'customer_location' => $this->customerLocation ? [
                'id' => $this->customerLocation->id,
                'customer_id' => $this->customerLocation->customer_id,
                'customer_name' => $this->customerLocation->customer ? $this->customerLocation->customer->name : 'N/A',
                'address' => $this->customerLocation->address,
                'latitude' => $this->customerLocation->latitude,
                'longitude' => $this->customerLocation->longitude,
            ] : null,
            'sub_total' => $subTotal,
            'discount' => $discount,
            'service_fee' => $serviceFee,
            'delivery_fee' => $deliveryFee,
            'total_price' => $this->total_price,
            'status' => $this->status,
            'employee_id' => $this->employee_id,
            'cashier_id' => $this->cashier_id,
            'created_at' => $this->created_at->toDateTimeString(),
            'items' => $this->items->map(function ($orderItem) {
                return [
                    'id' => $orderItem->id,
                    'item_id' => $orderItem->item_id,
                    'name' => $orderItem->item->name,
                    'quantity' => $orderItem->quantity,
                    'price' => $orderItem->price,
                ];
            }),
            'addons' => $this->addons->map(function ($addon) {
                return [
                    'id' => $addon->id,
                    'name' => $addon->ingredient ? $addon->ingredient->name : 'N/A',
                    'price' => $addon->addon_price,
                    'quantity'=>$addon->quantity,
                ];
            }),
        ];
    }
}