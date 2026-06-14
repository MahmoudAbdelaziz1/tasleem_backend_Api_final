<?php
// app/Http/Resources/ProductResource.php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'owner' => new UserResource($this->whenLoaded('owner')),
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'quantity' => $this->quantity,
            'view_count' => $this->view_count,
            'rate' => $this->rate,
            'pay_count' => $this->pay_count,
            'addingToCart_count' => $this->addingToCart_count,
            'status' => $this->status,
            'type' => $this->type,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
            'is_boosted'       => $this->isActivelyBoosted(),
            'boost_expires_at' => $this->boost_expires_at ? $this->boost_expires_at->format('Y-m-d H:i:s') : null,
        ];
    }
}