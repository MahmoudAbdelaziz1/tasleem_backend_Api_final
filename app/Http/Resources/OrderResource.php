<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
public function toArray($request)
{
    return [
        'id' => $this->id,
        'name' => $this->name,
        'description' => $this->description,
        'price' => (float) $this->price,
        'quantity' => $this->quantity,
        'status' => $this->status,
        'type' => $this->type,
        'category' => new CategoryResource($this->whenLoaded('category')),
        'owner' => new UserResource($this->whenLoaded('owner')), // ✅ هذا السطر مهم جداً
        'images' => ProductImageResource::collection($this->whenLoaded('images')),
        'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
        'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
    ];
}
}