<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'price' => (float) $this->price,
            'sale_price' => $this->sale_price ? (float) $this->sale_price : null,
            'effective_price' => (float) $this->effective_price,
            'discount_percentage' => $this->sale_price
                ? round((($this->price - $this->sale_price) / $this->price) * 100)
                : 0,
            'stock_quantity' => $this->stock_quantity,
            'in_stock' => $this->stock_quantity > 0,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'weight' => $this->weight ? (float) $this->weight : null,
            'meta_data' => $this->meta_data,

            // Relationships
            'category' => new CategoryResource($this->whenLoaded('category')),
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'primary_image' => $this->when(
                $this->relationLoaded('images'),
                function () {
                    $primary = $this->images->firstWhere('is_primary', true);
                    return $primary ? new ProductImageResource($primary) : null;
                }
            ),

            // Aggregates
            'reviews_count' => $this->when($this->reviews_count !== null, $this->reviews_count),
            'average_rating' => $this->when($this->reviews_avg_rating !== null, round($this->reviews_avg_rating, 1)),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
