import React from 'react';

export interface Column<T> {
    header: string;
    accessor: keyof T | ((row: T) => React.ReactNode);
    className?: string;
    align?: 'left' | 'center' | 'right';
}

interface DataTableProps<T> {
    columns: Column<T>[];
    data: T[];
    keyExtractor: (row: T, index: number) => string | number;
    emptyMessage?: string;
    maxHeightClass?: string;
    className?: string;
}

export default function DataTable<T>({
    columns,
    data,
    keyExtractor,
    emptyMessage = 'No records found.',
    maxHeightClass = 'max-h-[600px]',
    className = ''
}: DataTableProps<T>) {
    return (
        <div className={`overflow-x-auto relative rounded-xl border border-[#E5E7EB] bg-white shadow-sm ${maxHeightClass} ${className}`}>
            <table className="w-full text-left border-collapse table-auto">
                <thead className="sticky top-0 z-10 bg-[#F9FAFB] border-b border-[#E5E7EB]">
                    <tr className="text-gray-600 text-xs uppercase font-semibold font-mono">
                        {columns.map((col, idx) => {
                            const alignClass =
                                col.align === 'right' ? 'text-right' :
                                col.align === 'center' ? 'text-center' : 'text-left';
                            return (
                                <th
                                    key={idx}
                                    className={`py-3.5 px-5 font-semibold tracking-wider ${alignClass} ${col.className || ''}`}
                                >
                                    {col.header}
                                </th>
                            );
                        })}
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-200 text-sm text-gray-900 font-mono">
                    {data.length === 0 ? (
                        <tr>
                            <td colSpan={columns.length} className="py-12 text-center text-gray-600 font-mono">
                                {emptyMessage}
                            </td>
                        </tr>
                    ) : (
                        data.map((row, rowIdx) => (
                            <tr key={keyExtractor(row, rowIdx)} className="hover:bg-gray-50 transition duration-150 ease-in-out">
                                {columns.map((col, colIdx) => {
                                    const alignClass =
                                        col.align === 'right' ? 'text-right' :
                                        col.align === 'center' ? 'text-center' : 'text-left';

                                    const cellContent = typeof col.accessor === 'function'
                                        ? col.accessor(row)
                                        : (row[col.accessor] as React.ReactNode);

                                    return (
                                        <td
                                            key={colIdx}
                                            className={`py-3.5 px-5 align-middle ${alignClass} ${col.className || ''}`}
                                        >
                                            {cellContent}
                                        </td>
                                    );
                                })}
                            </tr>
                        ))
                    )}
                </tbody>
            </table>
        </div>
    );
}
