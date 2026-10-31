import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { KeyRound, Lock, EyeOff, CheckCircle, AlertCircle } from 'lucide-react';

export default function MyPassword() {
    const { data, setData, post, processing, errors, reset, wasSuccessful } = useForm({
        current_password: '',
        new_password: '',
        new_password_confirmation: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/profile/password', {
            onSuccess: () => reset('current_password', 'new_password', 'new_password_confirmation'),
        });
    };

    return (
        <AppLayout title="Change Password">
            <Head title="Change Password" />

            <div className="max-w-xl mx-auto mt-6">
                <div className="bg-white shadow-sm border border-gray-200 shadow-2xl rounded-2xl p-6 sm:p-8  relative overflow-hidden">
                    {/* Glowing effect */}
                    <div className="absolute top-[-30%] right-[-20%] w-[60%] h-[60%] rounded-full bg-cyan-500/5 blur-[80px] pointer-events-none" />

                    <div className="flex items-center gap-3 mb-6 pb-4 border-b border-gray-200">
                        <div className="w-10 h-10 rounded-xl bg-cyan-500/10 border border-cyan-500/25 flex items-center justify-center text-amber-800">
                            <KeyRound size={20} />
                        </div>
                        <div>
                            <h2 className="text-lg font-bold text-gray-900 tracking-wide">Change Your Password</h2>
                            <p className="text-xs text-gray-700">Update your account credentials to keep your account secure.</p>
                        </div>
                    </div>

                    {wasSuccessful && (
                        <div className="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-800 text-sm flex items-center gap-3">
                            <CheckCircle size={16} />
                            <span>Your password has been changed successfully.</span>
                        </div>
                    )}

                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Current Password */}
                        <div>
                            <label className="block text-xs font-semibold uppercase text-gray-700 tracking-wider mb-2 flex items-center gap-1.5">
                                <Lock size={12} className="text-gray-500" />
                                Current Password
                            </label>
                            <input
                                type="password"
                                required
                                value={data.current_password}
                                onChange={(e) => setData('current_password', e.target.value)}
                                className="w-full bg-white border border-gray-300 rounded-xl px-4 py-3 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none focus:ring-1 focus:ring-[#06B6D4] transition duration-300 placeholder-gray-500"
                                placeholder="Enter current password"
                            />
                            {errors.current_password && (
                                <p className="text-xs text-red-400 mt-1.5 flex items-center gap-1">
                                    <AlertCircle size={12} />
                                    {errors.current_password}
                                </p>
                            )}
                        </div>

                        {/* New Password */}
                        <div>
                            <label className="block text-xs font-semibold uppercase text-gray-700 tracking-wider mb-2 flex items-center gap-1.5">
                                <Lock size={12} className="text-amber-800" />
                                New Password
                            </label>
                            <input
                                type="password"
                                required
                                value={data.new_password}
                                onChange={(e) => setData('new_password', e.target.value)}
                                className="w-full bg-white border border-gray-300 rounded-xl px-4 py-3 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none focus:ring-1 focus:ring-[#06B6D4] transition duration-300 placeholder-gray-500"
                                placeholder="Min 12 characters recommended"
                            />
                            {errors.new_password && (
                                <p className="text-xs text-red-400 mt-1.5 flex items-center gap-1">
                                    <AlertCircle size={12} />
                                    {errors.new_password}
                                </p>
                            )}
                        </div>

                        {/* Confirm New Password */}
                        <div>
                            <label className="block text-xs font-semibold uppercase text-gray-700 tracking-wider mb-2 flex items-center gap-1.5">
                                <Lock size={12} className="text-amber-800" />
                                Confirm New Password
                            </label>
                            <input
                                type="password"
                                required
                                value={data.new_password_confirmation}
                                onChange={(e) => setData('new_password_confirmation', e.target.value)}
                                className="w-full bg-white border border-gray-300 rounded-xl px-4 py-3 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none focus:ring-1 focus:ring-[#06B6D4] transition duration-300 placeholder-gray-500"
                                placeholder="Confirm new password"
                            />
                            {errors.new_password_confirmation && (
                                <p className="text-xs text-red-400 mt-1.5 flex items-center gap-1">
                                    <AlertCircle size={12} />
                                    {errors.new_password_confirmation}
                                </p>
                            )}
                        </div>

                        {/* Submit Button */}
                        <div className="pt-2">
                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full flex items-center justify-center gap-2 py-3 px-4 bg-gradient-to-r from-blue-600 to-cyan-500 hover:from-blue-700 hover:to-cyan-600 text-white rounded-xl font-semibold shadow-[0_0_15px_rgba(6,182,212,0.2)] hover:shadow-[0_0_20px_rgba(6,182,212,0.3)] transition duration-300 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {processing ? 'Updating Password...' : 'Save Password'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
