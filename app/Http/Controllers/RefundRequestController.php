<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\RefundRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RefundRequestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'payment_id' => ['required', 'uuid', 'exists:payments,id'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $company = app('currentCompany');
        $payment = Payment::query()
            ->where('id', $validated['payment_id'])
            ->where('company_id', $company->id)
            ->firstOrFail();

        RefundRequest::query()->create([
            'company_id' => $company->id,
            'payment_id' => $payment->id,
            'requested_by' => $request->user()?->id,
            'status' => 'submitted',
            'reason' => $validated['reason'],
        ]);

        return back()->with('success', 'Votre demande de remboursement a ete envoyee.');
    }
}
