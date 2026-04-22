<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RefundRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class RefundRequestAdminController extends Controller
{
    public function index(): InertiaResponse
    {
        $requests = RefundRequest::query()
            ->with(['company:id,raison_sociale', 'payment:id,reference,amount_dzd,currency,status'])
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        return Inertia::render('Admin/RefundRequests/Index', [
            'refundRequests' => $requests,
        ]);
    }

    public function update(Request $request, RefundRequest $refundRequest): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:reviewing,approved,rejected,refunded'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $refundRequest->update([
            'status' => $validated['status'],
            'admin_note' => $validated['admin_note'] ?? null,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Demande de remboursement mise a jour.');
    }
}
