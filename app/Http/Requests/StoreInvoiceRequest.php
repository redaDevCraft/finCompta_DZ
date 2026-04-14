<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
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
            'due_date' => 'nullable|date|after_or_equal:issue_date',
            'document_type' => 'required|in:invoice,credit_note,quote,delivery_note',
            'payment_mode' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:2000',

            'lines' => 'required|array|min:1',
            'lines.*.designation' => 'required|string|min:1|max:1000',
            'lines.*.quantity' => 'required|numeric|min:0.0001',
            'lines.*.unit' => 'nullable|string|max:30',
            'lines.*.unit_price_ht' => 'required|numeric|min:0',
            'lines.*.discount_pct' => 'nullable|numeric|min:0|max:100',
            'lines.*.tax_rate_id' => 'nullable|uuid|exists:tax_rates,id',
            'lines.*.vat_rate_pct' => 'required|numeric|in:0,9,19',
            'lines.*.account_id' => 'nullable|uuid|exists:accounts,id',
        ];
    }

    public function messages(): array
    {
        return [
            'contact_id.uuid' => 'Le contact sélectionné est invalide.',
            'contact_id.exists' => 'Le contact sélectionné est introuvable.',
            'issue_date.required' => "La date d'émission est obligatoire.",
            'issue_date.date' => "La date d'émission est invalide.",
            'due_date.date' => "La date d'échéance est invalide.",
            'due_date.after_or_equal' => "La date d'échéance doit être postérieure ou égale à la date d'émission.",
            'document_type.required' => 'Le type de document est obligatoire.',
            'document_type.in' => 'Le type de document est invalide.',
            'payment_mode.max' => 'Le mode de paiement ne doit pas dépasser 50 caractères.',
            'notes.max' => 'Les notes ne doivent pas dépasser 2000 caractères.',

            'lines.required' => 'La facture doit contenir au moins une ligne.',
            'lines.array' => 'Les lignes de facture sont invalides.',
            'lines.min' => 'La facture doit contenir au moins une ligne.',
            'lines.*.designation.required' => 'Chaque ligne doit avoir une désignation.',
            'lines.*.designation.min' => 'La désignation de ligne est obligatoire.',
            'lines.*.designation.max' => 'La désignation ne doit pas dépasser 1000 caractères.',
            'lines.*.quantity.required' => 'La quantité est obligatoire.',
            'lines.*.quantity.numeric' => 'La quantité doit être numérique.',
            'lines.*.quantity.min' => 'La quantité doit être supérieure à 0.',
            'lines.*.unit.max' => "L'unité ne doit pas dépasser 30 caractères.",
            'lines.*.unit_price_ht.required' => 'Le prix unitaire HT est obligatoire.',
            'lines.*.unit_price_ht.numeric' => 'Le prix unitaire HT doit être numérique.',
            'lines.*.unit_price_ht.min' => 'Le prix unitaire HT ne peut pas être négatif.',
            'lines.*.discount_pct.numeric' => 'La remise doit être numérique.',
            'lines.*.discount_pct.min' => 'La remise ne peut pas être négative.',
            'lines.*.discount_pct.max' => 'La remise ne peut pas dépasser 100%.',
            'lines.*.tax_rate_id.uuid' => 'Le taux de taxe sélectionné est invalide.',
            'lines.*.tax_rate_id.exists' => 'Le taux de taxe sélectionné est introuvable.',
            'lines.*.vat_rate_pct.required' => 'Le taux de TVA est obligatoire.',
            'lines.*.vat_rate_pct.numeric' => 'Le taux de TVA doit être numérique.',
            'lines.*.vat_rate_pct.in' => 'Le taux de TVA doit être 0, 9 ou 19.',
            'lines.*.account_id.uuid' => 'Le compte comptable sélectionné est invalide.',
            'lines.*.account_id.exists' => 'Le compte comptable sélectionné est introuvable.',
        ];
    }
}