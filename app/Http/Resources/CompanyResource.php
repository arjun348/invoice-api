<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'status_reason' => $this->status_reason,
            'approved_at' => $this->approved_at?->toISOString(),
            'approved_by' => $this->approved_by,
            'users_count' => $this->whenCounted('users'),
            'clients_count' => $this->whenCounted('clients'),
            'invoices_count' => $this->whenCounted('invoices'),
            'users' => UserResource::collection($this->whenLoaded('users')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

