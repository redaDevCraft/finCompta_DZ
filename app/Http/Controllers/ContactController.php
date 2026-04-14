<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(Request $request): Response
    {
        $companyId = app('currentCompany')->id;
        $type = $request->string('type')->toString();

        $query = Contact::query()
            ->where('company_id', $companyId)
            ->orderBy('display_name');

        if (in_array($type, ['client', 'supplier', 'both'], true)) {
            $query->where('type', $type);
        }

        $contacts = $query
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Contacts/Index', [
            'contacts' => $contacts,
            'filters' => [
                'type' => $type ?: null,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = app('currentCompany')->id;

        $validated = $request->validate($this->rules());

        Contact::query()->create([
            ...$validated,
            'company_id' => $companyId,
        ]);

        return back()->with('success', 'Contact créé avec succès.');
    }

    public function update(Request $request, Contact $contact): RedirectResponse
    {
        $this->assertOwnership($contact);

        $validated = $request->validate($this->rules());

        $contact->update($validated);

        return back()->with('success', 'Contact mis à jour avec succès.');
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $this->assertOwnership($contact);

        $hasActiveInvoices = $contact->invoices()
            ->whereNotIn('status', ['void'])
            ->exists();

        $hasActiveExpenses = $contact->expenses()
            ->whereNotIn('status', ['cancelled'])
            ->exists();

        if ($hasActiveInvoices || $hasActiveExpenses) {
            return back()->withErrors([
                'delete' => 'Impossible de supprimer ce contact car il est référencé par des factures ou des dépenses actives.',
            ]);
        }

        $contact->delete();

        return back()->with('success', 'Contact supprimé avec succès.');
    }

    protected function assertOwnership(Contact $contact): void
    {
        abort_unless(
            $contact->company_id === app('currentCompany')->id,
            404
        );
    }

    protected function rules(): array
    {
        return [
            'type' => ['required', 'in:client,supplier,both'],
            'entity_type' => ['required', 'in:individual,enterprise'],
            'display_name' => ['required', 'string', 'max:255'],
            'raison_sociale' => ['nullable', 'string', 'max:255'],
            'nif' => ['nullable', 'string', 'max:30'],
            'nis' => ['nullable', 'string', 'max:30'],
            'rc' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address_line1' => ['nullable', 'string', 'max:500'],
            'address_wilaya' => ['nullable', 'string', 'max:100'],
        ];
    }
}
