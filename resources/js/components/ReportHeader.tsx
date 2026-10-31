import React from 'react';
import AppLogo from './AppLogo';

interface ReportHeaderProps {
    title: string;
    subtitle?: string;
    period?: string;
    className?: string;
}

export const ReportHeader: React.FC<ReportHeaderProps> = ({
    title,
    subtitle,
    period,
    className = '',
}) => {
    return (
        <div className={`flex items-center justify-between pb-6 mb-6 border-b border-slate-200 print:pb-4 print:mb-4 print:border-slate-300 ${className}`}>
            <div className="flex items-center gap-4">
                <AppLogo
                    variant="horizontal"
                    imgClassName="h-12 w-auto object-contain print:h-10"
                    textClassName="text-xl font-extrabold text-slate-900 print:text-lg"
                />
            </div>
            <div className="text-right">
                <h2 className="text-xl font-bold text-slate-900 print:text-lg tracking-tight">{title}</h2>
                {subtitle && <p className="text-sm font-medium text-slate-600 print:text-xs">{subtitle}</p>}
                {period && <p className="text-xs font-semibold text-amber-700 print:text-slate-500 mt-0.5">Period: {period}</p>}
            </div>
        </div>
    );
};

export default ReportHeader;
