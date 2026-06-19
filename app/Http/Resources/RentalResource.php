<?php
// app/Http/Resources/RentalResource.php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RentalResource extends JsonResource
{
public function toArray($request)
{
    return [
        'rental_id'      => $this->rental_id,
        'product'        => new ProductResource($this->whenLoaded('product')), // product.owner MUST load
        'renter'         => new UserResource($this->whenLoaded('renter')),
        'status'         => $this->status,
        'daily_price'    => (float) $this->daily_price,
        'total_days'     => (int) $this->total_days,
        'total_price'    => (float) $this->total_price,
        'tasleem_fee'    => (float) $this->tasleem_fee,    
        'delivery_fee'   => (float) $this->delivery_fee,    
        'payment_method' => $this->payment_method,         
        'start_date'     => $this->start_date,
        'end_date'       => $this->end_date,
        'created_at'     => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
        'updated_at'     => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
    ];
}
}