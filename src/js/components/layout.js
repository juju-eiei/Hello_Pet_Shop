import { menuConfig } from '../config/menu.js';

export async function initLayout() {
    const currentPath = window.location.pathname;
    const filename = currentPath.split('/').pop() || 'index.html';
    
    // Only run centralized layout and gates for admin or staff pages
    if (!filename.startsWith('admin_') && !filename.startsWith('staff_') && filename !== 'pos.html') {
        return;
    }
    
    try {
        const res = await fetch('/api/auth/me');
        if (!res.ok) {
            // Session expired or invalid
            localStorage.removeItem('user');
            window.location.href = '/login.html';
            return;
        }
        
        const result = await res.json();
        const user = result.data;
        localStorage.setItem('user', JSON.stringify(user));
        
        const roleNameLower = (user.role_name || '').toLowerCase();
        let role = 'staff';
        if (roleNameLower === 'admin') {
            role = 'admin';
        } else if (roleNameLower === 'customer') {
            window.location.href = '/products.html';
            return;
        }
        
        const permissions = user.permissions || [];
        
        // Dynamic Guard Check: Is user allowed to view this page?
        let allowed = true;
        const allMenuUrlMaps = [];
        
        const traverseMenu = (menuList) => {
            menuList.forEach(m => {
                if (m.type === 'link') {
                    allMenuUrlMaps.push({ url: m.url, permission: m.permission });
                } else if (m.type === 'group' && m.items) {
                    m.items.forEach(sub => {
                        allMenuUrlMaps.push({ url: sub.url, permission: sub.permission });
                    });
                }
            });
        };
        
        traverseMenu(menuConfig.admin);
        traverseMenu(menuConfig.staff);
        
        const matchingConfig = allMenuUrlMaps.find(m => m.url === filename);
        
        if (matchingConfig && matchingConfig.permission) {
            if (roleNameLower !== 'admin' && !permissions.includes(matchingConfig.permission)) {
                allowed = false;
            }
        }
        
        // Also enforce path role logic
        if (filename.startsWith('admin_') && roleNameLower !== 'admin') {
            allowed = false;
        }
        
        if (!allowed) {
            alert('คุณไม่มีสิทธิ์เข้าใช้งานหน้านี้ (Access Denied)');
            window.location.href = roleNameLower === 'admin' ? 'admin_stock.html' : 'staff_profile.html';
            return;
        }
        
        // Filter menu items for display
        const rawItems = menuConfig[role] || menuConfig.staff;
        const filteredItems = filterMenuByPermissions(rawItems, permissions, roleNameLower === 'admin');
        
        renderSidebar(filteredItems, filename, role);
        renderMobileHeader(filename);
        renderBottomNav(filteredItems, filename);
        bindLayoutEvents();
        
        if (window.i18n && typeof window.i18n.applyTranslations === 'function') {
            window.i18n.applyTranslations();
        }
    } catch (error) {
        console.error('Error during auth validation:', error);
        window.location.href = '/login.html';
    }
}

function filterMenuByPermissions(menuItems, permissions, isAdmin) {
    if (isAdmin) return menuItems;
    
    return menuItems.map(item => {
        if (item.type === 'link') {
            if (!item.permission || permissions.includes(item.permission)) {
                return item;
            }
        } else if (item.type === 'group') {
            const filteredSubItems = item.items.filter(sub => !sub.permission || permissions.includes(sub.permission));
            if (filteredSubItems.length > 0) {
                return { ...item, items: filteredSubItems };
            }
        }
        return null;
    }).filter(Boolean);
}

export function updateActiveMenu(currentFilename) {
    document.querySelectorAll('.sidebar-menu .menu-item, .sidebar-menu .submenu a').forEach(el => {
        el.classList.remove('active');
        const href = el.getAttribute('href');
        if (href && href.endsWith(currentFilename)) {
            el.classList.add('active');
            const parentGroup = el.closest('.menu-group');
            if (parentGroup) {
                const isStaff = window.location.pathname.includes('staff');
                const key = isStaff ? 'staffProductsMenuOpen' : 'productsMenuOpen';
                if (localStorage.getItem(key) === 'true') {
                    parentGroup.classList.add('open');
                } else {
                    parentGroup.classList.remove('open');
                }
            }
        }
    });

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
    const isGroupOpen = localStorage.getItem(key) !== 'false';
    let menuHTML = `<div class="sidebar-header" style="display: flex; align-items: center; justify-content: space-between;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-paw"></i>
            <span>Hello Pet Shop</span>
        </div>
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
    mobileHeader.innerHTML = `
        <i class="fas fa-bars" id="hamburgerBtn"></i>
        <h2>${titleText}</h2>
        <div style="display: flex; align-items: center; gap: 10px; margin-left: auto;">
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

    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', (e) => {
            e.preventDefault();
            localStorage.removeItem('user');
            localStorage.removeItem('userProfileData');
            localStorage.removeItem('productsMenuOpen');
            localStorage.removeItem('staffProductsMenuOpen');
            window.location.href = '/login.html';
        });
    }
}
