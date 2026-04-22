<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'contact_id' => 'nullable|uuid|exists:contacts,id',
            'issue_date' => 'required|date',
            'expiry_date' => 'nullable|date|after_or_equal:issue_date',
            'currency_id' => 'nullable|uuid|exists:currencies,id',
            'reference' => 'nullable|string|max:120',
            'notes' => 'nullable|string|max:2000',

            'lines' => 'required|array|min:1',
            'lines.*.description' => 'required|string|min:1|max:1000',
            'lines.*.quantity' => 'required|numeric|min:0.0001',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.vat_rate' => 'required|numeric|in:0,9,19',
        ];
    }
}
