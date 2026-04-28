import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { KpiCard } from '@/Components/Dashboard/KpiCard';
import { RevenueChart } from '@/Components/Dashboard/RevenueChart';
import { TopClientsTable } from '@/Components/Dashboard/TopClientsTable';
import { RecentInvoicesWidget } from '@/Components/Dashboard/RecentInvoices';

export default function Dashboard({
    kpis = {},
    charts = { invoiced_by_month: [], collected_by_month: [], expenses_by_month: [] },
    top_clients = [],
    recent_invoices = [],
}) {
    return (
        <AuthenticatedLayout header="Tableau de bord">
            <Head title="Tableau de bord" />
            <div className="space-y-6 p-1">
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-5">
                    <KpiCard label="Facturé ce mois" value={kpis.invoiced_this_month} />
                    <KpiCard label="Encaissé ce mois" value={kpis.collected_this_month} variant="success" />
                    <KpiCard label="Créances en attente" value={kpis.outstanding_receivables} />
                    <KpiCard label="Dettes en attente" value={kpis.outstanding_payables} />
                    <KpiCard
                        label="Factures en retard"
                        value={kpis.overdue_amount}
                        subLabel="Nombre"
                        subValue={kpis.overdue_count}
                        variant={Number(kpis.overdue_count) > 0 ? 'danger' : 'default'}
                    />
                </div>

                <RevenueChart
                    invoiced={charts.invoiced_by_month}
                    collected={charts.collected_by_month}
                    expenses={charts.expenses_by_month}
                />

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <TopClientsTable clients={top_clients} />
                    <RecentInvoicesWidget invoices={recent_invoices} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
