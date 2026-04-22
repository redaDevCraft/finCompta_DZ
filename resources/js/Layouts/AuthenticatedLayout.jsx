import { useEffect, useMemo, useRef, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    ArrowLeftRight,
    BarChart2,
    BookOpen,
    CreditCard,
    Crown,
    ClipboardCheck,
    Clock,
    ChevronDown,
    FileBarChart,
    FileText,
    Landmark,
    Layers,
    LayoutDashboard,
    LogOut,
    Menu,
    NotebookPen,
    Receipt,
    Scale,
    Settings,
    Shield,
    Truck,
    Upload,
    User,
    Users,
    X,
} from 'lucide-react';

const baseNavGroups = [
    {
        label: null,
        items: [
            { href: '/dashboard', label: 'Tableau de bord', icon: LayoutDashboard },
        ],
    },
    {
        label: 'Ventes & achats',
        items: [
            { href: '/invoices', label: 'Factures', icon: FileText },
            { href: '/expenses', label: 'Dépenses', icon: Receipt },
            { href: '/clients', label: 'Clients', icon: User },
            { href: '/suppliers', label: 'Fournisseurs', icon: Truck },
        ],
    },
    {
        label: 'Opérations',
        items: [
            { href: '/documents', label: 'Documents', icon: Upload },
            { href: '/bank/reconcile', label: 'Rapprochement', icon: ArrowLeftRight },
        ],
    },
    {
        label: 'Comptabilité',
        items: [
            { href: '/ledger/entries/create', label: 'Saisie d’écriture', icon: NotebookPen },
            { href: '/ledger/journal', label: 'Journal', icon: BookOpen },
            { href: '/ledger/account', label: 'Grand Livre', icon: Layers },
            { href: '/ledger/lettering', label: 'Lettrage', icon: ClipboardCheck },
            { href: '/ledger/trial-balance', label: 'Balance', icon: BarChart2 },
        ],
    },
    {
        label: 'États & rapports',
        items: [
            { href: '/reports/bilan', label: 'Bilan', icon: Scale },
            { href: '/reports/predictions', label: 'Prévisions', icon: BarChart2 },
            { href: '/reports/aged-receivables', label: 'Balance âgée clients', icon: Clock },
            { href: '/reports/aged-payables', label: 'Balance âgée fournisseurs', icon: Clock },
            { href: '/reports/analytic-trial-balance', label: 'Balance analytique', icon: BarChart2 },
            { href: '/reports/vat', label: 'Rapports TVA', icon: FileBarChart },
        ],
    },
    {
        label: 'Paramétrage',
        items: [
            { href: '/clients', label: 'Tiers', icon: Users },
            { href: '/settings/accounts', label: 'Plan comptable', icon: Landmark },
            { href: '/settings/analytics', label: 'Comptabilité analytique', icon: Layers },
            { href: '/settings/auto-counterpart-rules', label: 'Règles contrepartie auto', icon: ClipboardCheck },
            { href: '/settings/journals', label: 'Journaux', icon: BookOpen },
            { href: '/settings/periods', label: 'Périodes fiscales', icon: Clock },
            { href: '/settings/entry-locks', label: 'Verrouillage écritures', icon: Shield },
            { href: '/settings/bank-accounts', label: 'Comptes bancaires', icon: Landmark },
            { href: '/settings/company', label: 'Société', icon: Settings },
            { href: '/settings/performance', label: 'Performance', icon: Activity },
        ],
    },
    {
        label: 'Abonnement',
        items: [
            { href: '/billing', label: 'Facturation SaaS', icon: CreditCard },
        ],
    },
];

function buildNavGroups(roles) {
    const groups = [...baseNavGroups];
    if ((roles ?? []).includes('admin')) {
        groups.push({
            label: 'Administration',
            items: [
                { href: '/admin', label: 'Console admin', icon: Shield },
                { href: '/admin/payments', label: 'Paiements (validation)', icon: ClipboardCheck },
            ],
        });
    }
    return groups;
}

function SidebarLink({ href, label, icon: Icon, active, onClick, linkRef }) {
    return (
        <Link
            ref={linkRef}
            href={href}
            onClick={onClick}
            className={[
                'flex items-center gap-3 rounded-r-lg px-4 py-2.5 text-sm font-medium transition-all duration-200',
                active
                    ? 'border-l-4 border-indigo-600 bg-indigo-50 text-indigo-700 shadow-sm'
                    : 'border-l-4 border-transparent text-gray-700 hover:bg-gray-50 hover:text-gray-900 hover:translate-x-0.5',
            ].join(' ')}
        >
            <Icon className="h-4 w-4 shrink-0" />
            <span className="truncate">{label}</span>
        </Link>
    );
}

const featureByHref = {
    '/invoices': 'invoicing',
    '/expenses': 'invoicing',
    '/contacts': 'contacts',
    '/clients': 'contacts',
    '/suppliers': 'contacts',
    '/documents': 'ocr',
    '/bank/reconcile': 'bank_accounts',
    '/reports/bilan': 'advanced_reports',
    '/reports/predictions': 'advanced_reports',
    '/reports/aged-receivables': 'advanced_reports',
    '/reports/aged-payables': 'advanced_reports',
    '/reports/analytic-trial-balance': 'advanced_reports',
    '/reports/vat': 'basic_reports',
};

function isActiveLink(url, href) {
    if (href === '/dashboard') return url === '/' || url.startsWith('/dashboard');
    return url === href || url.startsWith(href + '/') || url.startsWith(href + '?');
}

export default function AuthenticatedLayout({ header, children }) {
    const { url, props } = usePage();
    const [mobileOpen, setMobileOpen] = useState(false);

    const company = props.currentCompany ?? null;
    const user = props.auth?.user ?? null;
    const roles = props.auth?.roles ?? [];
    const subscription = props.subscription ?? null;
    const trialBannerStorageKey = company?.id ? `trial-banner-dismissed:${company.id}` : null;
    const [trialBannerDismissed, setTrialBannerDismissed] = useState(false);
    const allowedFeatures = props.allowed_features ?? [];
    const hasAll = Array.isArray(allowedFeatures) && allowedFeatures.includes('*');
    const canUseFeature = (feature) => {
        if (!feature) return true;
        if (hasAll) return true;
        return Array.isArray(allowedFeatures) && allowedFeatures.includes(feature);
    };
    const navGroups = useMemo(() => buildNavGroups(roles), [roles]);
    const currentPlanCode = subscription?.plan?.code ?? null;
    const shouldShowUpgradeNudge = !subscription || ['trial', 'starter', 'pro'].includes(currentPlanCode);
    const [upgradeNudgeVisible, setUpgradeNudgeVisible] = useState(false);
    const [openGroups, setOpenGroups] = useState({});
    const navScrollRef = useRef(null);
    const activeLinkRef = useRef(null);

    useEffect(() => {
        if (!trialBannerStorageKey) return;
        setTrialBannerDismissed(window.sessionStorage.getItem(trialBannerStorageKey) === '1');
    }, [trialBannerStorageKey]);

    useEffect(() => {
        if (!shouldShowUpgradeNudge) {
            setUpgradeNudgeVisible(false);
            return undefined;
        }

        const timer = window.setInterval(() => {
            setUpgradeNudgeVisible(true);
        }, 90000);

        return () => window.clearInterval(timer);
    }, [shouldShowUpgradeNudge]);

    useEffect(() => {
        setOpenGroups((previous) => {
            const next = {};
            let changed = false;

            navGroups.forEach((group, idx) => {
                const hasActiveItem = group.items.some((item) => isActiveLink(url, item.href));
                const shouldBeOpen = group.label ? (previous[idx] ?? hasActiveItem) || hasActiveItem : true;
                next[idx] = shouldBeOpen;

                if (previous[idx] !== shouldBeOpen) {
                    changed = true;
                }
            });

            if (!changed && Object.keys(previous).length === navGroups.length) {
                return previous;
            }

            return next;
        });
    }, [navGroups, url]);

    useEffect(() => {
        const scrollActiveIntoSidebarView = () => {
            const navEl = navScrollRef.current;
            const activeEl = activeLinkRef.current;
            if (!navEl || !activeEl) return;

            const margin = 12;
            const linkTopInNav = activeEl.offsetTop;
            const linkBottomInNav = linkTopInNav + activeEl.offsetHeight;
            const visibleTop = navEl.scrollTop;
            const visibleBottom = visibleTop + navEl.clientHeight;

            const isAbove = linkTopInNav < visibleTop + margin;
            const isBelow = linkBottomInNav > visibleBottom - margin;
            if (!isAbove && !isBelow) return;

            const centeredTop = linkTopInNav - (navEl.clientHeight / 2) + (activeEl.offsetHeight / 2);
            const maxTop = Math.max(0, navEl.scrollHeight - navEl.clientHeight);

            navEl.scrollTo({
                top: Math.min(Math.max(0, centeredTop), maxTop),
                behavior: 'smooth',
            });
        };

        const rafId = window.requestAnimationFrame(scrollActiveIntoSidebarView);
        const timeoutId = window.setTimeout(scrollActiveIntoSidebarView, 260);

        return () => {
            window.cancelAnimationFrame(rafId);
            window.clearTimeout(timeoutId);
        };
    }, [url, mobileOpen, openGroups]);

    const toggleGroup = (groupIndex) => {
        const group = navGroups[groupIndex];
        if (!group?.label) return;

        const hasActiveItem = group.items.some((item) => isActiveLink(url, item.href));
        if (hasActiveItem) return;

        setOpenGroups((previous) => ({
            ...previous,
            [groupIndex]: !previous[groupIndex],
        }));
    };

    const sidebar = (
        <div className="flex h-full flex-col bg-white">
            <div className="shrink-0 border-b border-gray-200 px-5 py-4">
                <div className="text-lg font-bold text-gray-900">FinCompta DZ</div>
                <div className="mt-1 text-xs text-gray-500">Comptabilité PME Algérie</div>
            </div>

            <nav ref={navScrollRef} className="flex-1 overflow-y-auto px-2 py-3">
                {navGroups.map((group, idx) => (
                    <div key={idx} className={idx > 0 ? 'mt-4' : ''}>
                        {group.label && (
                            <button
                                type="button"
                                onClick={() => toggleGroup(idx)}
                                className="flex w-full items-center justify-between px-4 pb-1 text-base font-semibold tracking-wide text-gray-500 transition-colors hover:text-gray-700"
                            >
                                <span>{group.label}</span>
                                <ChevronDown
                                    className={[
                                        'h-3.5 w-3.5 transition-transform duration-200',
                                        openGroups[idx] ? 'rotate-180' : '',
                                    ].join(' ')}
                                />
                            </button>
                        )}
                        <div
                            className={[
                                'space-y-0.5 overflow-hidden transition-all duration-300',
                                openGroups[idx] ? 'max-h-[32rem] opacity-100' : 'max-h-0 opacity-0 pointer-events-none',
                            ].join(' ')}
                        >
                            {group.items.map((item) => (
                                (() => {
                                    const feature = featureByHref[item.href] ?? null;
                                    const allowed = canUseFeature(feature);
                                    const isActive = isActiveLink(url, item.href);

                                    if (allowed) {
                                        return (
                                            <SidebarLink
                                                key={item.href}
                                                href={item.href}
                                                label={item.label}
                                                icon={item.icon}
                                                active={isActive}
                                                linkRef={isActive ? activeLinkRef : null}
                                                onClick={() => setMobileOpen(false)}
                                            />
                                        );
                                    }

                                    return (
                                        <Link
                                            key={item.href}
                                            href="/billing/checkout"
                                            onClick={() => setMobileOpen(false)}
                                            className="flex items-center justify-between gap-3 rounded-r-lg border-l-4 border-transparent px-4 py-2.5 text-sm font-medium text-slate-400 hover:bg-slate-50 hover:text-slate-600"
                                            title="Fonctionnalité verrouillée — passez au plan supérieur"
                                        >
                                            <span className="flex min-w-0 items-center gap-3">
                                                <item.icon className="h-4 w-4 shrink-0" />
                                                <span className="truncate">{item.label}</span>
                                            </span>
                                            <Crown className="h-3.5 w-3.5 shrink-0 text-amber-500" />
                                        </Link>
                                    );
                                })()
                            ))}
                        </div>
                    </div>
                ))}
            </nav>

            {company && (
                <div className="shrink-0 border-t border-gray-200 bg-gray-50 px-5 py-3 text-xs">
                    <div className="font-semibold text-gray-900 truncate">
                        {company.raison_sociale}
                    </div>
                    <div className="mt-0.5 text-gray-500">Exercice en cours</div>
                </div>
            )}
            {shouldShowUpgradeNudge && (
                <div className="shrink-0 border-t border-amber-200 bg-gradient-to-r from-amber-50 to-orange-50 px-4 py-3">
                    <div className="text-xs font-semibold text-amber-900">Passez au niveau supérieur</div>
                    <div className="mt-1 text-[11px] text-amber-800">
                        Débloquez plus d’automatisation et de fonctionnalités premium.
                    </div>
                    <Link
                        href="/billing/checkout?plan=pro&cycle=monthly"
                        className="mt-2 inline-flex items-center gap-1.5 rounded-md bg-amber-600 px-2 py-1 text-[11px] font-semibold text-white hover:bg-amber-700"
                    >
                        <Crown className="h-3 w-3" />
                        Upgrade
                    </Link>
                </div>
            )}
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
                    'fixed inset-y-0 left-0 z-50 flex w-72 transform flex-col border-r border-gray-200 bg-white transition-transform duration-200 ease-in-out lg:translate-x-0',
                    mobileOpen ? 'translate-x-0' : '-translate-x-full',
                ].join(' ')}
            >
                <div className="flex shrink-0 items-center justify-between border-b border-gray-200 px-5 py-4 lg:hidden">
                    <span className="text-base font-semibold text-gray-900">Navigation</span>
                    <button
                        type="button"
                        onClick={() => setMobileOpen(false)}
                        className="rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>

                <div className="min-h-0 flex-1">{sidebar}</div>
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

                {subscription?.status === 'trial' &&
                    subscription?.days_remaining <= 7 &&
                    !trialBannerDismissed && (
                        <div className="flex items-center justify-between gap-3 bg-gradient-to-r from-amber-100 via-orange-100 to-amber-50 px-4 py-2 text-sm text-amber-900 sm:px-6 lg:px-8">
                            <span>
                                ⏳ Votre période d'essai se termine dans{' '}
                                <strong>{subscription.days_remaining}</strong> jour(s) — passez au
                                plan Pro pour continuer à profiter de toutes les fonctionnalités.
                            </span>
                            <div className="flex items-center gap-2">
                                <Link
                                    href="/billing/checkout"
                                    className="rounded-md bg-amber-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-amber-700"
                                >
                                    Mettre à niveau maintenant
                                </Link>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setTrialBannerDismissed(true);
                                        if (trialBannerStorageKey) {
                                            window.sessionStorage.setItem(trialBannerStorageKey, '1');
                                        }
                                    }}
                                    className="text-xs font-medium text-amber-900 underline hover:text-amber-950"
                                >
                                    Ignorer
                                </button>
                            </div>
                        </div>
                    )}
                {!subscription && (
                    <div className="flex items-center justify-between gap-3 bg-gradient-to-r from-amber-100 via-orange-100 to-amber-50 px-4 py-2 text-sm text-amber-900 sm:px-6 lg:px-8">
                        <span>
                            Votre compte n’a pas encore de plan actif — activez votre premier plan pour debloquer toute la plateforme.
                        </span>
                        <Link
                            href="/billing"
                            className="rounded-md bg-amber-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-amber-700"
                        >
                            Choisir un plan
                        </Link>
                    </div>
                )}
                {subscription?.status === 'past_due' && (
                    <div className="bg-rose-50 px-4 py-2 text-sm text-rose-800 sm:px-6 lg:px-8">
                        Votre abonnement est en retard de paiement.{' '}
                        <Link href="/billing" className="font-semibold underline hover:text-rose-900">Régulariser maintenant</Link>
                    </div>
                )}
                {upgradeNudgeVisible && shouldShowUpgradeNudge && (
                    <div className="mx-4 mt-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 shadow-sm sm:mx-6 lg:mx-8">
                        <div className="flex items-start justify-between gap-4">
                            <p>
                                Astuce premium: activez un plan superieur pour debloquer toutes les options avancees
                                et gagner du temps sur vos operations.
                            </p>
                            <button
                                type="button"
                                onClick={() => setUpgradeNudgeVisible(false)}
                                className="text-xs font-semibold underline"
                            >
                                Fermer
                            </button>
                        </div>
                        <div className="mt-2">
                            <Link href="/billing" className="text-xs font-semibold underline">
                                Voir les plans
                            </Link>
                        </div>
                    </div>
                )}

                <main className="px-4 py-6 text-[0.92rem] sm:px-6 lg:px-8">{children}</main>
            </div>
        </div>
    );
}
