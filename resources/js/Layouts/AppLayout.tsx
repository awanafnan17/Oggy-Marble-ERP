import React, { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { APP_NAME } from '../brand';
import AppLogo from '../components/AppLogo';
import {
    LayoutDashboard,
    Package,
    CircleDollarSign,
    ShoppingCart,
    Wrench,
    FileSpreadsheet,
    Menu,
    X,
    User,
    Compass,
    Database,
    ShieldAlert,
    ShieldCheck,
    Truck,
    LogOut,
    KeyRound,
    Building2
} from 'lucide-react';

interface AppLayoutProps {
    children: React.ReactNode;
    title?: string;
}

export default function AppLayout({ children, title }: AppLayoutProps) {
    const { url } = usePage();
    const { auth } = usePage().props as any;
    const [sidebarOpen, setSidebarOpen] = useState(false);

    const userPermissions = auth?.user?.permissions || [];
    const userRoles = auth?.user?.roles || [];
    const isSystemAdmin = userRoles.includes('admin');
    const isOwner = userRoles.includes('owner');

    const isCashBankCurrent = url.startsWith('/cash-bank') && !url.startsWith('/cash-bank/reconciliation');

    // Navigation order:
    // Pinned Quick Access: Cash & Bank (ABOVE Dashboard)
    // Normal menu (Positions 1-14):
    // 1. Dashboard
    // 2. Sales Orders
    // 3. Purchases
    // 4. Inventory
    // 5. Production & Operations
    // 6. Party Ledgers
    // 7. Accounting Ledger
    // 8. Reports Center
    // 9. Daily Reports
    // 10. Delivery Challans
    // 11. Quality Control
    // 12. Machine Rental
    // 13. Period Governance
    // 14. Audit Logs
    // LAST: Password & Security
    const navigation = [
        { name: 'Dashboard', href: '/dashboard', icon: LayoutDashboard, current: url.startsWith('/dashboard'), permission: 'view_dashboard' },
        { name: 'Sales Orders', href: '/sales', icon: CircleDollarSign, current: url.startsWith('/sales') && !url.startsWith('/delivery-challans'), permission: 'view_sales' },
        { name: 'Purchases', href: '/purchases', icon: ShoppingCart, current: url.startsWith('/purchases'), permission: 'view_purchases' },
        { name: 'Inventory', href: '/inventory', icon: Package, current: url.startsWith('/inventory') && !url.startsWith('/inventory/transfers'), permission: 'view_inventory' },
        { name: 'Production & Operations', href: '/production', icon: Wrench, current: url === '/production' || url.startsWith('/production/stone-to-slab') || url.startsWith('/production/slab-to-tile') || url.startsWith('/production/variance'), permission: 'view_production' },
        { name: 'Party Ledgers', href: '/party-ledgers', icon: User, current: url.startsWith('/party-ledgers'), permission: 'view_accounting' },
        { name: 'Accounting Ledger', href: '/accounting', icon: FileSpreadsheet, current: url === '/accounting', permission: 'view_accounting' },
        { name: 'Reports Center', href: '/reports', icon: FileSpreadsheet, current: url === '/reports', permission: 'view_reports' },
        { name: 'Daily Reports', href: '/reports/daily', icon: FileSpreadsheet, current: url.startsWith('/reports/daily'), permission: 'view_reports' },
        { name: 'Delivery Challans', href: '/delivery-challans', icon: Truck, current: url.startsWith('/delivery-challans'), permission: 'view_dispatch' },
        { name: 'Quality Control', href: '/quality-inspections', icon: ShieldCheck, current: url.startsWith('/quality-inspections'), permission: 'view_quality' },
        { name: 'Machine Rental', href: '/rental-jobs', icon: Compass, current: url.startsWith('/rental-jobs'), permission: 'view_production' },
        { name: 'Period Governance', href: '/accounting/periods', icon: ShieldAlert, current: url.startsWith('/accounting/periods'), permission: 'view_accounting' },
        { name: 'Audit Logs', href: '/administration/audit-logs', icon: LayoutDashboard, current: url.startsWith('/administration/audit-logs'), permission: 'view_audit_logs' },
        { name: 'Password & Security', href: '/profile/password', icon: KeyRound, current: url.startsWith('/profile/password') || url.startsWith('/administration/users') },
    ];

    const filteredNavigation = navigation.filter(item => {
        if (isSystemAdmin) return true;
        if (!item.permission) return true;
        return userPermissions.includes(item.permission);
    });

    const hasCashBankPermission = isSystemAdmin || userPermissions.includes('view_accounting');

    return (
        <div className="min-h-screen bg-slate-50 text-slate-900 flex flex-col font-sans selection:bg-amber-500/20 selection:text-amber-900">
            <div className="flex flex-1 relative">
                {/* Mobile sidebar toggle button */}
                <div className="absolute top-4 left-4 z-40 md:hidden">
                    <button
                        onClick={() => setSidebarOpen(!sidebarOpen)}
                        className="p-2.5 rounded-lg bg-white border border-slate-200 shadow-sm hover:bg-slate-50 transition duration-200"
                    >
                        {sidebarOpen ? <X size={22} className="text-amber-600" /> : <Menu size={22} className="text-slate-700" />}
                    </button>
                </div>

                {/* Sidebar Navigation - Bright White Theme */}
                <aside className={`
                    fixed md:static inset-y-0 left-0 z-30 w-72 bg-white border-r border-slate-200 shadow-sm
                    transition-transform duration-300 ease-in-out flex flex-col justify-between
                    ${sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'}
                `}>
                    <div>
                        {/* Brand Logo & Header */}
                        <div className="h-20 flex items-center px-6 border-b border-slate-200 bg-white">
                            <Link href="/dashboard" className="flex items-center group">
                                <AppLogo
                                    variant="horizontal"
                                    imgClassName="h-10 w-auto object-contain group-hover:scale-105 transition-transform duration-300"
                                    textClassName="font-extrabold text-lg tracking-tight text-slate-900"
                                />
                            </Link>
                        </div>

                        {/* PINNED QUICK ACCESS SECTION: Cash & Bank ABOVE Dashboard */}
                        {hasCashBankPermission && (
                            <div className="p-3 pb-0">
                                <div className="text-[11px] font-bold text-amber-800 uppercase tracking-wider px-3 mb-1">
                                    Quick Access
                                </div>
                                <Link
                                    href="/cash-bank"
                                    className={`
                                        flex items-center gap-3.5 px-4 py-3 rounded-lg border transition-all duration-200 text-[16px] font-bold
                                        ${isCashBankCurrent
                                            ? 'bg-[#FDE68A] border-[#FCD34D] text-[#78350F] shadow-sm'
                                            : 'bg-[#FFFBEB] border-[#FCD34D] text-[#92400E] hover:bg-[#FEF3C7]'
                                        }
                                    `}
                                >
                                    <Building2 size={20} className="text-[#D97706]" />
                                    <span>Cash & Bank</span>
                                </Link>
                                <div className="my-3 border-b border-slate-200" />
                            </div>
                        )}

                        {/* Normal Navigation Menu */}
                        <nav className="px-3 space-y-1 max-h-[calc(100vh-20rem)] overflow-y-auto custom-scrollbar">
                            {filteredNavigation.map((item) => (
                                <Link
                                    key={item.name}
                                    href={item.href}
                                    className={`
                                        flex items-center gap-3.5 px-4 py-2.5 rounded-lg transition-all duration-200 text-[15.5px] font-medium
                                        ${item.current
                                            ? 'bg-amber-50 text-amber-900 font-semibold border-l-4 border-amber-600 shadow-xs'
                                            : 'text-slate-700 hover:text-slate-900 hover:bg-slate-100 border-l-4 border-transparent'
                                        }
                                    `}
                                >
                                    <item.icon size={19} className={item.current ? 'text-amber-600' : 'text-slate-500'} />
                                    <span>{item.name}</span>
                                </Link>
                            ))}
                        </nav>
                    </div>

                    {/* User Profile & Logout Box */}
                    <div className="p-5 border-t border-slate-200 bg-slate-50 flex flex-col gap-3">
                        <div className="flex items-center gap-3">
                            <div className="w-10 h-10 rounded-full bg-amber-100 border border-amber-200 flex items-center justify-center">
                                <User size={20} className="text-amber-700" />
                            </div>
                            <div className="flex-1 min-w-0">
                                <h4 className="text-[15px] font-bold text-slate-900 truncate">{auth?.user?.name || 'ERP User'}</h4>
                                <div className="flex items-center gap-1.5 mt-0.5">
                                    <div className="w-2 h-2 rounded-full bg-emerald-500" />
                                    <span className="text-[12px] text-slate-600 font-semibold uppercase truncate">
                                        {userRoles[0] ? userRoles[0].replace(/_/g, ' ') : 'Admin'}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            className="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg border border-red-200 bg-red-50 hover:bg-red-100 text-red-700 text-[14.5px] font-semibold transition-all duration-200 cursor-pointer"
                        >
                            <LogOut size={16} />
                            <span>Logout</span>
                        </Link>
                    </div>
                </aside>

                {/* Main Content Area - Bright White Workspace */}
                <main className="flex-1 min-w-0 flex flex-col bg-slate-50">
                    {/* Header */}
                    <header className="h-20 border-b border-slate-200 px-8 pl-16 md:pl-8 flex items-center justify-between bg-white sticky top-0 z-20 shadow-xs">
                        <div>
                            {title && (
                                <h1 className="text-2xl font-bold tracking-tight text-slate-900">
                                    {title}
                                </h1>
                            )}
                        </div>

                        <div className="flex items-center gap-4">
                            <div className="flex items-center gap-2 px-3.5 py-1.5 rounded-lg border border-amber-200 bg-amber-50">
                                <Database size={15} className="text-amber-600" />
                                <span className="text-[13px] font-bold text-amber-900">
                                    {APP_NAME}
                                </span>
                            </div>
                        </div>
                    </header>

                    {/* Page Content Container */}
                    <div className="flex-1 p-6 md:p-8 overflow-y-auto text-[16px] text-slate-800">
                        {children}
                    </div>
                </main>
            </div>
        </div>
    );
}
