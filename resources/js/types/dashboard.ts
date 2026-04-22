export interface KpiCards {
  invoiced_this_month: number;
  collected_this_month: number;
  outstanding_receivables: number;
  outstanding_payables: number;
  overdue_count: number;
  overdue_amount: number;
}

export interface MonthPoint {
  month: string;
  label: string;
  value: number;
}

export interface TopClient {
  id: string;
  name: string;
  total_revenue: number;
  invoice_count: number;
}

export interface RecentInvoice {
  id: string;
  number: string;
  total: number;
  payment_status: 'draft' | 'unpaid' | 'partially_paid' | 'paid' | 'overdue';
  issue_date: string;
  client_name: string;
}

export interface DashboardProps {
  kpis: KpiCards;
  charts: {
    invoiced_by_month: MonthPoint[];
    collected_by_month: MonthPoint[];
    expenses_by_month: MonthPoint[];
  };
  top_clients: TopClient[];
  recent_invoices: RecentInvoice[];
}
