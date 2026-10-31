import React from 'react';

interface AppLogoProps {
    variant?: 'full' | 'mark' | 'horizontal';
    className?: string;
    imgClassName?: string;
    showText?: boolean;
    textClassName?: string;
}

export const AppLogo: React.FC<AppLogoProps> = ({
    variant = 'horizontal',
    className = '',
    imgClassName = 'h-10 w-10',
    showText = true,
    textClassName = 'text-slate-900 font-bold tracking-tight text-lg',
}) => {
    const markOnly = variant === 'mark';

    return (
        <div className={`flex items-center gap-3 ${className}`}>
            <span
                aria-hidden="true"
                className={`${imgClassName} inline-flex min-h-10 min-w-10 items-center justify-center rounded-xl bg-amber-500 px-2 font-black tracking-tight text-slate-950 shadow-sm`}
            >
                OM
            </span>
            {showText && !markOnly && (
                <div className="flex flex-col leading-tight">
                    <span className={textClassName}>Oggy Marble</span>
                    <span className="text-[11px] font-semibold tracking-wider text-amber-700 uppercase">
                        ERP System
                    </span>
                </div>
            )}
        </div>
    );
};

export default AppLogo;
