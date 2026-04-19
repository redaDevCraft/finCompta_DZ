<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class UserController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $search = trim((string) $request->input('search', ''));

        $users = User::query()
            ->with(['roles:id,name'])
            ->withCount(['companies as companies_count'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString()
            ->through(function (User $u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'created_at' => $u->created_at?->toIso8601String(),
                    'last_login_at' => $u->last_login_at?->toIso8601String(),
                    'is_admin' => $u->hasRole('admin'),
                    'roles' => $u->roles->pluck('name')->values(),
                    'companies_count' => $u->companies_count ?? 0,
                    'has_google' => ! empty($u->google_id),
                ];
            });

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function toggleAdmin(Request $request, User $user): RedirectResponse
    {
        $currentUser = $request->user();

        if ($user->hasRole('admin')) {
            if ($currentUser && $currentUser->id === $user->id) {
                return back()->withErrors([
                    'user' => 'Vous ne pouvez pas vous retirer le rôle admin.',
                ]);
            }

            $user->removeRole('admin');
            $message = 'Rôle admin retiré à '.$user->email.'.';
        } else {
            $user->assignRole('admin');
            $message = 'Rôle admin attribué à '.$user->email.'.';
        }

        return back()->with('success', $message);
    }
}
