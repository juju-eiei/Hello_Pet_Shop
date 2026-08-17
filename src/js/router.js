import { updateActiveMenu } from './components/layout.js';

export function initRouter() {
    // Ensure active menu highlights match the current page location
    updateActiveMenu(window.location.pathname);
}

export function navigateTo(url) {
    if (!url) return;
    const targetUrl = new URL(url, window.location.origin).href;
    if (window.location.href === targetUrl) {
        window.location.reload();
        return;
    }
    window.location.href = url;
}

if (typeof window !== 'undefined') {
    window.navigateTo = navigateTo;
}
