import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { route } from 'ziggy-js';
import { renderApp } from '@inertiaui/modal-react';
import { ToastProvider } from './components/ui/toast-provider';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.jsx`, import.meta.glob('./Pages/**/*.jsx')),
    setup({ el, App, props }) {
        window.route = route;
        const root = createRoot(el);

        root.render(
            <ToastProvider>
                {renderApp(App, props)}
            </ToastProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});
