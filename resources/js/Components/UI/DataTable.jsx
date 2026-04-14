import { useMemo, useState } from 'react';
import { Link } from '@inertiajs/react';
import {
    flexRender,
    getCoreRowModel,
    getSortedRowModel,
    useReactTable,
} from '@tanstack/react-table';
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';

function SortIcon({ sorted }) {
    if (sorted === 'asc') {
        return <ArrowUp className="h-4 w-4" />;
    }

    if (sorted === 'desc') {
        return <ArrowDown className="h-4 w-4" />;
    }

    return <ArrowUpDown className="h-4 w-4 opacity-50" />;
}

export default function DataTable({ columns = [], data = [], pagination = null }) {
    const [sorting, setSorting] = useState([]);

    const table = useReactTable({
        data,
        columns,
        state: { sorting },
        onSortingChange: setSorting,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
    });

    const pageLinks = useMemo(() => pagination?.links ?? [], [pagination]);

    return (
        <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        {table.getHeaderGroups().map((headerGroup) => (
                            <tr key={headerGroup.id}>
                                {headerGroup.headers.map((header) => {
                                    const canSort = header.column.getCanSort();
                                    const sorted = header.column.getIsSorted();

                                    return (
                                        <th
                                            key={header.id}
                                            className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600"
                                        >
                                            {header.isPlaceholder ? null : canSort ? (
                                                <button
                                                    type="button"
                                                    onClick={header.column.getToggleSortingHandler()}
                                                    className="inline-flex items-center gap-2"
                                                >
                                                    {flexRender(header.column.columnDef.header, header.getContext())}
                                                    <SortIcon sorted={sorted} />
                                                </button>
                                            ) : (
                                                flexRender(header.column.columnDef.header, header.getContext())
                                            )}
                                        </th>
                                    );
                                })}
                            </tr>
                        ))}
                    </thead>

                    <tbody className="divide-y divide-gray-100 bg-white">
                        {table.getRowModel().rows.length > 0 ? (
                            table.getRowModel().rows.map((row) => (
                                <tr key={row.id} className="hover:bg-gray-50">
                                    {row.getVisibleCells().map((cell) => (
                                        <td key={cell.id} className="px-4 py-3 text-sm text-gray-700">
                                            {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                        </td>
                                    ))}
                                </tr>
                            ))
                        ) : (
                            <tr>
                                <td
                                    colSpan={columns.length || 1}
                                    className="px-4 py-10 text-center text-sm text-gray-500"
                                >
                                    Aucun résultat trouvé
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {pagination && pageLinks.length > 0 ? (
                <div className="flex flex-wrap items-center gap-2 border-t border-gray-200 px-4 py-3">
                    {pageLinks.map((link, index) => (
                        <Link
                            key={`${link.label}-${index}`}
                            href={link.url || '#'}
                            preserveScroll
                            className={[
                                'rounded-md px-3 py-1.5 text-sm',
                                link.active
                                    ? 'bg-indigo-600 text-white'
                                    : link.url
                                    ? 'bg-white text-gray-700 hover:bg-gray-50'
                                    : 'cursor-not-allowed bg-gray-100 text-gray-400',
                            ].join(' ')}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            ) : null}
        </div>
    );
}