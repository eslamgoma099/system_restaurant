<?php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'item_name' => $this->item->name ?? 'N/A',
            'quantity' => $this->quantity ?? null,
            'price' => $this->price ?? null,
            // أضف أي حقول أخرى تحتاجها هنا
        ];
    }
}
