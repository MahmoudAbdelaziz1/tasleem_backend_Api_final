<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
public function toArray($request)
{
    return [
        'order_id'       => $this->order_id,
        'user'           => new UserResource($this->whenLoaded('user')),
        'product'        => new ProductResource($this->whenLoaded('product')),
        'quantity'       => $this->quantity,
        'unit_price'     => $this->unit_price,
        'total_price'    => $this->total_price,
        'status'         => $this->status,
        'tasleem_fee'    => $this->tasleem_fee,
        'delivery_fee'   => $this->delivery_fee,
        'payment'        => new PaymentResource($this->whenLoaded('payment')),
        'payment_method' => $this->payment?->payment_method,
        'created_at'     => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
        'updated_at'     => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
    ];
}
}