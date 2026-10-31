import React from 'react';

interface StatusBadgeProps {
    status: string;
    typeMap?: Record<string, string>;
    className?: string;
}

export default function StatusBadge({ status, className = '' }: StatusBadgeProps) {
    const s = status.toLowerCase();

    // Setup color mapping
    let bgGlowClass = 'bg-gray-500/10 border-gray-500/20 text-gray-400';

    if (s === 'paid' || s === 'completed' || s === 'deposit' || s === 'active') {
        bgGlowClass = 'bg-[#10B981]/10 border-[#10B981]/20 text-[#10B981] shadow-[0_0_10px_rgba(16,185,129,0.05)]';
    } else if (s === 'partial' || s === 'pending' || s === 'transfer') {
        bgGlowClass = 'bg-amber-400/10 border-amber-400/20 text-amber-400 shadow-[0_0_10px_rgba(251,191,36,0.05)]';
    } else if (s === 'unpaid' || s === 'withdrawal' || s === 'inactive' || s === 'failed') {
        bgGlowClass = 'bg-red-500/10 border-red-500/20 text-red-400 shadow-[0_0_10px_rgba(239,68,68,0.05)]';
    }

    return (
        <span className={`
            inline-flex items-center px-2.5 py-0.5 rounded-full border text-[10px]
            uppercase font-semibold tracking-wider font-mono ${bgGlowClass} ${className}
        `}>
            {status}
        </span>
    );
}
