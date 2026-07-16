import { updateActiveMenu } from './components/layout.js';

export function initRouter() {
    // Intercept clicks globally on links
    document.addEventListener('click', (e) => {
        const anchor = e.target.closest('a');
        if (!anchor) return;

        const href = anchor.getAttribute('href');

        // Ignore empty, anchor hashes, javascript links, or external links
        if (!href || href === '#' || href.startsWith('javascript:') || href.startsWith('http') || href.startsWith('//')) {
            return;
        }

        // Only handle HTML navigation inside app
        if (href.endsWith('.html') || href.startsWith('/admin/') || href.startsWith('/staff/')) {
            e.preventDefault();
            navigateTo(href);
        }
    });

    // Handle browser back / forward buttons
    window.addEventListener('popstate', () => {
        loadPage(window.location.href, false);
    });
}

export async function navigateTo(url) {
    if (window.location.href === new URL(url, window.location.origin).href) {
        return; // Already on this page
    }

    await loadPage(url, true);
}

if (typeof window !== 'undefined') {
    window.navigateTo = navigateTo;
}


async function loadPage(url, pushState = true) {
    try {
        const mainContent = document.querySelector('main.main-content');
        if (mainContent) mainContent.style.opacity = '0.5';

        const response = await fetch(url);
        if (!response.ok) throw new Error(`Failed to load page: ${response.statusText}`);

        const htmlText = await response.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(htmlText, 'text/html');

        // 1. Update Title
        if (doc.title) {
            document.title = doc.title;
            const mobileHeaderTitle = document.querySelector('header.mobile-header h2');
            if (mobileHeaderTitle) {
                mobileHeaderTitle.textContent = doc.title.split('-')[0].trim();
            }
        }

        // 2. Inject Page-Specific Styles into <head>
        syncHeadElements(doc);

        // 3. Replace Main Content
        const newMainContent = doc.querySelector('main.main-content');
        if (mainContent && newMainContent) {
            mainContent.innerHTML = newMainContent.innerHTML;
            mainContent.className = newMainContent.className;
            mainContent.style.opacity = '1';
        }

        // 4. Sync Page Modals, Drawers & Extra Body Elements
        syncBodyExtraElements(doc);

        // 5. Update URL bar
        if (pushState) {
            history.pushState({}, '', url);
        }

        // 6. Update Active Menu highlights
        const targetFilename = url.split('/').pop().split('?')[0] || 'index.html';
        updateActiveMenu(targetFilename);

        // 7. Re-trigger i18n translation if available
        if (window.i18n && typeof window.i18n.translatePage === 'function') {
            window.i18n.translatePage();
        }

        // 8. Execute Page Scripts in Clean Scope
        await executePageScripts(doc);

        if (window.i18n && typeof window.i18n.translatePage === 'function') {
            window.i18n.translatePage();
        }

        // Close mobile sidebar if open
        const sidebar = document.querySelector('aside.sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        if (sidebar && sidebarOverlay) {
            sidebar.classList.remove('mobile-open');
            sidebarOverlay.classList.remove('active');
        }

        // Scroll to top
        window.scrollTo(0, 0);

    } catch (err) {
        console.error('Router navigation error:', err);
        window.location.href = url; // Fallback to normal navigation
    }
}

function syncHeadElements(doc) {
    const newStyles = doc.querySelectorAll('head style, head link[rel="stylesheet"]');
    newStyles.forEach(styleEl => {
        if (styleEl.tagName.toLowerCase() === 'link') {
            const href = styleEl.getAttribute('href');
            if (href && !document.querySelector(`head link[href="${href}"]`)) {
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = href;
                document.head.appendChild(link);
            }
        } else if (styleEl.tagName.toLowerCase() === 'style') {
            const style = document.createElement('style');
            style.textContent = styleEl.textContent;
            style.setAttribute('data-dynamic-page-style', 'true');
            document.head.appendChild(style);
        }
    });
}

function syncBodyExtraElements(doc) {
    document.querySelectorAll('.dynamic-page-element').forEach(el => el.remove());

    const bodyChildren = doc.body.children;
    Array.from(bodyChildren).forEach(child => {
        const className = child.className || '';
        const id = child.id || '';

        if (id.includes('Modal') || id.includes('Drawer') || id.includes('toast') || id.includes('receipt') || id.includes('Print') || className.includes('modal') || className.includes('drawer') || className.includes('toast') || className.includes('receipt')) {
            if (id) {
                const existing = document.getElementById(id);
                if (existing) existing.remove();
            }
            const clone = child.cloneNode(true);
            clone.classList.add('dynamic-page-element');
            document.body.appendChild(clone);
        }
    });
}

async function executePageScripts(doc) {
    const scripts = doc.querySelectorAll('script');
    
    for (const script of scripts) {
        const src = script.getAttribute('src');
        if (src && (src.includes('layout.js') || src.includes('router.js') || src.includes('i18n.js') || src.includes('main.js'))) {
            continue;
        }

        if (src) {
            // Load external scripts
            const newScript = document.createElement('script');
            Array.from(script.attributes).forEach(attr => {
                newScript.setAttribute(attr.name, attr.value);
            });
            document.body.appendChild(newScript);
        } else if (script.innerHTML.trim()) {
            // Execute inline script wrapped in IIFE or Function to prevent global scope variable redeclaration SyntaxErrors!
            try {
                const code = script.innerHTML;
                // Auto-export top-level function declarations to window so inline onclick handlers work in SPA mode
                const fnNames = Array.from(code.matchAll(/(?:async\s+)?function\s+([a-zA-Z0-9_$]+)\s*\(/g), m => m[1]);
                const bindings = fnNames.map(name => `try { if (typeof ${name} !== 'undefined') window.${name} = ${name}; } catch(e){}`).join('\n');
                const wrappedCode = `(function(){\n${code}\n\n${bindings}\n})();`;
                const fn = new Function(wrappedCode);
                fn();
            } catch (err) {
                console.error('Error executing inline script:', err);
            }
        }
    }
    // Note: Inline scripts have executed above. We do NOT dispatch synthetic DOMContentLoaded or load events here
    // to prevent re-triggering init functions, double fetching data, and UI flickering.
}

