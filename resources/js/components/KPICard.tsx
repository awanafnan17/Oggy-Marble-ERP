import React from 'react';
import GlassCard from './GlassCard';
import { LucideIcon } from 'lucide-react';

interface KPICardProps {
    label: string;
    value: string | number;
    subLabel?: string;
    icon?: LucideIcon;
    glowColor?: 'blue' | 'cyan' | 'emerald' | 'red' | 'amber';
    trend?: {
        value: string;
        isPositive: boolean;
    };
    className?: string;
}

export default function KPICard({
    label,
    value,
    subLabel,
    icon: Icon,
    glowColor = 'amber',
    trend,
    className = ''
}: KPICardProps) {
    const bgIconColors = {
        blue: 'bg-blue-50 border-blue-200 text-blue-600',
        cyan: 'bg-cyan-50 border-cyan-200 text-cyan-600',
        emerald: 'bg-emerald-50 border-emerald-200 text-emerald-600',
        red: 'bg-red-50 border-red-200 text-red-600',
        amber: 'bg-amber-50 border-amber-200 text-amber-600',
    };

    return (
        <GlassCard hoverGlow glowColor={glowColor} className={className}>
            <div className="flex items-center justify-between mb-4">
                <span className="text-sm text-gray-600 font-medium">{label}</span>
                {Icon && (
                    <div className={`p-2.5 rounded-xl border ${bgIconColors[glowColor]}`}>
                        <Icon size={18} />
                    </div>
                )}
            </div>

            <h2 className="text-2xl font-bold tracking-tight text-gray-900 mb-1">
                {value}
            </h2>

            <div className="flex items-center gap-2">
                {trend && (
                    <span className={`
                        text-xs font-mono font-medium px-2 py-0.5 rounded
                        ${trend.isPositive ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'}
                    `}>
                        {trend.value}
                    </span>
                )}
                {subLabel && (
                    <p className="text-xs text-gray-500 font-mono">
                        {subLabel}
                    </p>
                )}
            </div>
        </GlassCard>
    );
}
