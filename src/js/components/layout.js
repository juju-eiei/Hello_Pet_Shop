import { menuConfig } from '../config/menu.js';

export function initLayout() {
    const currentPath = window.location.pathname;
    const filename = currentPath.split('/').pop() || 'index.html';
    
    // Determine Role based on filename
    const isStaff = filename.startsWith('staff_');
    const role = isStaff ? 'staff' : 'admin';
    const items = menuConfig[role] || menuConfig.admin;

    renderSidebar(items, filename, role);
    renderMobileHeader(filename);
    renderBottomNav(items, filename);
    bindLayoutEvents();

    if (window.i18n && typeof window.i18n.applyTranslations === 'function') {
        window.i18n.applyTranslations();
    }
}

export function updateActiveMenu(currentFilename) {
    // Update active state for desktop sidebar
    document.querySelectorAll('.sidebar-menu .menu-item, .sidebar-menu .submenu a').forEach(el => {
        el.classList.remove('active');
        const href = el.getAttribute('href');
        if (href && href.endsWith(currentFilename)) {
            el.classList.add('active');
            // If inside submenu, open parent group
            const parentGroup = el.closest('.menu-group');
            if (parentGroup) {
                parentGroup.classList.add('open');
            }
        }
    });

    // Update active state for mobile bottom nav
    document.querySelectorAll('.bottom-nav .nav-item').forEach(el => {
        el.classList.remove('active');
        const href = el.getAttribute('href');
        if (href && href.endsWith(currentFilename)) {
            el.classList.add('active');
        }
    });

    if (window.i18n && typeof window.i18n.applyTranslations === 'function') {
        window.i18n.applyTranslations();
    }
}

function renderSidebar(items, currentFilename, role) {
    const sidebar = document.querySelector('aside.sidebar');
    if (!sidebar) return;

    const key = role === 'staff' ? 'staffProductsMenuOpen' : 'productsMenuOpen';
    const isGroupOpen = localStorage.getItem(key) === 'true';
    const currentLang = (window.i18n && window.i18n.getLanguage) ? window.i18n.getLanguage() : (localStorage.getItem('selected_lang') || 'th');
    const langBtnText = currentLang === 'th' ? '🌐 TH' : '🌐 EN';

    let menuHTML = `<div class="sidebar-header" style="display: flex; align-items: center; justify-content: space-between;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-paw"></i>
            <span>Hello Pet Shop</span>
        </div>
        <button id="langToggleBtnAdmin" class="lang-toggle-btn" style="background: rgba(255,255,255,0.2); border: none; color: #fff; padding: 4px 10px; border-radius: 20px; cursor: pointer; font-size: 12px; font-weight: 600;">${langBtnText}</button>
    </div>
    <nav class="sidebar-menu">
        <div style="padding: 10px 24px; font-size: 12px; color: #3b82f6; font-weight: 600; text-transform: uppercase;">${role}</div>`;

    items.forEach(item => {
        if (item.type === 'link') {
            const isActive = currentFilename === item.url ? 'active' : '';
            const desktopClass = item.desktopOnly ? 'desktop-only-menu' : '';
            menuHTML += `
                <a href="${item.url}" class="${desktopClass} menu-item ${isActive}">
                    <i class="${item.icon}"></i>
                    <span data-i18n="${item.i18n}">${item.title}</span>
                </a>`;
        } else if (item.type === 'group') {
            let groupHasActive = false;
            let subItemsHTML = '';

            item.items.forEach(sub => {
                const isActive = currentFilename === sub.url ? 'active' : '';
                if (isActive) groupHasActive = true;
                const desktopClass = sub.desktopOnly ? 'desktop-only-menu' : '';
                subItemsHTML += `<a href="${sub.url}" class="${desktopClass} ${isActive}" data-i18n="${sub.i18n}">${sub.title}</a>`;
            });

            const openClass = (isGroupOpen || groupHasActive) ? 'open' : '';

            menuHTML += `
                <div class="menu-group ${openClass}">
                    <a class="menu-item submenu-toggle">
                        <i class="${item.icon}"></i>
                        <span data-i18n="${item.i18n}">${item.title}</span>
                        <i class="fas fa-chevron-down menu-arrow"></i>
                    </a>
                    <div class="submenu">
                        ${subItemsHTML}
                    </div>
                </div>`;
        }
    });

    menuHTML += `
        <a href="#" class="menu-item logout-item" id="logoutBtn">
            <i class="fas fa-sign-out-alt"></i>
            <span data-i18n="nav.logout">ออกจากระบบ</span>
        </a>
    </nav>`;

    sidebar.innerHTML = menuHTML;
}

function renderMobileHeader(currentFilename) {
    const mobileHeader = document.querySelector('header.mobile-header');
    if (!mobileHeader) return;

    let titleText = document.title.split('-')[0].trim();
    const currentLang = (window.i18n && window.i18n.getLanguage) ? window.i18n.getLanguage() : (localStorage.getItem('selected_lang') || 'th');
    const langBtnText = currentLang === 'th' ? '🌐 TH' : '🌐 EN';

    mobileHeader.innerHTML = `
        <i class="fas fa-bars" id="hamburgerBtn"></i>
        <h2>${titleText}</h2>
        <div style="display: flex; align-items: center; gap: 10px; margin-left: auto;">
            <button id="langToggleBtnMobile" class="lang-toggle-btn" style="background: rgba(255,255,255,0.2); border: none; color: #fff; padding: 4px 10px; border-radius: 20px; cursor: pointer; font-size: 12px; font-weight: 600;">${langBtnText}</button>
            <i class="fas fa-bell"></i>
        </div>
    `;
}

function renderBottomNav(items, currentFilename) {
    const bottomNav = document.querySelector('nav.bottom-nav');
    if (!bottomNav) return;

    let navHTML = '';
    const mainLinks = [];

    items.forEach(item => {
        if (item.type === 'link' && !item.desktopOnly) {
            mainLinks.push(item);
        } else if (item.type === 'group') {
            item.items.forEach(sub => {
                if (!sub.desktopOnly) mainLinks.push({ ...sub, icon: item.icon });
            });
        }
    });

    mainLinks.slice(0, 4).forEach(link => {
        const isActive = currentFilename === link.url ? 'active' : '';
        navHTML += `
            <a href="${link.url}" class="nav-item ${isActive}">
                <i class="${link.icon || 'fas fa-circle'}"></i>
                <span data-i18n="${link.i18n}">${link.title}</span>
            </a>`;
    });

    bottomNav.innerHTML = navHTML;
}

function bindLayoutEvents() {
    // Language toggle event listener binding
    document.querySelectorAll('.lang-toggle-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (window.toggleLanguage) {
                window.toggleLanguage();
            } else if (window.i18n && window.i18n.toggleLanguage) {
                window.i18n.toggleLanguage();
            }
        });
    });

    // Submenu toggle event
    document.querySelectorAll('.submenu-toggle').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const group = btn.closest('.menu-group');
            if (group) {
                group.classList.toggle('open');
                const isStaff = window.location.pathname.includes('staff');
                const key = isStaff ? 'staffProductsMenuOpen' : 'productsMenuOpen';
                localStorage.setItem(key, group.classList.contains('open'));
            }
        });
    });

    // Mobile hamburger & overlay
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const sidebar = document.querySelector('aside.sidebar');

    if (hamburgerBtn && sidebarOverlay && sidebar) {
        hamburgerBtn.addEventListener('click', () => {
            sidebar.classList.add('mobile-open');
            sidebarOverlay.classList.add('active');
        });

        sidebarOverlay.addEventListener('click', () => {
            sidebar.classList.remove('mobile-open');
            sidebarOverlay.classList.remove('active');
        });
    }

    // Global Logout
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', (e) => {
            e.preventDefault();
            localStorage.removeItem('user');
            localStorage.removeItem('userProfileData');
            window.location.href = '/login.html';
        });
    }
}
