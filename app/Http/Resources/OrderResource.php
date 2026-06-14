<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'order_id'     => $this->order_id,
            'user'         => new UserResource($this->whenLoaded('user')),
            'product'      => new ProductResource($this->whenLoaded('product')),
            'quantity'     => $this->quantity,
            'unit_price'   => $this->unit_price,
            'total_price'  => $this->total_price,
            'tasleem_fee'  => (float) $this->tasleem_fee,
            'delivery_fee' => (float) $this->delivery_fee,
            'status'       => $this->status,
            'payment'      => new PaymentResource($this->whenLoaded('payment')),
            'created_at'   => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at'   => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
        ];
    }
}