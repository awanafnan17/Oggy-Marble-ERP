import React, { Component, ErrorInfo, ReactNode } from 'react';
import AppLogo from './AppLogo';
import { RefreshCw, LayoutDashboard, AlertTriangle } from 'lucide-react';

interface Props {
    children: ReactNode;
}

interface State {
    hasError: boolean;
    error: Error | null;
    errorInfo: ErrorInfo | null;
}

export class ErrorBoundary extends Component<Props, State> {
    public state: State = {
        hasError: false,
        error: null,
        errorInfo: null,
    };

    public static getDerivedStateFromError(error: Error): State {
        return { hasError: true, error, errorInfo: null };
    }

    public componentDidCatch(error: Error, errorInfo: ErrorInfo) {
        const errorData = {
            timestamp: new Date().toISOString(),
            message: error.message,
            name: error.name,
            stack: error.stack,
            componentStack: errorInfo.componentStack,
            url: window.location.href
        };

        console.error('[REACT ERROR BOUNDARY DETECTED CRASH]', errorData);

        // Attach to global window object and sessionStorage for automated browser test extraction
        (window as any).__LAST_REACT_ERROR__ = errorData;
        try {
            sessionStorage.setItem('last_react_error', JSON.stringify(errorData));
        } catch (e) {
            // Ignore storage errors
        }

        this.setState({ error, errorInfo });
    }

    private handleReload = () => {
        window.location.reload();
    };

    private handleGoToLogin = () => {
        window.location.href = '/login';
    };

    private handleSignOut = () => {
        // Send a post request or navigate to login
        window.location.href = '/login';
    };

    public render() {
        if (this.state.hasError) {
            const isChunkError = this.state.error?.message?.includes('Importing a module script failed') ||
                                 this.state.error?.message?.includes('Failed to fetch dynamically imported module') ||
                                 this.state.error?.name === 'ChunkLoadError';

            return (
                <div className="min-h-screen bg-slate-50 text-slate-900 flex items-center justify-center p-6 font-sans">
                    <div className="w-full max-w-lg bg-white border border-slate-200 shadow-xl rounded-2xl p-8 text-center space-y-6">
                        <div className="inline-flex items-center justify-center p-4 bg-amber-50 rounded-2xl border border-amber-200">
                            <AppLogo variant="horizontal" imgClassName="h-10 w-10" />
                        </div>

                        <div className="space-y-2">
                            <div className="inline-flex items-center gap-2 text-amber-700 bg-amber-100/80 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider">
                                <AlertTriangle size={14} />
                                System Recovery
                            </div>
                            <h2 className="text-2xl font-bold text-slate-900">
                                {isChunkError ? 'Application Updated' : 'Something went wrong while loading this page'}
                            </h2>
                            <p className="text-sm text-slate-600">
                                {isChunkError
                                    ? 'A new system update was deployed. Please refresh to load the latest application assets.'
                                    : 'An unexpected display error occurred. You can safely reload the page or return to login.'}
                            </p>
                        </div>

                        <div className="pt-2 flex flex-col sm:flex-row items-center justify-center gap-3">
                            <button
                                onClick={this.handleReload}
                                className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm transition shadow-sm cursor-pointer"
                            >
                                <RefreshCw size={16} />
                                Reload Page
                            </button>
                            <button
                                onClick={this.handleGoToLogin}
                                className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-sm transition cursor-pointer"
                            >
                                <LayoutDashboard size={16} />
                                Go to Login
                            </button>
                        </div>
                    </div>
                </div>
            );
        }

        return this.props.children;
    }
}

export default ErrorBoundary;
