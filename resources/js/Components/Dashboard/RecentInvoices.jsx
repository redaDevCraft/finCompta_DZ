import { formatCurrency } from '@/utils/format';
import { Link } from '@inertiajs/react';
import PaymentStatusBadge from '@/Components/Invoices/PaymentStatusBadge';

export function RecentInvoicesWidget({ invoices }) {
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="mb-3 flex items-center justify-between">
                <h3 className="text-sm font-medium text-slate-500">Factures recentes</h3>
                <Link href="/invoices" className="text-xs text-indigo-600 hover:underline">
                    Tout voir →
                </Link>
            </div>
            <ul className="space-y-2">
                {(invoices ?? []).map((invoice) => (
                    <li key={invoice.id} className="flex min-w-0 items-center justify-between gap-3 text-sm">
                        <div className="min-w-0">
                            <Link href={`/invoices/${invoice.id}`} className="font-medium hover:underline">
                                {invoice.number}
                            </Link>
                            <span className="ml-2 block truncate text-slate-500 sm:inline">{invoice.client_name}</span>
                        </div>
                        <div className="flex shrink-0 items-center gap-3">
                            <PaymentStatusBadge status={invoice.payment_status} />
                            <span className="max-w-[9rem] break-words text-right tabular-nums font-medium sm:max-w-none">
                                {formatCurrency(invoice.total)}
                            </span>
                        </div>
                    </li>
                ))}
            </ul>
        </div>
    );
}
