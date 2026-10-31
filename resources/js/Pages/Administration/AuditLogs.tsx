import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import {
    Search,
    Filter,
    Eye,
    History,
    X,
    Activity
} from 'lucide-react';

interface AuditLog {
    id: number;
    user_id: number | null;
    action: string;
    model_type: string;
    model_id: number;
    before_data: any;
    after_data: any;
    ip_address: string;
    created_at: string;
    user: {
        id: number;
        name: string;
    } | null;
}

interface User {
    id: number;
    name: string;
    email: string;
}

interface AuditLogsProps {
    auditLogs: {
        data: AuditLog[];
        links: any[];
        current_page: number;
        last_page: number;
        from: number;
        to: number;
        total: number;
    };
    users: User[];
    filters: {
        user_id?: string;
        model_type?: string;
        action?: string;
        date_from?: string;
        date_to?: string;
        search?: string;
    };
}

export default function AuditLogs({ auditLogs, users, filters }: AuditLogsProps) {
    const [selectedLog, setSelectedLog] = useState<AuditLog | null>(null);
    const [filterState, setFilterState] = useState({
        user_id: filters.user_id || '',
        model_type: filters.model_type || '',
        action: filters.action || '',
        date_from: filters.date_from || '',
        date_to: filters.date_to || '',
        search: filters.search || '',
    });

    const handleFilterChange = (e: React.ChangeEvent<HTMLSelectElement | HTMLInputElement>) => {
        const { name, value } = e.target;
        setFilterState(prev => ({ ...prev, [name]: value }));
    };

    const applyFilters = () => {
        router.get('/administration/audit-logs', filterState, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const clearFilters = () => {
        setFilterState({
            user_id: '',
            model_type: '',
            action: '',
            date_from: '',
            date_to: '',
            search: '',
        });
        router.get('/administration/audit-logs');
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
    };

    const formatModelName = (modelType: string) => {
        const parts = modelType.split('\\');
        return parts[parts.length - 1];
    };

    const getActionColor = (action: string) => {
        switch (action) {
            case 'created': return 'text-[#10B981] bg-[#10B981]/10 border-[#10B981]/20 shadow-[0_0_10px_rgba(16,185,129,0.1)]';
            case 'updated': return 'text-[#2563EB] bg-[#2563EB]/10 border-[#2563EB]/20 shadow-[0_0_10px_rgba(37,99,235,0.1)]';
            case 'deleted': return 'text-[#EF4444] bg-[#EF4444]/10 border-[#EF4444]/20 shadow-[0_0_10px_rgba(239,68,68,0.1)]';
            default: return 'text-gray-400 bg-gray-500/10 border-gray-500/20';
        }
    };

    return (
        <AppLayout title="Enterprise Audit Log">

            {/* Header Info */}
            <div className="mb-6 flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-xl bg-[#06B6D4]/10 border border-[#06B6D4]/20 flex items-center justify-center">
                        <History size={20} className="text-amber-800" />
                    </div>
                    <div>
                        <h2 className="text-lg font-bold text-gray-900">System Activity Trail</h2>
                        <p className="text-xs text-gray-700">Tracking every financial and critical modification.</p>
                    </div>
                </div>
            </div>

            {/* Filters */}
            <div className="glass-panel rounded-2xl p-5 mb-6">
                <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    <div className="lg:col-span-2 relative">
                        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <Search size={14} className="text-gray-500" />
                        </div>
                        <input
                            type="text"
                            name="search"
                            value={filterState.search}
                            onChange={handleFilterChange}
                            placeholder="Search IP, Model ID..."
                            className="w-full bg-white border border-gray-300 rounded-xl pl-9 pr-4 py-2 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                        />
                    </div>

                    <div>
                        <select
                            name="user_id"
                            value={filterState.user_id}
                            onChange={handleFilterChange}
                            className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                        >
                            <option value="">All Users</option>
                            {users.map(u => (
                                <option key={u.id} value={u.id}>{u.name}</option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <select
                            name="action"
                            value={filterState.action}
                            onChange={handleFilterChange}
                            className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                        >
                            <option value="">All Actions</option>
                            <option value="created">Created</option>
                            <option value="updated">Updated</option>
                            <option value="deleted">Deleted</option>
                        </select>
                    </div>

                    <div>
                        <input
                            type="date"
                            name="date_from"
                            value={filterState.date_from}
                            onChange={handleFilterChange}
                            className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-sm text-gray-400 focus:text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                        />
                    </div>

                    <div className="flex gap-2">
                        <button
                            onClick={applyFilters}
                            className="flex-1 bg-[#2563EB] hover:bg-blue-700 text-white rounded-xl py-2 text-sm font-medium shadow-lg hover:shadow-blue-500/20 transition-all flex items-center justify-center gap-2"
                        >
                            <Filter size={14} /> Filter
                        </button>
                        <button
                            onClick={clearFilters}
                            className="px-3 bg-gray-50 hover:bg-gray-500 text-gray-500 hover:text-gray-900 rounded-xl py-2 transition-all flex items-center justify-center"
                            title="Clear Filters"
                        >
                            <X size={16} />
                        </button>
                    </div>
                </div>
            </div>

            {/* Main Table */}
            <div className="glass-panel rounded-2xl overflow-hidden mb-6">
                <div className="overflow-x-auto">
                    <table className="w-full text-left border-collapse">
                        <thead>
                            <tr className="border-b border-gray-200 text-[10px] text-gray-600 uppercase tracking-wider font-semibold bg-[#F9FAFB]">
                                <th className="px-6 py-4">Timestamp</th>
                                <th className="px-6 py-4">User</th>
                                <th className="px-6 py-4">Action</th>
                                <th className="px-6 py-4">Model & ID</th>
                                <th className="px-6 py-4">IP Address</th>
                                <th className="px-6 py-4 text-center">Details</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200">
                            {auditLogs.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-6 py-12 text-center text-sm text-gray-500 font-mono">
                                        No audit records found matching criteria.
                                    </td>
                                </tr>
                            ) : (
                                auditLogs.data.map((log) => (
                                    <tr key={log.id} className="hover:bg-white/[0.02] transition-colors duration-200">
                                        <td className="px-6 py-3 text-xs text-gray-700 font-mono">
                                            {formatDate(log.created_at)}
                                        </td>
                                        <td className="px-6 py-3 text-sm text-gray-900 font-medium">
                                            {log.user ? log.user.name : <span className="text-gray-600 italic">System</span>}
                                        </td>
                                        <td className="px-6 py-3">
                                            <span className={`inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider border ${getActionColor(log.action)}`}>
                                                {log.action}
                                            </span>
                                        </td>
                                        <td className="px-6 py-3">
                                            <div className="flex items-center gap-2">
                                                <Activity size={14} className="text-gray-500" />
                                                <span className="text-sm font-semibold text-gray-800">{formatModelName(log.model_type)}</span>
                                                <span className="text-xs text-gray-500 font-mono bg-black/20 px-1.5 py-0.5 rounded border border-gray-200">
                                                    #{log.model_id}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="px-6 py-3 text-xs text-gray-500 font-mono">
                                            {log.ip_address || '-'}
                                        </td>
                                        <td className="px-6 py-3 text-center">
                                            <button
                                                onClick={() => setSelectedLog(log)}
                                                className="p-1.5 rounded-lg bg-gray-50 hover:bg-[#06B6D4]/20 text-gray-400 hover:text-amber-800 transition border border-transparent hover:border-[#06B6D4]/30"
                                                title="View JSON Diff"
                                            >
                                                <Eye size={16} />
                                            </button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Pagination */}
            {auditLogs.last_page > 1 && (
                <div className="flex justify-between items-center text-xs text-gray-700 font-mono">
                    <div>
                        Showing {auditLogs.from || 0} to {auditLogs.to || 0} of {auditLogs.total} logs
                    </div>
                    <div className="flex gap-1">
                        {auditLogs.links.map((link, idx) => (
                            <button
                                key={idx}
                                onClick={() => {
                                    if (link.url) router.get(link.url);
                                }}
                                disabled={!link.url}
                                className={`px-3 py-1.5 rounded-lg border transition ${
                                    link.active
                                        ? 'bg-[#2563EB]/20 border-[#2563EB]/50 text-[#2563EB] font-bold'
                                        : 'bg-gray-50 border-gray-200 text-gray-400 hover:bg-gray-500 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed'
                                }`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                </div>
            )}

            {/* Detail Modal */}
            {selectedLog && (
                <div className="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="glass-panel w-full max-w-4xl rounded-2xl overflow-hidden shadow-2xl animate-in fade-in zoom-in-95 duration-200 flex flex-col max-h-[85vh]">
                        <div className="p-5 border-b border-gray-200 flex items-center justify-between bg-black/20">
                            <div className="flex items-center gap-3">
                                <div className="w-8 h-8 rounded-lg bg-[#06B6D4]/10 border border-[#06B6D4]/20 flex items-center justify-center">
                                    <Activity size={16} className="text-amber-800" />
                                </div>
                                <div>
                                    <h3 className="font-bold text-gray-900 leading-tight">
                                        Audit Details: {formatModelName(selectedLog.model_type)} #{selectedLog.model_id}
                                    </h3>
                                    <span className="text-[10px] text-gray-700 font-mono uppercase tracking-wider">
                                        {selectedLog.action} by {selectedLog.user?.name || 'System'} at {formatDate(selectedLog.created_at)}
                                    </span>
                                </div>
                            </div>
                            <button onClick={() => setSelectedLog(null)} className="text-gray-500 hover:text-gray-900 transition p-1 bg-gray-50 rounded-lg hover:bg-gray-500">
                                <X size={18} />
                            </button>
                        </div>

                        <div className="flex-1 overflow-y-auto p-6">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                {/* Before Data */}
                                <div>
                                    <div className="flex items-center gap-2 mb-2">
                                        <div className="w-2 h-2 rounded-full bg-orange-400" />
                                        <h4 className="text-xs font-bold text-gray-800 uppercase tracking-wider">Before Change</h4>
                                    </div>
                                    <div className="bg-white border border-gray-200 rounded-xl p-4 h-full min-h-[300px]">
                                        {selectedLog.before_data ? (
                                            <pre className="text-xs font-mono text-gray-400 overflow-x-auto">
                                                {JSON.stringify(selectedLog.before_data, null, 2)}
                                            </pre>
                                        ) : (
                                            <div className="flex h-full items-center justify-center text-gray-600 text-xs font-mono italic">
                                                No previous state
                                            </div>
                                        )}
                                    </div>
                                </div>

                                {/* After Data */}
                                <div>
                                    <div className="flex items-center gap-2 mb-2">
                                        <div className="w-2 h-2 rounded-full bg-emerald-400" />
                                        <h4 className="text-xs font-bold text-gray-800 uppercase tracking-wider">After Change</h4>
                                    </div>
                                    <div className="bg-white border border-gray-200 rounded-xl p-4 h-full min-h-[300px]">
                                        {selectedLog.after_data ? (
                                            <pre className="text-xs font-mono text-emerald-800/80 overflow-x-auto">
                                                {JSON.stringify(selectedLog.after_data, null, 2)}
                                            </pre>
                                        ) : (
                                            <div className="flex h-full items-center justify-center text-gray-600 text-xs font-mono italic">
                                                No updated state
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}

        </AppLayout>
    );
}
