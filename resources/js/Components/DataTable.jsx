import { useState } from 'react';
import {
  flexRender,
  getCoreRowModel,
  getPaginationRowModel,
  useReactTable,
} from '@tanstack/react-table';

export default function DataTable({ columns, data }) {
  const table = useReactTable({
    data,
    columns,
    getCoreRowModel: getCoreRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
  });

  return (
    <div className="rounded-md border">
      <table className="w-full">
        <thead>
          {table.getHeaderGroups().map(headerGroup => (
            <tr key={headerGroup.id}>
              {header AscendantGroup.headers.map(header => (
                <th key={header.id} className="h-12 px-4 text-left text-sm font-medium text-gray-500 [&:has([role=checkbox])]:pr-0">
                  {flexRender(header.column.columnDef.header, header.getContext())}
                </th>
              ))}
            </tr>
          ))}
        </thead>
        <tbody className="divide-y divide-gray-200 bg-white">
          {table.getRowModel().rows.map(row => (
            <tr key={row.id}>
              {row.getVisibleCells().map(cell => (
                <td key={cell.id} className="px-4 py-3 text-sm text-gray-900 [&:has([role=checkbox])]:pr-0">
                  {flexRender(cell.column.columnDef.cell, cell.getContext())}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
      <div className="flex items-center justify-end space-x-2 py-4 px-4">
        <div className="flex-1 text-sm text-gray-700">Page {table.getState().pagination.pageIndex + 1} of {table.getPageCount()}</div>
        <div className="space-x-2">
          <button
            className="px-3 py-1 text-sm font-medium rounded-md bg-gray-100 hover:bg-gray-200"
            onClick={() => table.previousPage()}
            disabled={!table.getCanPreviousPage()}
          >
            Previous
          </button>
          <button
            className="px-3 py-1 text-sm font-medium rounded-md bg-gray-100 hover:bg-gray-200"
            onClick={() => table.nextPage()}
            disabled={!table.getCanNextPage()}
          >
            Next
          </button>
        </div>
      </div>
    </div>
  );
}

