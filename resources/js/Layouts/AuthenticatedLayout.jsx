import { useEffect, useMemo, useRef, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import StickyBackButton from '@/Components/StickyBackButton';
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
    Search,
    Receipt,
    Scale,
    Settings,
    Shield,
    CircleHelp,
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
            { href: '/dashboard', label: 'Dashboard', icon: LayoutDashboard },
        ],
    },
    {
        label: 'COMMERCIAL',
        items: [
            { href: '/invoices', label: 'Factures (ventes)', icon: FileText },
            { href: '/expenses', label: 'Dépenses (achats)', icon: Receipt },
            { href: '/clients', label: 'Clients', icon: User },
            { href: '/suppliers', label: 'Fournisseurs', icon: Truck },
            { href: '/documents', label: 'Documents (OCR)', icon: Upload },
        ],
    },
    {
        label: 'BANQUE & TRÉSORERIE',
        items: [
            { href: '/settings/bank-accounts', label: 'Comptes bancaires', icon: Landmark },
            { href: '/bank/reconcile', label: 'Rapprochement', icon: ArrowLeftRight },
            { href: '/documents', label: 'Relevés importés', icon: Upload },
        ],
    },
    {
        label: 'COMPTABILITÉ GÉNÉRALE',
        items: [
            { href: '/ledger/entries/create', label: 'Saisie d’écriture [+]', icon: NotebookPen, cta: true },
            { href: '/ledger/journal', label: 'Journal des opérations', icon: BookOpen },
            { href: '/ledger/account', label: 'Grand Livre', icon: Layers },
            { href: '/ledger/trial-balance', label: 'Balance des comptes', icon: BarChart2 },
            { href: '/ledger/lettering', label: 'Lettrage', icon: ClipboardCheck },
        ],
    },
    {
        label: 'ANALYTIQUE & RAPPORTS',
        items: [
            { href: '/reports/bilan', label: 'Bilan / CPC / TFT', icon: Scale },
            { href: '/reports/vat', label: 'Rapport TVA (G50/G11)', icon: FileBarChart },
            { href: '/reports/analytic-trial-balance', label: 'Balance analytique', icon: BarChart2 },
            { href: '/reports/aged-receivables', label: 'Balance âgée — Clients', icon: Clock },
            { href: '/reports/aged-payables', label: 'Balance âgée — Fournisseurs', icon: Clock },
            { href: '/reports/predictions', label: 'Prévisions de gestion', icon: BarChart2 },
            { href: '/reports/bilan', label: 'État récap. annuel (ERA)', icon: FileText },
        ],
    },
    {
        label: 'PARAMÉTRAGE',
        items: [
            { href: '/settings/accounts', label: 'Référentiels', icon: Settings, isSubheading: true },
            { href: '/settings/accounts', label: 'Plan comptable', icon: Landmark, nested: true },
            { href: '/clients', label: 'Tiers (clients/fourn.)', icon: Users, nested: true },
            { href: '/settings/journals', label: 'Journaux', icon: BookOpen, nested: true },
            { href: '/settings/auto-counterpart-rules', label: 'Automatisation', icon: Activity, isSubheading: true },
            { href: '/settings/auto-counterpart-rules', label: 'Règles contrepartie auto', icon: ClipboardCheck, nested: true },
            { href: '/settings/analytics', label: 'Comptabilité analytique', icon: Layers, nested: true },
            { href: '/settings/periods', label: 'Exercice & sécurité', icon: Shield, isSubheading: true },
            { href: '/settings/periods', label: 'Périodes fiscales', icon: Clock, nested: true },
            { href: '/settings/entry-locks', label: 'Verrouillage écritures', icon: Shield, nested: true },
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

function SidebarLink({ href, label, icon: Icon, active, onClick, linkRef, cta = false, nested = false, isSubheading = false }) {
    if (isSubheading) {
        return (
            <div className="mt-2 px-4 pb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                {label}
            </div>
        );
    }

    return (
        <Link
            ref={linkRef}
            href={href}
            onClick={onClick}
            className={[
                'flex items-center gap-3 rounded-r-lg px-4 py-2.5 text-[14px] font-medium transition-all duration-200',
                nested ? 'pl-9 text-[13px]' : '',
                cta ? 'border border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100' : '',
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
    '/quotes': 'invoicing',
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
    const companySwitcher = props.company_switcher ?? [];
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
    const [upgradeBannerDismissed, setUpgradeBannerDismissed] = useState(false);
    const [openGroups, setOpenGroups] = useState({});
    const [quickQuery, setQuickQuery] = useState('');
    const [quickResults, setQuickResults] = useState([]);
    const [quickLoading, setQuickLoading] = useState(false);
    const [selectedFiscalYear, setSelectedFiscalYear] = useState(String(new Date().getFullYear()));
    const [accountMenuOpen, setAccountMenuOpen] = useState(false);
    const [contextMenuOpen, setContextMenuOpen] = useState(false);
    const [companyMenuOpen, setCompanyMenuOpen] = useState(false);
    const [fiscalMenuOpen, setFiscalMenuOpen] = useState(false);
    const navScrollRef = useRef(null);
    const activeLinkRef = useRef(null);
    const lastAutoScrolledUrlRef = useRef(null);
    const quickSearchAbortRef = useRef(null);

    useEffect(() => {
        if (!trialBannerStorageKey) return;
        setTrialBannerDismissed(window.sessionStorage.getItem(trialBannerStorageKey) === '1');
    }, [trialBannerStorageKey]);

    useEffect(() => {
        if (!shouldShowUpgradeNudge) {
            setUpgradeNudgeVisible(false);
            setUpgradeBannerDismissed(false);
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
        if (lastAutoScrolledUrlRef.current === url) return;
        lastAutoScrolledUrlRef.current = url;

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
    }, [url]);

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

    const fiscalYearOptions = useMemo(() => {
        const year = new Date().getFullYear();
        return [String(year - 1), String(year), String(year + 1)];
    }, []);

    const handleCompanySwitch = (nextCompanyId) => {
        if (!nextCompanyId || String(company?.id ?? '') === nextCompanyId) return;

        router.post(
            route('company.switch'),
            { company_id: nextCompanyId },
            { preserveScroll: true }
        );
    };

    const sidebar = (
        <div className="flex h-full flex-col bg-white">
            <div className="shrink-0 border-b border-gray-200 px-5 py-4">
                <div className="text-base font-bold text-gray-900">FinCompta DZ</div>
                <div className="mt-1 text-[11px] text-gray-500">Comptabilité PME Algérie</div>
            </div>

            <div className="relative shrink-0 border-b border-gray-200 bg-slate-50 px-3 py-3">
                {contextMenuOpen && (
                    <div className="absolute left-3 right-3 top-[calc(100%+8px)] z-20 rounded-md border border-slate-200 bg-white p-3.5 shadow-lg">
                        <div className="space-y-3">
                            <div className="relative">
                                <div className="mb-1 flex items-center justify-between">
                                    <label className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                        Société
                                    </label>
                                    <Link
                                        href="/company/select"
                                        className="text-[12px] font-medium text-slate-600 hover:text-slate-900"
                                        onClick={() => setContextMenuOpen(false)}
                                    >
                                        Changer
                                    </Link>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setCompanyMenuOpen((open) => !open);
                                        setFiscalMenuOpen(false);
                                    }}
                                    className="flex w-full items-center justify-between rounded-md border border-slate-200 bg-white px-2.5 py-2 text-[13px] font-semibold uppercase tracking-wide text-slate-700 hover:bg-slate-100"
                                >
                                    <span className="truncate">{company?.raison_sociale ?? 'Aucune société'}</span>
                                    <ChevronDown className={['h-3.5 w-3.5 shrink-0 transition-transform', companyMenuOpen ? 'rotate-180' : ''].join(' ')} />
                                </button>
                                {companyMenuOpen && (
                                    <div className="absolute left-0 right-0 z-20 mt-1 max-h-48 overflow-y-auto rounded-md border border-slate-200 bg-white py-1 shadow-lg">
                                        {companySwitcher.length > 0 ? (
                                            companySwitcher.map((item) => (
                                                <button
                                                    key={item.id}
                                                    type="button"
                                                    onClick={() => {
                                                        handleCompanySwitch(String(item.id));
                                                        setCompanyMenuOpen(false);
                                                        setContextMenuOpen(false);
                                                    }}
                                                    className={[
                                                        'block w-full px-2.5 py-2 text-left text-[13px] hover:bg-slate-100',
                                                        String(company?.id ?? '') === String(item.id)
                                                            ? 'bg-indigo-50 font-semibold text-indigo-700'
                                                            : 'text-slate-700',
                                                    ].join(' ')}
                                                >
                                                    {item.raison_sociale}
                                                </button>
                                            ))
                                        ) : (
                                            <div className="px-2.5 py-2 text-[13px] text-slate-500">Aucune société</div>
                                        )}
                                    </div>
                                )}
                            </div>

                            <div className="relative">
                                <div className="mb-1 flex items-center justify-between">
                                    <label className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                        Exercice
                                    </label>
                                    <Link
                                        href="/settings/periods"
                                        className="text-[12px] font-medium text-slate-600 hover:text-slate-900"
                                        onClick={() => setContextMenuOpen(false)}
                                    >
                                        Gérer
                                    </Link>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setFiscalMenuOpen((open) => !open);
                                        setCompanyMenuOpen(false);
                                    }}
                                    className="flex w-full items-center justify-between rounded-md border border-slate-200 bg-white px-2.5 py-2 text-[13px] font-semibold text-slate-700 hover:bg-slate-100"
                                >
                                    <span>{selectedFiscalYear}</span>
                                    <ChevronDown className={['h-3.5 w-3.5 shrink-0 transition-transform', fiscalMenuOpen ? 'rotate-180' : ''].join(' ')} />
                                </button>
                                {fiscalMenuOpen && (
                                    <div className="absolute left-0 right-0 z-20 mt-1 rounded-md border border-slate-200 bg-white py-1 shadow-lg">
                                        {fiscalYearOptions.map((year) => (
                                            <button
                                                key={year}
                                                type="button"
                                                onClick={() => {
                                                    setSelectedFiscalYear(year);
                                                    setFiscalMenuOpen(false);
                                                    setContextMenuOpen(false);
                                                }}
                                                className={[
                                                    'block w-full px-2.5 py-2 text-left text-[13px] hover:bg-slate-100',
                                                    selectedFiscalYear === year
                                                        ? 'bg-indigo-50 font-semibold text-indigo-700'
                                                        : 'text-slate-700',
                                                ].join(' ')}
                                            >
                                                {year}
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                )}
                <button
                    type="button"
                    onClick={() => {
                        setContextMenuOpen((open) => !open);
                        setCompanyMenuOpen(false);
                        setFiscalMenuOpen(false);
                    }}
                    className="flex w-full items-center justify-between rounded-md border border-slate-200 bg-white px-3 py-2.5 text-[13px] font-medium text-slate-700 hover:bg-slate-100"
                >
                    <span className="truncate">
                        {company?.raison_sociale ?? 'Société'} · {selectedFiscalYear}
                    </span>
                    <ChevronDown className={['h-3.5 w-3.5 transition-transform', contextMenuOpen ? 'rotate-180' : ''].join(' ')} />
                </button>
            </div>

            <nav ref={navScrollRef} className="flex-1 overflow-y-auto px-2 py-4">
                {navGroups.map((group, idx) => (
                    <div key={idx} className={idx > 0 ? 'mt-5' : ''}>
                        {group.label && (
                            <button
                                type="button"
                                onClick={() => toggleGroup(idx)}
                                className="flex w-full items-center justify-between px-4 pb-1.5 text-[12px] font-semibold uppercase tracking-wide text-gray-500 transition-colors hover:text-gray-700"
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
                                'space-y-1 overflow-hidden transition-all duration-300',
                                openGroups[idx] ? 'max-h-[40rem] opacity-100' : 'max-h-0 opacity-0 pointer-events-none',
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
                                            className="flex items-center justify-between gap-3 rounded-r-lg border-l-4 border-transparent px-4 py-2.5 text-[14px] font-medium text-slate-400 hover:bg-slate-50 hover:text-slate-600"
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

            <div className="relative shrink-0 border-t border-gray-200 bg-gray-50 px-3 py-3">
                {accountMenuOpen && (
                    <div className="absolute bottom-full left-3 right-3 z-10 mb-2 rounded-md border border-slate-200 bg-white p-3.5 shadow-lg">
                        <div className="text-[13px] font-semibold text-gray-900">{user?.name ?? 'Utilisateur'}</div>
                        {company && (
                            <div className="mt-0.5 truncate text-[12px] text-slate-500">{company.raison_sociale}</div>
                        )}
                        <div className="mt-2 flex items-center gap-2">
                            <span className="rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold text-indigo-700">
                                {subscription?.plan?.name ?? 'Plan non actif'}
                            </span>
                            <Link
                                href="/billing"
                                className="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-medium text-slate-700 hover:bg-slate-100"
                                onClick={() => setAccountMenuOpen(false)}
                            >
                                <CreditCard className="h-3.5 w-3.5" />
                                Billing
                            </Link>
                        </div>
                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            className="mt-2 inline-flex w-full items-center justify-center gap-2 rounded-md border border-gray-200 bg-white px-3 py-2 text-[12px] font-medium text-gray-700 hover:bg-gray-100"
                        >
                            <LogOut className="h-3.5 w-3.5" />
                            Logout
                        </Link>
                    </div>
                )}
                <button
                    type="button"
                    onClick={() => setAccountMenuOpen((open) => !open)}
                    className="flex w-full items-center justify-between rounded-md border border-slate-200 bg-white px-3 py-2.5 text-[13px] font-medium text-slate-700 hover:bg-slate-100"
                >
                    <span className="truncate">{user?.name ?? 'Utilisateur'}</span>
                    <ChevronDown className={['h-3.5 w-3.5 transition-transform', accountMenuOpen ? 'rotate-180' : ''].join(' ')} />
                </button>
            </div>
            {shouldShowUpgradeNudge && !upgradeBannerDismissed && (
                <div className="shrink-0 border-t border-amber-200 bg-gradient-to-r from-amber-50 to-orange-50 px-4 py-3">
                    <div className="flex items-start justify-between gap-2">
                        <div className="text-xs font-semibold text-amber-900">Passez au niveau supérieur</div>
                        <button
                            type="button"
                            onClick={() => setUpgradeBannerDismissed(true)}
                            className="rounded p-0.5 text-amber-700 hover:bg-amber-100 hover:text-amber-900"
                            aria-label="Fermer la bannière d'upgrade"
                        >
                            <X className="h-3.5 w-3.5" />
                        </button>
                    </div>
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

    useEffect(() => {
        const query = quickQuery.trim();

        if (query.length < 2) {
            if (quickSearchAbortRef.current) {
                quickSearchAbortRef.current.abort();
                quickSearchAbortRef.current = null;
            }
            setQuickResults([]);
            setQuickLoading(false);
            return;
        }

        const controller = new AbortController();
        quickSearchAbortRef.current = controller;
        setQuickLoading(true);

        const timer = window.setTimeout(async () => {
            try {
                const response = await fetch(`/search/global?q=${encodeURIComponent(query)}`, {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });

                if (!response.ok) {
                    throw new Error(`Search failed with status ${response.status}`);
                }

                const payload = await response.json();
                setQuickResults(Array.isArray(payload.results) ? payload.results : []);
            } catch (error) {
                if (error?.name !== 'AbortError') {
                    setQuickResults([]);
                }
            } finally {
                if (!controller.signal.aborted) {
                    setQuickLoading(false);
                }
            }
        }, 220);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [quickQuery]);

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

                        <div className="flex items-center gap-3">
                            <div className="hidden xl:block">
                                <div className="group relative">
                                    <div className="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                        <Search className="h-4 w-4 text-slate-400" />
                                        <input
                                            type="text"
                                            value={quickQuery}
                                            onChange={(e) => setQuickQuery(e.target.value)}
                                            placeholder="Recherche action rapide..."
                                            className="w-56 bg-transparent text-sm text-slate-700 outline-none placeholder:text-slate-400"
                                        />
                                    </div>
                                    {quickQuery.trim().length > 0 && (
                                        <div className="absolute right-0 top-[calc(100%+6px)] z-40 w-72 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg">
                                            {quickLoading && (
                                                <div className="px-3 py-2 text-xs text-slate-500">
                                                    Recherche globale en cours...
                                                </div>
                                            )}
                                            {!quickLoading && quickResults.length > 0 ? (
                                                quickResults.map((result, index) => (
                                                    <Link
                                                        key={`${result.type}-${result.href}-${index}`}
                                                        href={result.href}
                                                        onClick={() => setQuickQuery('')}
                                                        className="block px-3 py-2 text-sm text-slate-700 hover:bg-indigo-50 hover:text-indigo-700"
                                                    >
                                                        <div className="flex items-center justify-between gap-2">
                                                            <span className="truncate font-medium">{result.title}</span>
                                                            <span className="shrink-0 text-[10px] uppercase tracking-wide text-slate-400">
                                                                {result.section}
                                                            </span>
                                                        </div>
                                                        {result.description && (
                                                            <div className="truncate text-xs text-slate-500">{result.description}</div>
                                                        )}
                                                    </Link>
                                                ))
                                            ) : (
                                                <div className="px-3 py-2 text-xs text-slate-500">
                                                    {quickQuery.trim().length < 2
                                                        ? 'Tapez au moins 2 caractères'
                                                        : 'Aucun résultat'}
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </div>
                            <div className="hidden items-center gap-2 lg:flex">
                                <Link
                                    href="/invoices/create"
                                    className="rounded-md border border-indigo-200 bg-indigo-50 px-2.5 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100"
                                >
                                    + Facture
                                </Link>
                                <Link
                                    href="/expenses/create"
                                    className="rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                >
                                    + Dépense
                                </Link>
                                <Link
                                    href="/bank/reconcile"
                                    className="rounded-md border border-sky-200 bg-sky-50 px-2.5 py-1.5 text-xs font-medium text-sky-700 hover:bg-sky-100"
                                >
                                    Rapprocher
                                </Link>
                            </div>
                            <Link
                                href={route('legal.terms')}
                                className="hidden items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 md:inline-flex"
                            >
                                <CircleHelp className="h-3.5 w-3.5" />
                                Aide
                            </Link>
                            <div className="hidden text-right sm:block">
                                <div className="text-sm font-medium text-gray-900">
                                    {user?.name ?? 'Utilisateur'}
                                </div>
                                <div className="text-xs text-gray-500">Session active</div>
                            </div>
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
            <StickyBackButton />
        </div>
    );
}
