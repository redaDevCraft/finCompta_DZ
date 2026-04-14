import DataTable from '@/Components/DataTable';
import { Button } from '@/Components/ui/button';
import { Plus, FileText, Download } from 'lucide-react';
import { router } from '@inertiajs/react';
import { Head } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function InvoicesIndex({ invoices }) {
  const { data: invoiceData, links } = invoices;

  const columns = [
    {
      header: 'Number',
      accessorKey: 'invoice_number',
      cell: ({ row }) => row.original.invoice_number || 'Draft',
    },
    {
      header: 'Client',
      accessorKey: 'contact.display_name',
      cell: ({ row }) => row.original.contact?.display_name || 'N/A',
    },
    {
      header: 'Amount TTC',
      accessorKey: 'total_ttc',
      cell: ({ row }) => `${row.original.total_ttc?.toLocaleString()} DZD`,
    },
    {
      header: 'Issue Date',
      accessorKey: 'issue_date',
      cell: ({ row }) => new Date(row.original.issue_date).toLocaleDateString(),
    },
    {
      header: 'Status',
      accessorKey: 'status',
      cell: ({ row }) => {
        const status = row.original.status;
        const colors = {
          draft: 'bg-yellow-100 text-yellow-800',
          issued: 'bg-green-100 text-green-800',
          voided: 'bg-red-100 text-red-800',
        };
        return (
          <span className={`px-2 py-1 rounded-full text-xs font-medium ${colors[status] || 'bg-gray-100 text-gray-800'}`}>
            {status}
          </span>
        );
      },
    },
    {
      header: 'Actions',
      id: 'actions',
      cell: ({ row }) => (
        <div className="flex space-x-2">
          <Button variant="outline" size="sm" onClick={() => router.visit(`/invoices/${row.original.id}`)}>
            View
          </Button>
          <Button variant="ghost" size="sm">
            PDF
          </Button>
        </div>
      ),
    },
  ];

  return (
    <AuthenticatedLayout header="Invoices">
      <Head title="Invoices" />
      <div className="py-12">
        <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
          <div className="mb-6 flex justify-between">
            <h1 className="text-2xl font-bold">Invoices</h1>
            <Button onClick={() => router.visit('/invoices/create')}>
              <Plus className="mr-2 h-4 w-4" /> New Invoice
            </Button>
          </div>
          <DataTable columns={columns} data={invoiceData} />
          {/* Pagination links */}
          <div className="mt-4 flex justify-center">
            {links.map(link => (
              <a
                key={link.url}
                href={link.url}
                className={`mx-1 px-3 py-2 rounded ${
                  link.active
                    ? 'bg-blue-500 text-white'
                    : 'bg-white text-gray-700 hover:bg-gray-100'
                }`}
              >
                {link.label}
              </a>
            ))}
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}

