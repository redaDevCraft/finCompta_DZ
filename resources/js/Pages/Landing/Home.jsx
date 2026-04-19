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

    return (
        <>
            <Head title={`${brand?.name ?? 'FinCompta DZ'} — Votre comptabilité, dans le cloud`} />

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
                            <a href="#features" className="hover:text-slate-900">Fonctionnalités</a>
                            <a href="#pricing" className="hover:text-slate-900">Tarifs</a>
                            <a href="#faq" className="hover:text-slate-900">FAQ</a>
                        </nav>
                        <div className="flex items-center gap-2">
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
                                Votre comptabilité PME, <span className="text-indigo-600">enfin simple.</span>
                            </h1>
                            <p className="mt-4 max-w-xl text-lg text-slate-600">
                                {brand?.tagline ?? 'Factures, dépenses, bilan, TVA — tout depuis le cloud, conforme DGI.'}
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

                {/* ── FEATURES ─────────────────────────────────────── */}
                <section id="features" className="border-t border-slate-100 bg-white py-20">
                    <div className="mx-auto max-w-6xl px-6">
                        <h2 className="text-center text-3xl font-bold">Tout ce dont votre PME a besoin</h2>
                        <p className="mx-auto mt-3 max-w-2xl text-center text-slate-600">
                            Conforme DGI, SCF, TVA G50 — conçu avec des experts-comptables algériens.
                        </p>
                        <div className="mt-12 grid gap-6 md:grid-cols-3">
                            {[
                                { icon: FileText, title: 'Facturation conforme', text: 'Numérotation DGI, mentions obligatoires, NIF/NIS/RC, AR + FR.' },
                                { icon: Receipt, title: 'Dépenses & justificatifs', text: 'Importez vos factures fournisseurs, OCR ara/fra/eng automatique.' },
                                { icon: ScanLine, title: 'OCR multilingue', text: 'Extraction HT/TVA/TTC — arabe + français sur la même page.' },
                                { icon: BookOpen, title: 'Journal & grand livre', text: 'Écritures automatiques. Balance âgée clients/fournisseurs.' },
                                { icon: Landmark, title: 'Rapprochement bancaire', text: 'Importez vos relevés, lettrage auto des comptes 411/401.' },
                                { icon: BarChart3, title: 'Bilan & CR (SCF)', text: 'Clôture guidée, bilan et compte de résultat exportables PDF.' },
                            ].map((f) => (
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

                {/* ── PRICING ──────────────────────────────────────── */}
                <section id="pricing" className="border-t border-slate-100 py-20">
                    <div className="mx-auto max-w-6xl px-6">
                        <h2 className="text-center text-3xl font-bold">Des tarifs simples</h2>
                        <p className="mx-auto mt-3 max-w-2xl text-center text-slate-600">
                            {trialDays} jours d’essai gratuit sur tous les plans. Payez ensuite par carte Edahabia/CIB
                            (Chargily) ou par virement sur bon de commande.
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
                        <h2 className="text-center text-3xl font-bold">Questions fréquentes</h2>
                        <div className="mt-10 space-y-4">
                            {[
                                { q: 'Est-ce vraiment conforme DGI ?', a: 'Oui — numérotation séquentielle verrouillée, mentions obligatoires (NIF, NIS, RC, AI), TVA G50, et export des journaux au format requis par l’administration.' },
                                { q: 'Comment fonctionne l’essai gratuit ?', a: `${trialDays} jours complets sans carte bancaire. Vous accédez à toutes les fonctionnalités de votre plan. À l’issue, choisissez de payer par Edahabia / CIB (via Chargily) ou par virement bancaire sur bon de commande.` },
                                { q: 'Puis-je payer par bon de commande ?', a: 'Absolument — générez votre bon de commande depuis l’espace Facturation, effectuez le virement, puis déposez le justificatif. Votre abonnement est activé sous 24h après validation.' },
                                { q: 'Mes données sont-elles sauvegardées ?', a: 'Oui, sauvegardes chiffrées quotidiennes. Vous pouvez à tout moment exporter vos données (écritures, journal, balance) en CSV / PDF.' },
                            ].map((f) => (
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
                        <Link
                            href={`/start-trial?plan=${plans[1]?.code ?? plans[0]?.code ?? 'pro'}&cycle=monthly`}
                            className="mt-6 inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-6 py-3 text-base font-medium text-white shadow-sm hover:bg-indigo-700"
                        >
                            <LayoutDashboard className="h-4 w-4" />
                            Essayer gratuitement {trialDays} jours
                        </Link>
                    </div>
                </section>

                <footer className="border-t border-slate-200 py-8">
                    <div className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-6 text-sm text-slate-500 md:flex-row">
                        <div>© {new Date().getFullYear()} FinCompta DZ. Tous droits réservés.</div>
                        <div className="flex items-center gap-4">
                            <Link href={route('login')} className="hover:text-slate-700">Connexion</Link>
                            <a href="#pricing" className="hover:text-slate-700">Tarifs</a>
                            <a href="#faq" className="hover:text-slate-700">FAQ</a>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
