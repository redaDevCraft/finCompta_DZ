import { formatCurrency } from '@/utils/format';
import { Link } from '@inertiajs/react';
import PaymentStatusBadge from '@/Components/Invoices/PaymentStatusBadge';

export function RecentInvoicesWidget({ invoices }) {
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="mb-3 flex items-center justify-between">
                <h3 className="text-sm font-medium text-slate-500">Recent Invoices</h3>
                <Link href="/invoices" className="text-xs text-indigo-600 hover:underline">
                    View all →
                </Link>
            </div>
            <ul className="space-y-2">
                {(invoices ?? []).map((invoice) => (
                    <li key={invoice.id} className="flex items-center justify-between text-sm">
                        <div>
                            <Link href={`/invoices/${invoice.id}`} className="font-medium hover:underline">
                                {invoice.number}
                            </Link>
                            <span className="ml-2 text-slate-500">{invoice.client_name}</span>
                        </div>
                        <div className="flex items-center gap-3">
                            <PaymentStatusBadge status={invoice.payment_status} />
                            <span className="tabular-nums font-medium">{formatCurrency(invoice.total)}</span>
                        </div>
                    </li>
                ))}
            </ul>
        </div>
    );
}
