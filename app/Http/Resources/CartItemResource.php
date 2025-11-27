<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            'price' => (float) $this->price,
            'subtotal' => (float) $this->subtotal,

            // Product details
            'product' => $this->when($this->relationLoaded('product'), function () {
                return [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'slug' => $this->product->slug,
                    'price' => (float) $this->product->price,
                    'sale_price' => $this->product->sale_price ? (float) $this->product->sale_price : null,
                    'effective_price' => (float) $this->product->effective_price,
                    'stock_quantity' => $this->product->stock_quantity,
                    'in_stock' => $this->product->stock_quantity > 0,
                    'primary_image' => $this->product->images->firstWhere('is_primary', true)?->image_path,
                ];
            }),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
