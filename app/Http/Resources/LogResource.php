<?php
// app/Http/Resources/LogResource.php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LogResource extends JsonResource
{
public function toArray($request)
{
    return [
        'log_id' => $this->log_id,
        'user' => new UserResource($this->whenLoaded('user')),
        'action_type' => $this->action_type,
        'action_name' => $this->action_name,
        'module' => $this->module,
        'entity_type' => $this->entity_type,
        'entity_id' => $this->entity_id,
        'old_data' => $this->old_data,
        'new_data' => $this->new_data,
        'ip_address' => $this->ip_address,
        'mac_address' => $this->mac_address,  
        'user_agent' => $this->user_agent,
        'status' => $this->status,
        'message' => $this->message,
        'error_code' => $this->error_code,
        'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
    ];
}
}