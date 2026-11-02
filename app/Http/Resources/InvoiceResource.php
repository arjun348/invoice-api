<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'number' => $this->number,
            'amount' => $this->amount,
            'status' => $this->status,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'notes' => $this->notes,
            'client' => ClientResource::make($this->whenLoaded('client')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}