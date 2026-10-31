import React, { FormEvent, useState } from 'react';
import { APP_NAME, COMPANY_NAME } from '../../brand';
import { Head, router } from '@inertiajs/react';
import { Lock, Mail } from 'lucide-react';
import AppLogo from '@/components/AppLogo';

export default function Login({ errors }: { errors: any }) {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        router.post('/login', { email, password });
    };

    return (
        <div className="min-h-screen flex items-center justify-center bg-slate-50 text-slate-900 py-12 px-4 sm:px-6 lg:px-8 font-sans">
            <Head title={`Log In - ${APP_NAME}`} />

            <div className="w-full max-w-md space-y-6">
                {/* Header with Dynamic Brand Logo */}
                <div className="text-center space-y-3">
                    <div className="inline-flex items-center justify-center p-4 bg-white rounded-2xl border border-slate-200 shadow-sm mb-2">
                        <AppLogo
                            variant="mark"
                            imgClassName="h-20 w-20 text-xl"
                            showText={false}
                        />
                    </div>
                    <h1 className="text-3xl font-extrabold text-slate-900 tracking-tight">
                        {APP_NAME}
                    </h1>
                    <p className="text-[16px] text-slate-600 font-medium">Enterprise Management Portal</p>
                </div>

                {/* Bright White Card */}
                <div className="bg-white border border-slate-200 shadow-md rounded-2xl p-8">
                    <form onSubmit={handleSubmit} className="space-y-5">
                        {errors?.email && (
                            <div className="bg-red-50 border border-red-200 text-red-700 p-3.5 rounded-xl text-[15px] font-semibold text-center">
                                {errors.email}
                            </div>
                        )}

                        <div className="space-y-1.5">
                            <label className="text-[15.5px] font-semibold text-slate-700 ml-1">Email Address</label>
                            <div className="relative">
                                <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                    <Mail className="h-5 w-5 text-slate-400" />
                                </div>
                                <input
                                    type="email"
                                    name="email"
                                    required
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                    className="block w-full pl-11 pr-4 py-3 bg-white border border-slate-300 rounded-xl text-slate-900 text-[16px] placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition duration-200"
                                    placeholder="admin@example.test"
                                />
                            </div>
                        </div>

                        <div className="space-y-1.5">
                            <label className="text-[15.5px] font-semibold text-slate-700 ml-1">Password</label>
                            <div className="relative">
                                <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                    <Lock className="h-5 w-5 text-slate-400" />
                                </div>
                                <input
                                    type="password"
                                    name="password"
                                    required
                                    value={password}
                                    onChange={(e) => setPassword(e.target.value)}
                                    className="block w-full pl-11 pr-4 py-3 bg-white border border-slate-300 rounded-xl text-slate-900 text-[16px] placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition duration-200"
                                    placeholder="••••••••"
                                />
                            </div>
                        </div>

                        <div className="flex items-center justify-between pt-1">
                            <div className="flex items-center">
                                <input id="remember-me" type="checkbox" className="h-4.5 w-4.5 text-amber-600 focus:ring-amber-500 border-slate-300 rounded" />
                                <label htmlFor="remember-me" className="ml-2.5 block text-[15px] text-slate-600 font-medium">
                                    Remember login
                                </label>
                            </div>
                        </div>

                        <button
                            type="submit"
                            className="w-full flex justify-center py-3.5 px-4 border border-transparent rounded-xl shadow-sm text-[16px] font-bold text-gray-900 bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 transition duration-200 cursor-pointer"
                        >
                            Sign In to {APP_NAME}
                        </button>
                    </form>
                </div>

                <div className="text-center pt-2">
                    <p className="text-[14px] text-slate-500 font-medium">
                        &copy; 2026 {COMPANY_NAME}. All rights reserved.
                    </p>
                </div>
            </div>
        </div>
    );
}
