import React, { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import SlideModal from '../../components/SlideModal';
import { UserCheck, ShieldAlert, Check, X, Shield, Lock, AlertTriangle, Key } from 'lucide-react';

interface UserData {
    id: number;
    name: string;
    email: string;
    status: string;
    roles: string[];
}

interface UserManagementProps {
    users: UserData[];
    errors: {
        status?: string;
    };
}

export default function UserManagement({ users, errors: parentErrors }: UserManagementProps) {
    const [selectedUser, setSelectedUser] = useState<UserData | null>(null);
    const [isResetOpen, setIsResetOpen] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
        password_confirmation: '',
    });

    const handleToggleStatus = (id: number) => {
        router.post(`/administration/users/${id}/toggle-status`, {}, {
            preserveScroll: true,
        });
    };

    const handleOpenResetModal = (user: UserData) => {
        setSelectedUser(user);
        setIsResetOpen(true);
        reset();
    };

    const handleResetSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedUser) return;

        post(`/administration/users/${selectedUser.id}/reset-password`, {
            onSuccess: () => {
                setIsResetOpen(false);
                setSelectedUser(null);
                reset();
            },
        });
    };

    return (
        <AppLayout title="User Security & Access Controls">
            <Head title="User Management" />

            <div className="max-w-6xl mx-auto mt-6 space-y-6">
                {/* Security warning if present */}
                {parentErrors?.status && (
                    <div className="p-4 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-800 text-sm flex items-center gap-3 shadow-md">
                        <AlertTriangle size={18} className="flex-shrink-0 animate-pulse" />
                        <span>{parentErrors.status}</span>
                    </div>
                )}

                {/* Users List Card */}
                <div className="bg-white shadow-sm border border-gray-200 shadow-2xl rounded-2xl p-6  relative overflow-hidden">
                    <div className="absolute top-[-30%] left-[-20%] w-[60%] h-[60%] rounded-full bg-blue-500/5 blur-[80px] pointer-events-none" />

                    <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-4 border-b border-gray-200 mb-6">
                        <div>
                            <h2 className="text-lg font-bold text-gray-900 tracking-wide">Manage ERP Users</h2>
                            <p className="text-xs text-gray-700">View access roles, update user status, or reset temporary seeded passwords.</p>
                        </div>
                    </div>

                    <div className="overflow-x-auto rounded-xl border border-gray-200">
                        <table className="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr className="bg-white/[0.02] border-b border-gray-200 text-gray-400 font-semibold uppercase tracking-wider font-mono">
                                    <th className="px-5 py-4">User Details</th>
                                    <th className="px-5 py-4">Assigned Roles</th>
                                    <th className="px-5 py-4">Status</th>
                                    <th className="px-5 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 font-mono text-gray-800">
                                {users.map((user) => (
                                    <tr key={user.id} className="hover:bg-white/[0.01] transition duration-200">
                                        <td className="px-5 py-4">
                                            <div className="flex flex-col">
                                                <span className="font-bold text-gray-900 text-sm">{user.name}</span>
                                                <span className="text-[10px] text-gray-700 mt-0.5">{user.email}</span>
                                            </div>
                                        </td>
                                        <td className="px-5 py-4">
                                            <div className="flex flex-wrap gap-1">
                                                {user.roles.map((r, idx) => (
                                                    <span
                                                        key={idx}
                                                        className="px-2.5 py-0.5 rounded-full bg-cyan-500/10 border border-cyan-500/20 text-amber-800 text-[10px] font-semibold uppercase"
                                                    >
                                                        {r}
                                                    </span>
                                                ))}
                                            </div>
                                        </td>
                                        <td className="px-5 py-4">
                                            {user.status === 'active' ? (
                                                <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-800 text-[10px] font-bold uppercase">
                                                    <Check size={10} /> Active
                                                </span>
                                            ) : (
                                                <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-red-500/10 border border-red-500/20 text-red-400 text-[10px] font-bold uppercase">
                                                    <X size={10} /> Inactive
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-5 py-4 text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                {/* Reset Password */}
                                                <button
                                                    onClick={() => handleOpenResetModal(user)}
                                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-50 hover:bg-cyan-500/10 border border-gray-200 hover:border-cyan-500/20 text-gray-800 hover:text-amber-800 rounded-lg text-[10px] font-semibold tracking-wider uppercase transition duration-200"
                                                >
                                                    <Key size={12} />
                                                    Reset Password
                                                </button>

                                                {/* Toggle Status */}
                                                <button
                                                    onClick={() => handleToggleStatus(user.id)}
                                                    className={`
                                                        inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[10px] font-semibold tracking-wider uppercase border transition duration-200
                                                        ${user.status === 'active'
                                                            ? 'bg-red-500/5 hover:bg-red-500/10 border-red-500/10 hover:border-red-500/20 text-red-400'
                                                            : 'bg-emerald-500/5 hover:bg-emerald-500/10 border-emerald-500/10 hover:border-emerald-500/20 text-emerald-800'
                                                        }
                                                    `}
                                                >
                                                    {user.status === 'active' ? (
                                                        <>
                                                            <X size={12} /> Disable
                                                        </>
                                                    ) : (
                                                        <>
                                                            <Check size={12} /> Enable
                                                        </>
                                                    )}
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* Reset password Modal */}
            <SlideModal
                isOpen={isResetOpen}
                onClose={() => { setIsResetOpen(false); setSelectedUser(null); }}
                title={`Reset Password for ${selectedUser?.name}`}
            >
                <div className="space-y-4">
                    <div className="p-4 rounded-xl bg-cyan-500/10 border border-cyan-500/20 text-amber-800 text-xs leading-relaxed">
                        <span className="font-bold uppercase tracking-wider block mb-1">Temporary Password Guideline</span>
                        Set a temporary password. The user will be required to update this password to a permanent one upon logging in.
                    </div>

                    <form onSubmit={handleResetSubmit} className="space-y-4">
                        {/* New Password */}
                        <div>
                            <label className="block text-xs font-semibold uppercase text-gray-700 tracking-wider mb-2">
                                New Password
                            </label>
                            <input
                                type="password"
                                required
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none focus:ring-1 focus:ring-[#06B6D4] transition"
                                placeholder="Min 12 characters"
                            />
                            {errors.password && (
                                <p className="text-xs text-red-400 mt-1.5">{errors.password}</p>
                            )}
                        </div>

                        {/* Confirm New Password */}
                        <div>
                            <label className="block text-xs font-semibold uppercase text-gray-700 tracking-wider mb-2">
                                Confirm New Password
                            </label>
                            <input
                                type="password"
                                required
                                value={data.password_confirmation}
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                                className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none focus:ring-1 focus:ring-[#06B6D4] transition"
                                placeholder="Confirm password"
                            />
                            {errors.password_confirmation && (
                                <p className="text-xs text-red-400 mt-1.5">{errors.password_confirmation}</p>
                            )}
                        </div>

                        <div className="pt-2">
                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full flex items-center justify-center gap-2 py-2.5 px-4 bg-gradient-to-r from-blue-600 to-cyan-500 hover:from-blue-700 hover:to-cyan-600 text-white rounded-xl font-semibold transition disabled:opacity-50"
                            >
                                {processing ? 'Resetting Password...' : 'Save Reset Password'}
                            </button>
                        </div>
                    </form>
                </div>
            </SlideModal>
        </AppLayout>
    );
}
