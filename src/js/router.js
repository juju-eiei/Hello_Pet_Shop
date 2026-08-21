import { updateActiveMenu } from './components/layout.js';
import { getRouteInfo, navigateTo as spaNavigateTo, updateActiveNavLinks } from './spa.js';

export function initRouter() {
    const currentPath = window.location.pathname;
    const info = getRouteInfo(currentPath);

    if (info && (info.category === 'admin' || info.category === 'staff')) {
        updateActiveMenu(currentPath);
    } else if (info && info.category === 'customer') {
        updateActiveNavLinks(currentPath);
    }
}

export function navigateTo(url) {
    if (!url) return;
    spaNavigateTo(url, true);
}

if (typeof window !== 'undefined') {
    window.navigateTo = navigateTo;
}
