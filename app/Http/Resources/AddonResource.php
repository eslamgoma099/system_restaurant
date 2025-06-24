<?php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AddonResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'addon_price' => $this->addon_price ?? $this->price ?? null,
            'quantity' => $this->quantity ?? null,
            'ingredient_id' => $this->ingredient_id ?? null,
            'ingredient_name' => $this->ingredient->name ?? null,

            // أضف أي حقول أخرى تحتاجها هنا
        ];
    }
}




