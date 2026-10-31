import React, { useEffect } from 'react';
import { X } from 'lucide-react';

interface SlideModalProps {
    isOpen: boolean;
    onClose: () => void;
    title: string;
    children: React.ReactNode;
    widthClass?: string;
}

export default function SlideModal({
    isOpen,
    onClose,
    title,
    children,
    widthClass = 'max-w-md'
}: SlideModalProps) {
    useEffect(() => {
        if (isOpen) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
        return () => {
            document.body.style.overflow = '';
        };
    }, [isOpen]);

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 overflow-hidden">
            <div
                className="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity duration-300"
                onClick={onClose}
            />

            <div className="fixed inset-y-0 right-0 pl-4 sm:pl-10 max-w-full flex">
                <div className={`
                    w-screen ${widthClass} max-w-full bg-white border-l border-[#E5E7EB]
                    shadow-2xl flex flex-col
                    transform transition-transform duration-300 ease-in-out translate-x-0
                `}>
                    {/* Header */}
                    <div className="p-6 border-b border-[#E5E7EB] flex items-center justify-between">
                        <h3 className="text-lg font-bold text-gray-900 tracking-wide">
                            {title}
                        </h3>
                        <button
                            onClick={onClose}
                            className="p-1.5 rounded-lg border border-gray-200 bg-gray-50 text-gray-500 hover:text-gray-900 hover:border-gray-300 transition duration-200"
                        >
                            <X size={18} />
                        </button>
                    </div>

                    {/* Scrollable Body */}
                    <div className="flex-1 overflow-y-auto p-6 space-y-6">
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}
