export function registerServiceWorker() {
    if (! ('serviceWorker' in navigator)) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker
            .register('/sw.js')
            .then((registration) => {
                console.info('SMART POS: Service worker registered', registration.scope);
                registration.addEventListener('updatefound', () => {
                    const worker = registration.installing;
                    worker?.addEventListener('statechange', () => {
                        if (worker.state === 'installed' && navigator.serviceWorker.controller) {
                            window.dispatchEvent(new CustomEvent('smart:app-update'));
                        }
                    });
                });
            })
            .catch((error) => {
                console.warn('SMART POS: Service worker registration failed', error);
            });
    });
}

registerServiceWorker();
