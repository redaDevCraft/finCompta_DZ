<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class SubscriptionController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $status = (string) $request->input('status', '');
        $search = trim((string) $request->input('search', ''));

        $query = Subscription::query()
            ->with(['company:id,raison_sociale,nif', 'plan:id,name,code']);

        if (in_array($status, ['trialing', 'active', 'past_due', 'canceled', 'paused'], true)) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->whereHas('company', function ($q) use ($search) {
                $q->where('raison_sociale', 'ilike', "%{$search}%")
                    ->orWhere('nif', 'ilike', "%{$search}%");
            });
        }

        $subscriptions = $query
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $counts = Subscription::query()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return Inertia::render('Admin/Subscriptions/Index', [
            'subscriptions' => $subscriptions,
            'filters' => [
                'status' => $status ?: null,
                'search' => $search,
            ],
            'counts' => [
                'trialing' => (int) ($counts['trialing'] ?? 0),
                'active' => (int) ($counts['active'] ?? 0),
                'past_due' => (int) ($counts['past_due'] ?? 0),
                'canceled' => (int) ($counts['canceled'] ?? 0),
            ],
        ]);
    }

    public function cancel(
        Request $request,
        Subscription $subscription,
        SubscriptionService $service,
    ): RedirectResponse {
        $immediate = $request->boolean('immediate');

        $service->cancel($subscription, $immediate);

        return back()->with(
            'success',
            $immediate
                ? 'Abonnement résilié immédiatement.'
                : 'Résiliation planifiée à la fin de la période.',
        );
    }

    public function reactivate(Subscription $subscription): RedirectResponse
    {
        $subscription->update([
            'status' => $subscription->current_period_ends_at
                && $subscription->current_period_ends_at->isFuture()
                    ? 'active'
                    : 'past_due',
            'canceled_at' => null,
            'cancel_at' => null,
        ]);

        return back()->with('success', 'Abonnement réactivé.');
    }

    public function extend(Request $request, Subscription $subscription): RedirectResponse
    {
        $validated = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $base = $subscription->current_period_ends_at && $subscription->current_period_ends_at->isFuture()
            ? $subscription->current_period_ends_at
            : Carbon::now();

        $subscription->update([
            'current_period_ends_at' => $base->copy()->addDays($validated['days']),
            'status' => 'active',
        ]);

        return back()->with('success', 'Abonnement prolongé de '.$validated['days'].' jours.');
    }
}
