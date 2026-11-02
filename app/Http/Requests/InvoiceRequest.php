<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $invoiceId = $this->route('invoice');

        return [
            'client_id' => ['required', 'integer'],
            'number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('invoices', 'number')
                    ->where('company_id', $this->user()->company_id)
                    ->ignore($invoiceId),
            ],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['draft', 'sent', 'paid'])],
            'due_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}