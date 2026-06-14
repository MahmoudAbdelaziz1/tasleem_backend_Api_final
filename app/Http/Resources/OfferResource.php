<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OfferResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'         => $this->id,
            'product'    => new ProductResource($this->whenLoaded('product')),
            'buyer'      => new UserResource($this->whenLoaded('buyer')),
            'seller'     => new UserResource($this->whenLoaded('seller')),
            'amount'     => (float)$this->amount,
            'status'     => $this->status,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
        ];
    }
}