import { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    CreditCard,
    LayoutDashboard,
    LogOut,
    Menu,
    Package,
    Shield,
    Users,
    WalletCards,
    X,
} from 'lucide-react';

const navItems = [
    { href: '/admin', label: 'Tableau de bord', icon: LayoutDashboard },
    { href: '/admin/payments', label: 'Paiements à confirmer', icon: WalletCards },
    { href: '/admin/subscriptions', label: 'Abonnements', icon: CreditCard },
    { href: '/admin/companies', label: 'Sociétés', icon: Building2 },
    { href: '/admin/users', label: 'Utilisateurs', icon: Users },
    { href: '/admin/plans', label: 'Plans tarifaires', icon: Package },
];

function isActive(url, href) {
    if (href === '/admin') return url === '/admin' || url.startsWith('/admin?');
    return url.startsWith(href);
}

export default function AdminLayout({ header, children }) {
    const { url, props } = usePage();
    const [mobileOpen, setMobileOpen] = useState(false);
    const user = props.auth?.user ?? null;
    const flash = props.flash ?? {};

    const sidebar = (
        <div className="flex h-full flex-col bg-slate-900 text-slate-100">
            <div className="shrink-0 border-b border-slate-700 px-5 py-4">
                <div className="flex items-center gap-2 text-lg font-bold">
                    <Shield className="h-5 w-5 text-amber-400" />
                    Administration
                </div>
                <div className="mt-1 text-xs text-slate-400">FinCompta DZ — rôles Spatie</div>
            </div>

            <nav className="flex-1 space-y-0.5 overflow-y-auto px-2 py-3">
                {navItems.map((item) => {
                    const Icon = item.icon;
                    const active = isActive(url, item.href);
                    return (
                        <Link
                            key={item.href}
                            href={item.href}
                            onClick={() => setMobileOpen(false)}
                            className={[
                                'flex items-center gap-3 rounded-lg px-4 py-2.5 text-sm font-medium transition',
                                active
                                    ? 'bg-slate-800 text-white ring-1 ring-amber-500/40'
                                    : 'text-slate-300 hover:bg-slate-800 hover:text-white',
                            ].join(' ')}
                        >
                            <Icon className="h-4 w-4 shrink-0" />
                            {item.label}
                        </Link>
                    );
                })}
            </nav>

            <div className="shrink-0 border-t border-slate-700 px-5 py-3">
                <Link
                    href="/dashboard"
                    className="text-sm font-medium text-amber-400 hover:text-amber-300"
                    onClick={() => setMobileOpen(false)}
                >
                    ← Retour à l’application
                </Link>
            </div>
        </div>
    );

    return (
        <div className="min-h-screen bg-slate-100">
            {mobileOpen && (
                <div
                    className="fixed inset-0 z-40 bg-black/50 lg:hidden"
                    onClick={() => setMobileOpen(false)}
                />
            )}

            <aside
                className={[
                    'fixed inset-y-0 left-0 z-50 flex w-72 transform flex-col border-r border-slate-800 transition-transform duration-200 ease-in-out lg:translate-x-0',
                    mobileOpen ? 'translate-x-0' : '-translate-x-full',
                ].join(' ')}
            >
                <div className="flex shrink-0 items-center justify-between border-b border-slate-700 px-5 py-4 lg:hidden">
                    <span className="text-base font-semibold text-white">Menu admin</span>
                    <button
                        type="button"
                        onClick={() => setMobileOpen(false)}
                        className="rounded-md p-2 text-slate-400 hover:bg-slate-800 hover:text-white"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>
                <div className="min-h-0 flex-1">{sidebar}</div>
            </aside>

            <div className="lg:pl-72">
                <header className="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
                    <div className="flex items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                        <div className="flex items-center gap-3">
                            <button
                                type="button"
                                onClick={() => setMobileOpen(true)}
                                className="rounded-md p-2 text-slate-600 hover:bg-slate-100 lg:hidden"
                            >
                                <Menu className="h-5 w-5" />
                            </button>
                            <div>
                                <div className="text-lg font-semibold text-slate-900">{header || 'Admin'}</div>
                                <div className="text-sm text-slate-500">Espace réservé aux administrateurs</div>
                            </div>
                        </div>
                        <div className="flex items-center gap-4">
                            <div className="hidden text-right sm:block">
                                <div className="text-sm font-medium text-slate-900">{user?.name ?? '—'}</div>
                                <div className="text-xs text-slate-500">{user?.email}</div>
                            </div>
                            <Link
                                href={route('logout')}
                                method="post"
                                as="button"
                                className="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                <LogOut className="h-4 w-4" />
                                <span className="hidden sm:inline">Déconnexion</span>
                            </Link>
                        </div>
                    </div>
                </header>

                {flash.success && (
                    <div className="bg-emerald-50 px-4 py-2 text-sm text-emerald-800 sm:px-6 lg:px-8">{flash.success}</div>
                )}
                {flash.warning && (
                    <div className="bg-amber-50 px-4 py-2 text-sm text-amber-800 sm:px-6 lg:px-8">{flash.warning}</div>
                )}
                {flash.error && (
                    <div className="bg-rose-50 px-4 py-2 text-sm text-rose-800 sm:px-6 lg:px-8">{flash.error}</div>
                )}

                <main className="px-4 py-6 sm:px-6 lg:px-8">{children}</main>
            </div>
        </div>
    );
}
