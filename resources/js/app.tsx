import './bootstrap';
import '../css/app.css';

import React from 'react';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

import { APP_NAME } from './brand';
import ErrorBoundary from './components/ErrorBoundary';

const appName = APP_NAME;

// 1. Vite Preload Error Handler for controlled recovery from stale/missing chunks
window.addEventListener('vite:preloadError', (event) => {
    console.warn('Vite preload error detected. Performing controlled page reload...', event);
    const reloadKey = 'vite_preload_reload_count';
    const count = parseInt(sessionStorage.getItem(reloadKey) || '0', 10);
    if (count < 2) {
        sessionStorage.setItem(reloadKey, (count + 1).toString());
        window.location.reload();
    }
});

// Clear reload count on successful window load
window.addEventListener('load', () => {
    sessionStorage.removeItem('vite_preload_reload_count');
});

// 2. Global Unhandled JS & Promise Error Logging
window.addEventListener('unhandledrejection', (event) => {
    if (event.reason?.message?.includes('Importing a module script failed') ||
        event.reason?.message?.includes('Failed to fetch dynamically imported module')) {
        console.warn('Unhandled promise rejection from missing module chunk:', event.reason);
        window.location.reload();
    }
});

createInertiaApp({
    title: (title) => title ? `${title} - ${appName}` : appName,
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.tsx');
        return resolvePageComponent(`./Pages/${name}.tsx`, pages).catch((err) => {
            console.error(`Failed to load page component ${name}:`, err);
            // Controlled auto-recovery for stale chunk loading
            if (err?.message?.includes('Failed to fetch') || err?.message?.includes('Importing a module')) {
                window.location.reload();
            }
            throw err;
        }) as any;
    },
    setup({ el, App, props }) {
        const root = createRoot(el);
        root.render(
            <ErrorBoundary>
                <App {...props} />
            </ErrorBoundary>
        );
    },
    progress: {
        color: '#D97706',
    },
});
