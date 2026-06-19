<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                     => $this->id,
            'name'                   => $this->name,
            'email'                  => $this->email,
            'phone'                  => $this->phone,
            'national_id'            => $this->national_id, 
            'address'                => $this->address,
            'city'                   => $this->city,
            'post_code'              => $this->post_code,
            'user_photo'             => $this->user_photo ? asset('storage/' . $this->user_photo) : null,
            'role'                   => $this->role,
            'status'                 => $this->status,
            'wallet_balance'         => (float) $this->wallet_balance,
            'free_sales_remaining'   => (int) $this->free_sales_remaining, // ✅ أضف ده
            'created_at'             => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at'             => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
        ];
    }
}