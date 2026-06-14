<?php
// app/Http/Resources/ProductImageResource.php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductImageResource extends JsonResource
{
    public function toArray($request)
    {
       
        $rawUrl = $this->getAttributes()['image_url'] ?? null;
        $rawUrl = $rawUrl ? trim($rawUrl) : null;
        $finalUrl = null;

        if ($rawUrl) {
            
            if (str_starts_with($rawUrl, 'http://') || str_starts_with($rawUrl, 'https://')) {
                $finalUrl = $rawUrl;
            }
          
            elseif (preg_match('#(https?://[^\s<<>"{}|\\^`\[\]]+)#i', $rawUrl, $matches)) {
                $finalUrl = $matches[1];
            }
           
            else {
                $finalUrl = asset('storage/' . ltrim($rawUrl, '/'));
            }
        }

        return [
            'image_id' => $this->image_id,
            'product_id' => $this->product_id,
            'image_url' => $finalUrl,
            'alt_text' => $this->alt_text,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
        ];
    }
}