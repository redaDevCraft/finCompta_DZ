const statusMap = {
    draft: {
        label: 'Brouillon',
        className: 'bg-gray-100 text-gray-700 ring-gray-200',
    },
    issued: {
        label: 'Émise',
        className: 'bg-blue-100 text-blue-700 ring-blue-200',
    },
    partially_paid: {
        label: 'Partiellement payée',
        className: 'bg-amber-100 text-amber-700 ring-amber-200',
    },
    paid: {
        label: 'Payée',
        className: 'bg-emerald-100 text-emerald-700 ring-emerald-200',
    },
    voided: {
        label: 'Annulée',
        className: 'bg-red-100 text-red-700 ring-red-200',
    },
    replaced: {
        label: 'Remplacée',
        className: 'bg-purple-100 text-purple-700 ring-purple-200',
    },
};

export default function Badge({ status }) {
    const item = statusMap[status] ?? {
        label: status ?? 'Inconnu',
        className: 'bg-gray-100 text-gray-700 ring-gray-200',
    };

    return (
        <span
            className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset ${item.className}`}
        >
            {item.label}
        </span>
    );
}