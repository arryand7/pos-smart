export function registerServiceWorker() {
    if (! ('serviceWorker' in navigator)) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker
            .register('/sw.js')
            .then((registration) => {
                console.info('SMART POS: Service worker registered', registration.scope);
            })
            .catch((error) => {
                console.warn('SMART POS: Service worker registration failed', error);
            });
    });
}

registerServiceWorker();
