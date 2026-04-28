import { formatCurrency } from '@/utils/format';
import { Link } from '@inertiajs/react';

export function TopClientsTable({ clients }) {
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 className="mb-3 text-sm font-medium text-slate-500">Meilleurs clients - cette annee</h3>
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b text-left text-xs text-slate-500">
                        <th className="pb-2">Client</th>
                        <th className="pb-2 text-right">Factures</th>
                        <th className="pb-2 text-right">Chiffre d'affaires</th>
                    </tr>
                </thead>
                <tbody>
                    {(clients ?? []).map((client) => (
                        <tr key={client.id} className="border-b last:border-0">
                            <td className="py-2">
                                <Link href={`/clients/${client.id}`} className="hover:underline">
                                    {client.name}
                                </Link>
                            </td>
                            <td className="py-2 text-right tabular-nums">{client.invoice_count}</td>
                            <td className="py-2 text-right tabular-nums font-medium">
                                {formatCurrency(client.total_revenue)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
