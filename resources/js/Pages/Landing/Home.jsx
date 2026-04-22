import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    BarChart3,
    BookOpen,
    CheckCircle2,
    FileText,
    Landmark,
    LayoutDashboard,
    Receipt,
    ScanLine,
    Sparkles,
    ArrowRight,
    ShieldCheck,
    Users,
    Building2,
    UserCog,
    BadgeCheck,
} from 'lucide-react';

function formatDzd(n) {
    return new Intl.NumberFormat('fr-DZ').format(n) + ' DZD';
}

function PlanCard({ plan, cycle, trialDays, featured }) {
    const price = cycle === 'yearly' ? plan.yearly_price_dzd : plan.monthly_price_dzd;
    const cycleLabel = cycle === 'yearly' ? '/an' : '/mois';

    return (
        <div
            className={[
                'flex flex-col rounded-2xl border p-6 shadow-sm transition',
                featured
                    ? 'border-indigo-500 bg-white ring-2 ring-indigo-200'
                    : 'border-slate-200 bg-white',
            ].join(' ')}
        >
            {featured && (
                <div className="mb-3 inline-flex w-fit items-center gap-1 rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-semibold text-indigo-700">
                    <Sparkles className="h-3 w-3" />
                    Le plus populaire
                </div>
            )}
            <h3 className="text-xl font-semibold text-slate-900">{plan.name}</h3>
            <p className="mt-1 text-sm text-slate-500">{plan.tagline}</p>

            <div className="mt-6 flex items-baseline gap-1">
                <span className="text-4xl font-bold text-slate-900">{formatDzd(price)}</span>
                <span className="text-sm text-slate-500">{cycleLabel}</span>
            </div>
            <p className="mt-1 text-xs text-slate-500">
                {trialDays} jours d’essai gratuit · sans carte
            </p>

            <ul className="mt-6 flex-1 space-y-2">
                {(plan.features ?? []).map((f) => (
                    <li key={f} className="flex items-start gap-2 text-sm text-slate-700">
                        <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" />
                        <span>{f}</span>
                    </li>
                ))}
            </ul>

            <Link
                href={`/start-trial?plan=${plan.code}&cycle=${cycle}`}
                className={[
                    'mt-8 inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium transition',
                    featured
                        ? 'bg-indigo-600 text-white hover:bg-indigo-700'
                        : 'border border-slate-300 bg-white text-slate-800 hover:bg-slate-50',
                ].join(' ')}
            >
                Commencer l’essai
                <ArrowRight className="h-4 w-4" />
            </Link>
        </div>
    );
}

export default function Home({ plans = [], trialDays = 3, brand }) {
    const { auth } = usePage().props;
    const [cycle, setCycle] = useState('monthly');
    const heroTitle = 'Comptabilité cloud pour les entreprises algériennes, construite sur le SCF.';
    const personas = [
        {
            icon: Building2,
            title: 'Gérants PME (SARL / EURL)',
            points: [
                'Suivi factures, dépenses et trésorerie en temps réel',
                'Accès à distance depuis bureau, maison ou mobile',
                'Pilotage multi-société depuis un seul espace',
            ],
        },
        {
            icon: UserCog,
            title: 'Cabinets comptables',
            points: [
                'Gestion multi-dossiers clients en cloud',
                'Collaboration avec vos équipes sans échanges USB',
                'Exports SCF, journaux et états pour révision rapide',
            ],
        },
        {
            icon: Users,
            title: 'Chefs comptables',
            points: [
                'Écritures, grand livre et balance centralisés',
                'TVA G50 et contrôles de cohérence intégrés',
                'Traçabilité des actions par utilisateur',
            ],
        },
    ];
    const trustItems = [
        {
            icon: BadgeCheck,
            title: 'Conformité SCF',
            text: 'Plan comptable, journaux et clôture alignés sur les pratiques SCF pour PME algériennes.',
        },
        {
            icon: FileText,
            title: 'Facturation compatible DGI',
            text: 'Numérotation, mentions légales et exports prêts pour vos obligations fiscales.',
        },
        {
            icon: ShieldCheck,
            title: 'Sauvegardes chiffrées',
            text: 'Sauvegardes quotidiennes et export des données à tout moment, sans verrouillage.',
        },
        {
            icon: Landmark,
            title: 'Paiement local',
            text: 'Abonnement via Chargily (Edahabia/CIB) ou bon de commande avec validation manuelle.',
        },
    ];
    const features = [
        {
            icon: BookOpen,
            title: 'Comptabilité SCF complète',
            text: 'Plan comptable, journaux, grand livre, balance et outils de clôture alignés sur le SCF algérien.',
        },
        {
            icon: Receipt,
            title: 'Flux ventes & achats',
            text: 'Émettez vos factures clients, enregistrez vos factures fournisseurs, joignez les justificatifs et suivez les soldes.',
        },
        {
            icon: Landmark,
            title: 'Import bancaire & rapprochement',
            text: 'Importez vos relevés bancaires, rapprochez les opérations automatiquement et lettez les comptes 411/401.',
        },
        {
            icon: BarChart3,
            title: 'TVA G50 & rapports',
            text: 'Générez la TVA G50, les rapports de résultat et de TVA, puis exportez en PDF/Excel en un clic.',
        },
        {
            icon: LayoutDashboard,
            title: 'Multi-société, multi-utilisateur',
            text: 'Gérez plusieurs sociétés dans un seul espace et invitez votre comptable avec des droits précis.',
        },
        {
            icon: ShieldCheck,
            title: 'Cloud, sauvegardes & sécurité',
            text: 'Données chiffrées, sauvegardes quotidiennes, accès par rôles et paiements sécurisés via Chargily ou virement.',
        },
    ];
    const faqItems = [
        {
            q: 'Comment fonctionne l’essai gratuit ?',
            a: `${trialDays} jours complets sans carte bancaire. Vous pouvez démarrer immédiatement et activer un plan ensuite.`,
        },
        {
            q: 'Puis-je exporter toutes mes données comptables ?',
            a: 'Oui. Exportez vos journaux, balances et principaux rapports à tout moment (CSV/PDF), sans verrouillage.',
        },
        {
            q: 'Puis-je arrêter mon abonnement facilement ?',
            a: 'Oui. Le modèle est sans engagement long terme et vous pouvez arrêter depuis votre espace de facturation.',
        },
        {
            q: 'L’application est-elle accessible à distance ?',
            a: 'Oui. FinCompta DZ est accessible depuis navigateur, au bureau, à domicile ou en déplacement.',
        },
    ];

    return (
        <>
            <Head title={`${brand?.name ?? 'FinCompta DZ'} — Comptabilité cloud SCF pour PME algériennes`}>
                <meta
                    name="description"
                    content="FinCompta DZ — plateforme de comptabilité cloud pour PME et cabinets algériens : SCF, TVA G50, journaux, grand livre et collaboration sécurisée."
                />
                <meta property="og:title" content="FinCompta DZ — Comptabilité cloud SCF pour PME algériennes" />
                <meta
                    property="og:description"
                    content="Plateforme de comptabilité cloud sérieuse pour entreprises et cabinets en Algérie."
                />
                <meta property="og:image" content="/og-image.png" />
            </Head>

            <div className="min-h-screen bg-gradient-to-b from-slate-50 to-white text-slate-900">
                {/* ── NAV ──────────────────────────────────────────── */}
                <header className="sticky top-0 z-40 border-b border-slate-200/60 bg-white/80 backdrop-blur">
                    <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                        <Link href="/" className="flex items-center gap-2">
                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600 text-white">
                                <BookOpen className="h-4 w-4" />
                            </div>
                            <span className="text-lg font-bold">FinCompta DZ</span>
                        </Link>
                        <nav className="hidden items-center gap-6 text-sm text-slate-600 md:flex">
                            <a href="#features" className="hover:text-slate-900">Produit</a>
                            <a href="#pricing" className="hover:text-slate-900">Tarifs</a>
                            <a href="#ressources" className="hover:text-slate-900">Ressources</a>
                            <a href="#societe" className="hover:text-slate-900">Société</a>
                            <a href="#faq" className="hover:text-slate-900">FAQ</a>
                        </nav>
                        <div className="flex items-center gap-2">
                            <span className="hidden text-xs font-medium text-slate-500 sm:inline">FR</span>
                            {auth?.user ? (
                                <Link
                                    href="/dashboard"
                                    className="rounded-lg bg-slate-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-slate-800"
                                >
                                    Mon espace
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={route('login')}
                                        className="hidden rounded-lg px-3 py-2 text-sm text-slate-700 hover:text-slate-900 sm:inline"
                                    >
                                        Connexion
                                    </Link>
                                    <Link
                                        href={`/start-trial?plan=${plans[0]?.code ?? 'starter'}&cycle=monthly`}
                                        className="rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                                    >
                                        Essai gratuit
                                    </Link>
                                </>
                            )}
                        </div>
                    </div>
                </header>

                {/* ── HERO ─────────────────────────────────────────── */}
                <section className="relative overflow-hidden">
                    <div className="mx-auto grid max-w-6xl items-center gap-10 px-6 py-16 md:grid-cols-2 md:py-24">
                        <div>
                            <div className="inline-flex items-center gap-1.5 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700">
                                <Sparkles className="h-3 w-3" /> Conçu pour l’Algérie — SCF, TVA, G50
                            </div>
                            <h1 className="mt-5 text-4xl font-bold leading-tight tracking-tight md:text-5xl">
                                {heroTitle}
                            </h1>
                            <p className="mt-4 max-w-xl text-lg text-slate-600">
                                {brand?.tagline ?? 'Centralize invoicing, expenses, bank reconciliation, journal posting and TVA G50 in one secure cloud platform.'}
                                {' '}Accessible anytime, from any device, for business owners and accounting teams.
                                {' '}Démarrez avec <strong>{trialDays} jours d’essai gratuit</strong>, aucune carte requise.
                            </p>
                            <div className="mt-8 flex flex-wrap gap-3">
                                <Link
                                    href={`/start-trial?plan=${plans[1]?.code ?? plans[0]?.code ?? 'pro'}&cycle=monthly`}
                                    className="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-3 text-base font-medium text-white shadow-sm transition hover:bg-indigo-700"
                                >
                                    Commencer l’essai gratuit
                                    <ArrowRight className="h-4 w-4" />
                                </Link>
                                <a
                                    href="#pricing"
                                    className="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-5 py-3 text-base font-medium text-slate-800 hover:bg-slate-50"
                                >
                                    Voir les tarifs
                                </a>
                            </div>
                            <p className="mt-4 flex items-center gap-2 text-xs text-slate-500">
                                <ShieldCheck className="h-4 w-4 text-emerald-500" />
                                Paiement sécurisé via Chargily (Edahabia / CIB) ou bon de commande.
                            </p>
                        </div>

                        {/* Mock dashboard card */}
                        <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-xl">
                            <div className="flex items-center gap-2 border-b border-slate-100 pb-3">
                                <div className="h-2.5 w-2.5 rounded-full bg-rose-400"/>
                                <div className="h-2.5 w-2.5 rounded-full bg-amber-400"/>
                                <div className="h-2.5 w-2.5 rounded-full bg-emerald-400"/>
                                <div className="ml-3 text-xs text-slate-400">fincompta.dz / dashboard</div>
                            </div>
                            <div className="grid grid-cols-3 gap-3 pt-4">
                                {[
                                    { label: 'Trésorerie', value: '2 430 700 DZD', color: 'from-indigo-50 to-indigo-100 text-indigo-800' },
                                    { label: 'Clients', value: '1 120 450 DZD', color: 'from-emerald-50 to-emerald-100 text-emerald-800' },
                                    { label: 'Fournisseurs', value: '415 230 DZD', color: 'from-rose-50 to-rose-100 text-rose-800' },
                                ].map((k) => (
                                    <div key={k.label} className={`rounded-xl bg-gradient-to-br ${k.color} px-3 py-2.5`}>
                                        <div className="text-[10px] uppercase tracking-wide opacity-70">{k.label}</div>
                                        <div className="mt-1 text-sm font-bold">{k.value}</div>
                                    </div>
                                ))}
                            </div>
                            <div className="mt-4 flex h-36 items-end gap-1.5 rounded-xl bg-slate-50 p-3">
                                {[30, 55, 35, 62, 48, 70, 58, 85, 72, 90, 65, 78].map((h, i) => (
                                    <div
                                        key={i}
                                        style={{ height: `${h}%` }}
                                        className="flex-1 rounded-t bg-gradient-to-b from-indigo-400 to-indigo-600"
                                    />
                                ))}
                            </div>
                        </div>
                    </div>
                </section>

                <section className="border-t border-slate-100 bg-white py-16">
                    <div className="mx-auto max-w-6xl px-6">
                        <h2 className="text-center text-3xl font-bold">Conçu pour les PME et les cabinets algériens</h2>
                        <div className="mt-8 grid gap-6 md:grid-cols-3">
                            {personas.map((persona) => (
                                <div key={persona.title} className="rounded-2xl border border-slate-200 bg-slate-50 p-6">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-white text-indigo-600 shadow-sm">
                                        <persona.icon className="h-5 w-5" />
                                    </div>
                                    <h3 className="mt-4 text-lg font-semibold text-slate-900">{persona.title}</h3>
                                    <ul className="mt-3 space-y-2 text-sm text-slate-600">
                                        {persona.points.map((point) => (
                                            <li key={point} className="flex items-start gap-2">
                                                <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" />
                                                <span>{point}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* ── FEATURES ─────────────────────────────────────── */}
                <section id="features" className="border-t border-slate-100 bg-white py-20">
                    <div className="mx-auto max-w-6xl px-6">
                        <h2 className="text-center text-3xl font-bold">Tout ce dont votre PME a besoin</h2>
                        <p className="mx-auto mt-3 max-w-2xl text-center text-slate-600">
                            Comptabilité cœur, automatisation, collaboration et conformité dans un espace cloud unique.
                        </p>
                        <div className="mt-12 grid gap-6 md:grid-cols-3">
                            {features.map((f) => (
                                <div key={f.title} className="rounded-2xl border border-slate-200 bg-slate-50 p-6">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-white text-indigo-600 shadow-sm">
                                        <f.icon className="h-5 w-5" />
                                    </div>
                                    <h3 className="mt-4 font-semibold text-slate-900">{f.title}</h3>
                                    <p className="mt-2 text-sm text-slate-600">{f.text}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="border-t border-slate-100 bg-white py-20">
                    <div className="mx-auto max-w-4xl px-6 text-center">
                        <h2 className="text-3xl font-bold">La mise en route est simple</h2>
                        <p className="mt-3 text-slate-600">
                            Du premier accès à votre première TVA G50 en quelques étapes, sans déploiement complexe.
                        </p>
                        <div className="mt-10 grid gap-6 text-left md:grid-cols-3">
                            <div className="rounded-xl border border-slate-200 bg-slate-50 p-5">
                                <h3 className="mb-1 font-semibold">1. Créez votre espace</h3>
                                <p className="text-sm text-slate-600">
                                    Connectez-vous avec Google, créez votre société et invitez votre comptable si besoin.
                                </p>
                            </div>
                            <div className="rounded-xl border border-slate-200 bg-slate-50 p-5">
                                <h3 className="mb-1 font-semibold">2. Configurez votre plan</h3>
                                <p className="text-sm text-slate-600">
                                    Démarrez avec les comptes SCF et paramètres fiscaux intégrés, puis adaptez à votre activité.
                                </p>
                            </div>
                            <div className="rounded-xl border border-slate-200 bg-slate-50 p-5">
                                <h3 className="mb-1 font-semibold">3. Commencez à saisir</h3>
                                <p className="text-sm text-slate-600">
                                    Émettez vos factures, saisissez vos dépenses et rapprochez vos mouvements bancaires.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="border-t border-slate-100 bg-slate-50 py-20">
                    <div className="mx-auto grid max-w-6xl items-center gap-10 px-6 md:grid-cols-2">
                        <div>
                            <h2 className="text-3xl font-bold">Pourquoi passer votre comptabilité dans le cloud ?</h2>
                            <p className="mt-3 text-slate-600">
                                FinCompta DZ garde vos données comptables centralisées, sécurisées et toujours disponibles pour votre équipe.
                            </p>
                            <ul className="mt-6 space-y-2 text-sm text-slate-700">
                                <li>• Accédez à vos comptes en sécurité depuis le bureau, la maison ou chez le client.</li>
                                <li>• Partagez un espace unique entre dirigeants et comptables.</li>
                                <li>• Profitez des mises à jour automatiques sans réinstallation.</li>
                                <li>• Disposez de sauvegardes quotidiennes et d’exports à tout moment.</li>
                            </ul>
                        </div>
                        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <h3 className="text-lg font-semibold text-slate-900">Vue d’ensemble des opérations cloud</h3>
                            <p className="mt-2 text-sm text-slate-600">
                                Accès en temps réel aux factures, dépenses, soldes et statuts de rapprochement dans un tableau unique.
                            </p>
                            <div className="mt-5 grid grid-cols-2 gap-3">
                                <div className="rounded-xl bg-indigo-50 p-3">
                                    <div className="text-[11px] uppercase tracking-wide text-indigo-600">Disponibilité</div>
                                    <div className="mt-1 text-sm font-semibold text-indigo-900">Accès 24/7</div>
                                </div>
                                <div className="rounded-xl bg-emerald-50 p-3">
                                    <div className="text-[11px] uppercase tracking-wide text-emerald-600">Sauvegardes</div>
                                    <div className="mt-1 text-sm font-semibold text-emerald-900">Quotidiens</div>
                                </div>
                                <div className="rounded-xl bg-amber-50 p-3">
                                    <div className="text-[11px] uppercase tracking-wide text-amber-600">Collaboration</div>
                                    <div className="mt-1 text-sm font-semibold text-amber-900">Multi-utilisateur</div>
                                </div>
                                <div className="rounded-xl bg-slate-100 p-3">
                                    <div className="text-[11px] uppercase tracking-wide text-slate-600">Conformité</div>
                                    <div className="mt-1 text-sm font-semibold text-slate-900">SCF + TVA G50</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="societe" className="border-t border-slate-100 bg-slate-50 py-20">
                    <div className="mx-auto max-w-6xl px-6">
                        <h2 className="text-center text-3xl font-bold">Confiance & conformité pour l’Algérie</h2>
                        <div className="mt-10 grid gap-6 md:grid-cols-4">
                            {trustItems.map((item) => (
                                <div key={item.title} className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-50 text-indigo-600">
                                        <item.icon className="h-5 w-5" />
                                    </div>
                                    <h3 className="mt-4 font-semibold text-slate-900">{item.title}</h3>
                                    <p className="mt-2 text-sm text-slate-600">{item.text}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* ── PRICING ──────────────────────────────────────── */}
                <section id="pricing" className="border-t border-slate-100 py-20">
                    <div className="mx-auto max-w-6xl px-6">
                        <h2 className="text-center text-3xl font-bold">Des tarifs simples</h2>
                        <p className="mx-auto mt-3 max-w-2xl text-center text-slate-600">
                            {trialDays} jours d’essai gratuit sur tous les plans. Sans engagement long terme, annulation à tout moment.
                            Paiement mensuel ou annuel via Edahabia/CIB (Chargily) ou virement sur bon de commande.
                        </p>

                        <div className="mt-6 flex justify-center">
                            <div className="inline-flex rounded-full border border-slate-200 bg-white p-1 text-sm">
                                <button
                                    type="button"
                                    onClick={() => setCycle('monthly')}
                                    className={[
                                        'rounded-full px-4 py-1.5 transition',
                                        cycle === 'monthly' ? 'bg-indigo-600 text-white' : 'text-slate-600',
                                    ].join(' ')}
                                >
                                    Mensuel
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setCycle('yearly')}
                                    className={[
                                        'rounded-full px-4 py-1.5 transition',
                                        cycle === 'yearly' ? 'bg-indigo-600 text-white' : 'text-slate-600',
                                    ].join(' ')}
                                >
                                    Annuel <span className="ml-1 rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700">-17%</span>
                                </button>
                            </div>
                        </div>

                        <div className="mt-10 grid gap-6 md:grid-cols-3">
                            {plans.map((plan, idx) => (
                                <PlanCard
                                    key={plan.id}
                                    plan={plan}
                                    cycle={cycle}
                                    trialDays={trialDays}
                                    featured={idx === 1}
                                />
                            ))}
                        </div>
                    </div>
                </section>

                {/* ── FAQ ──────────────────────────────────────────── */}
                <section id="faq" className="border-t border-slate-100 bg-slate-50 py-20">
                    <div className="mx-auto max-w-3xl px-6">
                        <h2 id="ressources" className="text-center text-3xl font-bold">Questions fréquentes</h2>
                        <div className="mt-10 space-y-4">
                            {faqItems.map((f) => (
                                <details key={f.q} className="group rounded-xl border border-slate-200 bg-white p-4">
                                    <summary className="cursor-pointer list-none font-medium text-slate-900 group-open:text-indigo-600">
                                        {f.q}
                                    </summary>
                                    <p className="mt-2 text-sm text-slate-600">{f.a}</p>
                                </details>
                            ))}
                        </div>
                    </div>
                </section>

                {/* ── CTA / FOOTER ─────────────────────────────────── */}
                <section className="border-t border-slate-100 py-16">
                    <div className="mx-auto max-w-4xl px-6 text-center">
                        <h2 className="text-3xl font-bold">Prêt à simplifier votre compta ?</h2>
                        <p className="mx-auto mt-3 max-w-xl text-slate-600">
                            Rejoignez les entreprises algériennes qui bossent dans le cloud — démarrez gratuitement.
                        </p>
                        <p className="mx-auto mt-2 max-w-xl text-sm text-slate-500">
                            Aucun frais d’installation, aucune carte requise pendant l’essai.
                        </p>
                        <Link
                            href={`/start-trial?plan=${plans[1]?.code ?? plans[0]?.code ?? 'pro'}&cycle=monthly`}
                            className="mt-6 inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-6 py-3 text-base font-medium text-white shadow-sm hover:bg-indigo-700"
                        >
                            <LayoutDashboard className="h-4 w-4" />
                            Essayer gratuitement {trialDays} jours
                        </Link>
                    </div>
                </section>

                <footer className="border-t border-slate-200 bg-slate-50 py-10">
                    <div className="mx-auto grid max-w-6xl gap-8 px-6 text-sm text-slate-600 md:grid-cols-4">
                        <div>
                            <div className="flex items-center gap-2">
                                <div className="flex h-7 w-7 items-center justify-center rounded-md bg-indigo-600 text-white">
                                    <BookOpen className="h-4 w-4" />
                                </div>
                                <span className="text-base font-bold text-slate-900">FinCompta DZ</span>
                            </div>
                            <p className="mt-3 max-w-xs">
                                Plateforme de comptabilité cloud pour PME et cabinets algériens :
                                SCF, journaux, TVA G50, rapprochement bancaire et rapports financiers
                                dans un espace unique.
                            </p>
                        </div>

                        <div>
                            <h3 className="text-xs font-semibold uppercase tracking-wide text-slate-500">Produit</h3>
                            <ul className="mt-3 space-y-1.5">
                                <li><a href="#features" className="hover:text-slate-900">Vue d’ensemble</a></li>
                                <li><a href="#pricing" className="hover:text-slate-900">Tarifs</a></li>
                                <li><a href="#societe" className="hover:text-slate-900">Confiance & conformité</a></li>
                            </ul>
                        </div>

                        <div>
                            <h3 className="text-xs font-semibold uppercase tracking-wide text-slate-500">Ressources</h3>
                            <ul className="mt-3 space-y-1.5">
                                <li><a href="#faq" className="hover:text-slate-900">FAQ</a></li>
                                <li><a href="#ressources" className="hover:text-slate-900">Aide & ressources</a></li>
                                <li>
                                    <a href="mailto:support@fincompta.dz" className="hover:text-slate-900">
                                        Contact & support
                                    </a>
                                </li>
                            </ul>
                        </div>

                        <div>
                            <h3 className="text-xs font-semibold uppercase tracking-wide text-slate-500">Société & légal</h3>
                            <ul className="mt-3 space-y-1.5">
                                <li><a href="#societe" className="hover:text-slate-900">À propos</a></li>
                                <li><Link href="/legal/policy" className="hover:text-slate-900">Politique de confidentialité</Link></li>
                                <li><a href="#pricing" className="hover:text-slate-900">Conditions d’abonnement</a></li>
                            </ul>
                        </div>
                    </div>

                    <div className="mx-auto mt-6 flex max-w-6xl flex-col items-center justify-between gap-3 px-6 text-xs text-slate-500 md:flex-row">
                        <div>© {new Date().getFullYear()} FinCompta DZ. Tous droits réservés.</div>
                        <div className="flex items-center gap-4">
                            <Link href={route('login')} className="hover:text-slate-700">Connexion</Link>
                            <a href="#pricing" className="hover:text-slate-700">Tarifs</a>
                            <span>FR</span>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
