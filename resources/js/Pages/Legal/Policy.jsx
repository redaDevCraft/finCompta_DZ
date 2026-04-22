import { Head, Link } from '@inertiajs/react';

export default function Policy({ title, sections = [] }) {
    return (
        <div className="min-h-screen bg-slate-50 px-4 py-10">
            <Head title={title} />
            <div className="mx-auto max-w-3xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h1 className="text-2xl font-bold text-slate-900">{title}</h1>
                <ul className="mt-4 list-disc space-y-2 pl-5 text-sm text-slate-700">
                    {sections.map((section) => (
                        <li key={section}>{section}</li>
                    ))}
                </ul>
                <div className="mt-6">
                    <Link href="/" className="text-sm text-indigo-600 hover:underline">
                        Retour a l accueil
                    </Link>
                </div>
            </div>
        </div>
    );
}
