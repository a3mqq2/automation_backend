<?php

namespace App\Http\Resources\Client;

use App\Models\ProductPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'facebook_page_id' => $this->facebook_page_id,
            'name' => $this->name,
            'sku' => $this->sku,
            'price' => $this->price === null ? null : (float) $this->price,
            'formatted_price' => $this->formattedPrice(),
            'currency' => $this->currency,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'product_url' => $this->product_url,
            'in_stock' => $this->in_stock,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'category' => $this->whenLoaded('category', fn () => $this->category === null ? null : [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'brand' => $this->whenLoaded('brand', fn () => $this->brand === null ? null : [
                'id' => $this->brand->id,
                'name' => $this->brand->name,
            ]),
            'post_ids' => $this->whenLoaded('posts', fn () => $this->posts->map(fn (ProductPost $post) => $post->post_id)->values()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
