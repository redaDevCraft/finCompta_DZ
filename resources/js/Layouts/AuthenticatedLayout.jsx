import { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeftRight,
    BarChart2,
    BookOpen,
    FileText,
    LayoutDashboard,
    LogOut,
    Menu,
    Receipt,
    Settings,
    Upload,
    X,
} from 'lucide-react';

const navigation = [
    { href: '/dashboard', label: 'Tableau de bord', icon: LayoutDashboard },
    { href: '/invoices', label: 'Factures', icon: FileText },
    { href: '/expenses', label: 'Dépenses', icon: Receipt },
    { href: '/documents/upload', label: 'Documents', icon: Upload },
    { href: '/bank/reconcile', label: 'Rapprochement', icon: ArrowLeftRight },
    { href: '/ledger/journal', label: 'Journal', icon: BookOpen },
    { href: '/reports/vat', label: 'Rapports TVA', icon: BarChart2 },
    { href: '/settings/company', label: 'Paramètres', icon: Settings },
];

function SidebarLink({ href, label, icon: Icon, active, onClick }) {
    return (
        <Link
            href={href}
            onClick={onClick}
            className={[
                'flex items-center gap-3 rounded-r-lg px-4 py-3 text-sm font-medium transition',
                active
                    ? 'border-l-4 border-indigo-600 bg-indigo-50 text-indigo-700'
                    : 'border-l-4 border-transparent text-gray-700 hover:bg-gray-50 hover:text-gray-900',
            ].join(' ')}
        >
            <Icon className="h-5 w-5 shrink-0" />
            <span>{label}</span>
        </Link>
    );
}

export default function AuthenticatedLayout({ header, children }) {
    const { url, props } = usePage();
    const [mobileOpen, setMobileOpen] = useState(false);

    const company = props.currentCompany ?? null;
    const user = props.auth?.user ?? null;

    const sidebar = (
        <div className="flex h-full flex-col bg-white">
            <div className="border-b border-gray-200 px-5 py-4">
                <div className="text-lg font-bold text-gray-900">FinCompta DZ</div>
                <div className="mt-1 text-xs text-gray-500">Comptabilité PME Algérie</div>
            </div>

            <nav className="flex-1 space-y-1 px-2 py-4">
                {navigation.map((item) => (
                    <SidebarLink
                        key={item.href}
                        href={item.href}
                        label={item.label}
                        icon={item.icon}
                        active={url.startsWith(item.href)}
                        onClick={() => setMobileOpen(false)}
                    />
                ))}
            </nav>
        </div>
    );

    return (
        <div className="min-h-screen bg-gray-50">
            {mobileOpen && (
                <div
                    className="fixed inset-0 z-40 bg-black/40 lg:hidden"
                    onClick={() => setMobileOpen(false)}
                />
            )}

            <aside
                className={[
                    'fixed inset-y-0 left-0 z-50 w-72 transform border-r border-gray-200 bg-white transition-transform duration-200 ease-in-out lg:translate-x-0',
                    mobileOpen ? 'translate-x-0' : '-translate-x-full',
                ].join(' ')}
            >
                <div className="flex items-center justify-between border-b border-gray-200 px-5 py-4 lg:hidden">
                    <span className="text-base font-semibold text-gray-900">Navigation</span>
                    <button
                        type="button"
                        onClick={() => setMobileOpen(false)}
                        className="rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>

                {sidebar}
            </aside>

            <div className="lg:pl-72">
                <header className="sticky top-0 z-30 border-b border-gray-200 bg-white/95 backdrop-blur">
                    <div className="flex items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                        <div className="flex items-center gap-3">
                            <button
                                type="button"
                                onClick={() => setMobileOpen(true)}
                                className="rounded-md p-2 text-gray-600 hover:bg-gray-100 lg:hidden"
                            >
                                <Menu className="h-5 w-5" />
                            </button>

                            <div>
                                <div className="text-lg font-semibold text-gray-900">
                                    {header || 'Espace de gestion'}
                                </div>
                                <div className="text-sm text-gray-500">
                                    {company?.raison_sociale ?? 'Aucune société sélectionnée'}
                                </div>
                            </div>
                        </div>

                        <div className="flex items-center gap-4">
                            <div className="hidden text-right sm:block">
                                <div className="text-sm font-medium text-gray-900">
                                    {user?.name ?? 'Utilisateur'}
                                </div>
                                <div className="text-xs text-gray-500">Session active</div>
                            </div>

                            <Link
                                href={route('logout')}
                                method="post"
                                as="button"
                                className="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                <LogOut className="h-4 w-4" />
                                <span className="hidden sm:inline">Déconnexion</span>
                            </Link>
                        </div>
                    </div>
                </header>

                <main className="px-4 py-6 sm:px-6 lg:px-8">{children}</main>
            </div>
        </div>
    );
}