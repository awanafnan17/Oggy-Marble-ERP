import React from 'react';
import GlassCard from './GlassCard';

interface AgingSummary {
    current: number;
    thirty: number;
    sixty: number;
    ninety: number;
    total: number;
}

interface SummaryCardProps {
    openingBalance: number;
    currentBalance: number;
    agingSummary?: AgingSummary | null;
    currencySymbol?: string;
    isCustomer?: boolean;
}

export default function SummaryCard({
    openingBalance,
    currentBalance,
    agingSummary,
    currencySymbol = 'Rs.',
    isCustomer = true
}: SummaryCardProps) {
    const formatAmount = (val: number) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'PKR',
            currencyDisplay: 'code'
        }).format(val).replace('PKR', currencySymbol).trim();
    };

    const agingBuckets = agingSummary ? [
        { label: '0 - 30 Days (Current)', value: agingSummary.current, color: 'text-emerald-400' },
        { label: '31 - 60 Days', value: agingSummary.thirty, color: 'text-amber-400' },
        { label: '61 - 90 Days', value: agingSummary.sixty, color: 'text-orange-400' },
        { label: '90+ Days (Overdue)', value: agingSummary.ninety, color: 'text-red-400' },
    ] : [];

    return (
        <div className="space-y-6">
            {/* Balance Card */}
            <GlassCard hoverGlow glowColor="blue" className="p-6">
                <h3 className="text-sm font-semibold uppercase text-gray-400 mb-6 font-mono tracking-wider">
                    Balance Summary
                </h3>

                <div className="space-y-4">
                    <div className="flex justify-between items-center pb-3 border-b border-white/5">
                        <span className="text-sm text-gray-400">Opening Balance</span>
                        <span className="text-sm text-white font-mono font-medium">
                            {formatAmount(openingBalance)}
                        </span>
                    </div>

                    <div className="flex justify-between items-center pt-2">
                        <div>
                            <span className="text-sm text-gray-400">
                                {isCustomer ? 'Net Receivables' : 'Net Payables'}
                            </span>
                            <p className="text-[10px] text-gray-500 font-mono mt-0.5">Current outstanding book balance</p>
                        </div>
                        <span className={`text-xl font-bold font-mono ${currentBalance >= 0 ? 'text-[#06B6D4]' : 'text-red-400'}`}>
                            {formatAmount(currentBalance)}
                        </span>
                    </div>
                </div>
            </GlassCard>

            {/* Aging Report Buckets */}
            {agingSummary && (
                <GlassCard hoverGlow glowColor="cyan" className="p-6">
                    <h3 className="text-sm font-semibold uppercase text-gray-400 mb-6 font-mono tracking-wider">
                        Aging Analysis
                    </h3>

                    <div className="space-y-4 font-mono text-xs">
                        {agingBuckets.map((bucket, idx) => (
                            <div key={idx} className="flex justify-between items-center py-2 border-b border-white/2 last:border-b-0">
                                <span className="text-gray-400">{bucket.label}</span>
                                <span className={`font-bold ${bucket.color}`}>
                                    {formatAmount(bucket.value)}
                                </span>
                            </div>
                        ))}

                        <div className="flex justify-between items-center pt-4 border-t border-white/5 text-sm">
                            <span className="text-gray-300 font-bold">Total Arrears</span>
                            <span className="text-[#06B6D4] font-bold">
                                {formatAmount(agingSummary.total)}
                            </span>
                        </div>
                    </div>
                </GlassCard>
            )}
        </div>
    );
}
