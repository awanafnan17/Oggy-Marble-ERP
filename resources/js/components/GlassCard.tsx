import React from 'react';

interface GlassCardProps extends React.HTMLAttributes<HTMLDivElement> {
    children: React.ReactNode;
    hoverGlow?: boolean;
    glowColor?: 'blue' | 'cyan' | 'emerald' | 'red' | 'amber';
}

export default function GlassCard({
    children,
    className = '',
    hoverGlow = false,
    glowColor = 'amber',
    ...props
}: GlassCardProps) {
    const glowClasses = {
        blue: 'hover:border-blue-300 hover:shadow-md',
        cyan: 'hover:border-cyan-300 hover:shadow-md',
        emerald: 'hover:border-emerald-300 hover:shadow-md',
        red: 'hover:border-red-300 hover:shadow-md',
        amber: 'hover:border-amber-300 hover:shadow-md',
    };

    return (
        <div
            className={`
                bg-white border border-[#E5E7EB] shadow-sm
                rounded-2xl p-6 relative overflow-hidden group
                transition duration-200 ease-out
                ${hoverGlow ? glowClasses[glowColor] : ''}
                ${className}
            `}
            {...props}
        >
            {children}
        </div>
    );
}
