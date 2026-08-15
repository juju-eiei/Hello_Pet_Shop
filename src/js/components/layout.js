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
            if (window.Swal) {
                await window.Swal.fire({
                    title: 'ปฏิเสธการเข้าถึง',
                    text: 'คุณไม่มีสิทธิ์เข้าใช้งานหน้านี้ (Access Denied)',
                    icon: 'warning',
                    confirmButtonText: 'ตกลง',
                    confirmButtonColor: '#4D7C68'
                });
            } else {
                alert('คุณไม่มีสิทธิ์เข้าใช้งานหน้านี้ (Access Denied)');
            }
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
        initNotifications();
        bindNotificationEvents();
        
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

function isUrlActive(currentFilename, targetUrl) {
    if (!currentFilename || !targetUrl) return false;
    if (currentFilename === targetUrl || targetUrl.endsWith(currentFilename)) return true;
    
    // Map detail pages to main parent pages
    if (currentFilename === 'admin_product_edit.html' && (targetUrl === 'admin_product_management.html' || targetUrl === 'admin_stock.html')) return true;
    if (currentFilename === 'admin_order_details.html' && targetUrl === 'admin_orders.html') return true;
    if (currentFilename === 'admin_customer_details.html' && targetUrl === 'admin_customers.html') return true;
    if (currentFilename === 'staff_order_details.html' && targetUrl === 'staff_orders.html') return true;
    if (currentFilename === 'staff_customer_details.html' && targetUrl === 'staff_customers.html') return true;
    
    return false;
}

export function updateActiveMenu(currentFilename) {
    document.querySelectorAll('.sidebar-menu .menu-item, .sidebar-menu .submenu a').forEach(el => {
        el.classList.remove('active');
        const href = el.getAttribute('href');
        if (href && isUrlActive(currentFilename, href.split('/').pop())) {
            el.classList.add('active');
            const parentGroup = el.closest('.menu-group');
            if (parentGroup) {
                parentGroup.classList.add('open');
            }
        }
    });

    document.querySelectorAll('.bottom-nav .nav-item').forEach(el => {
        el.classList.remove('active');
        const href = el.getAttribute('href');
        if (href && isUrlActive(currentFilename, href.split('/').pop())) {
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

    let menuHTML = `<div class="sidebar-header" style="display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 24px;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-paw text-[#3b82f6]"></i>
            <span>Hello Pet Shop</span>
        </div>
        
        <!-- Desktop Notification Icon & Dropdown -->
        <div class="notification-container relative cursor-pointer" style="margin-left: auto;">
            <div id="desktopNotificationBtn" class="p-2 hover:bg-slate-100 rounded-xl transition-colors relative flex items-center justify-center">
                <i class="fas fa-bell text-slate-500 hover:text-slate-800 text-lg transition-colors"></i>
                <span id="desktopNotificationBadge" class="absolute top-1 right-1 bg-red-500 text-white text-[9px] font-bold w-4 h-4 rounded-full flex items-center justify-center border border-white hidden">0</span>
            </div>
            
            <!-- Desktop Dropdown -->
            <div id="desktopNotificationDropdown" class="absolute top-12 left-0 bg-white border border-slate-100 rounded-2xl shadow-2xl w-80 text-slate-700 z-50 text-left font-normal overflow-hidden transform origin-top-left transition-all duration-200 scale-95 opacity-0 pointer-events-none">
                <div class="dropdown-header px-4 py-3 border-b border-slate-100 flex justify-between items-center font-bold text-sm bg-slate-50/50">
                    <span>การแจ้งเตือนสต็อก</span>
                    <button id="desktopSendLineBtn" class="bg-[#06c755] hover:bg-[#05b04b] text-white border-0 px-2.5 py-1 rounded-lg text-xs font-semibold cursor-pointer flex items-center gap-1 transition-colors">
                        <i class="fab fa-line text-sm"></i> ส่งไลน์
                    </button>
                </div>
                <div id="desktopNotificationList" class="max-h-[300px] overflow-y-auto py-2">
                    <div class="px-4 py-6 text-center text-slate-400 text-sm">กำลังโหลด...</div>
                </div>
                <div class="dropdown-footer px-4 py-2.5 border-t border-slate-100 text-center bg-slate-50/30">
                    <a href="${role === 'admin' ? 'admin_stock.html' : 'staff_stock.html'}" class="text-xs text-blue-600 hover:text-blue-700 no-underline font-bold transition-colors">จัดการสต็อกทั้งหมด</a>
                </div>
            </div>
        </div>
    </div>
    <nav class="sidebar-menu">
        <div style="padding: 8px 20px 4px 20px; font-size: 11px; color: #94a3b8; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">${role === 'admin' ? 'ระบบผู้ดูแลร้าน' : 'ระบบพนักงาน'}</div>`;

    items.forEach(item => {
        if (item.type === 'link') {
            const isActive = isUrlActive(currentFilename, item.url) ? 'active' : '';
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
                const isActive = isUrlActive(currentFilename, sub.url) ? 'active' : '';
                if (isActive) groupHasActive = true;
                const desktopClass = sub.desktopOnly ? 'desktop-only-menu' : '';
                const iconHTML = sub.icon ? `<i class="${sub.icon}"></i>` : `<i class="fas fa-angle-right"></i>`;
                subItemsHTML += `
                    <a href="${sub.url}" class="${desktopClass} ${isActive}" data-i18n="${sub.i18n}">
                        ${iconHTML}
                        <span>${sub.title}</span>
                    </a>`;
            });

            const savedState = item.id ? localStorage.getItem(`menu_group_${item.id}`) : null;
            const openClass = (groupHasActive || savedState === 'true') ? 'open' : '';

            menuHTML += `
                <div class="menu-group ${openClass}" ${item.id ? `data-group-id="${item.id}"` : ''}>
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
        
        <!-- Mobile Notification Icon & Dropdown -->
        <div class="notification-container relative cursor-pointer ml-auto flex items-center justify-center">
            <div id="mobileNotificationBtn" class="p-2 hover:bg-slate-100 rounded-xl transition-colors relative flex items-center justify-center">
                <i class="fas fa-bell text-slate-700 text-xl"></i>
                <span id="mobileNotificationBadge" class="absolute top-1 right-1 bg-red-500 text-white text-[9px] font-bold w-4 h-4 rounded-full flex items-center justify-center border border-white hidden">0</span>
            </div>
            
            <!-- Mobile Dropdown -->
            <div id="mobileNotificationDropdown" class="absolute top-12 right-0 bg-white border border-slate-100 rounded-2xl shadow-2xl w-72 text-slate-700 z-50 text-left font-normal overflow-hidden transform origin-top-right transition-all duration-200 scale-95 opacity-0 pointer-events-none">
                <div class="dropdown-header px-4 py-2.5 border-b border-slate-100 flex justify-between items-center font-bold text-sm bg-slate-50/50">
                    <span>การแจ้งเตือนสต็อก</span>
                    <button id="mobileSendLineBtn" class="bg-[#06c755] hover:bg-[#05b04b] text-white border-0 px-2 py-1 rounded-lg text-xs font-semibold cursor-pointer flex items-center gap-1 transition-colors">
                        <i class="fab fa-line text-sm"></i> ส่งไลน์
                    </button>
                </div>
                <div id="mobileNotificationList" class="max-h-[250px] overflow-y-auto py-2">
                    <div class="px-4 py-5 text-center text-slate-400 text-sm">กำลังโหลด...</div>
                </div>
                <div class="dropdown-footer px-4 py-2 border-t border-slate-100 text-center bg-slate-50/30">
                    <a href="${currentFilename.startsWith('admin_') ? 'admin_stock.html' : 'staff_stock.html'}" class="text-xs text-blue-600 hover:text-blue-700 no-underline font-bold transition-colors">จัดการสต็อกทั้งหมด</a>
                </div>
            </div>
        </div>
    `;
}

function renderBottomNav(items, currentFilename) {
    const bottomNav = document.querySelector('nav.bottom-nav');
    if (!bottomNav) return;

    const isAdmin = currentFilename.startsWith('admin_');
    let links = [];

    if (isAdmin) {
        links = [
            { url: 'admin_orders.html', icon: 'fas fa-file-invoice', title: 'คำสั่งซื้อ', i18n: 'nav.orders' },
            { url: 'admin_stock.html', icon: 'fas fa-warehouse', title: 'คลังสินค้า', i18n: 'nav.stock' },
            { url: 'admin_customers.html', icon: 'fas fa-users', title: 'ลูกค้า', i18n: 'nav.customers' },
            { url: 'admin_promotions.html', icon: 'fas fa-gift', title: 'โปรโมชั่น', i18n: 'nav.promotions' }
        ];
    } else {
        links = [
            { url: 'staff_orders.html', icon: 'fas fa-file-invoice', title: 'คำสั่งซื้อ', i18n: 'nav.orders' },
            { url: 'staff_stock.html', icon: 'fas fa-warehouse', title: 'คลังสินค้า', i18n: 'nav.stock' },
            { url: 'staff_promotions.html', icon: 'fas fa-gift', title: 'โปรโมชั่น', i18n: 'nav.promotions' },
            { url: 'staff_customers.html', icon: 'fas fa-users', title: 'ลูกค้า', i18n: 'nav.customers' },
            { url: 'staff_profile.html', icon: 'fas fa-user-cog', title: 'โปรไฟล์', i18n: 'nav.profile' }
        ];
    }

    let navHTML = '';
    links.forEach(link => {
        const isActive = isUrlActive(currentFilename, link.url) ? 'active' : '';
        navHTML += `
            <a href="${link.url}" class="nav-item ${isActive}">
                <i class="${link.icon}"></i>
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
                const groupId = group.getAttribute('data-group-id');
                if (groupId) {
                    localStorage.setItem(`menu_group_${groupId}`, group.classList.contains('open'));
                }
            }
        });
    });

    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const sidebar = document.querySelector('aside.sidebar');

    if (hamburgerBtn && sidebarOverlay && sidebar) {
        hamburgerBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('mobile-open');
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
            sidebarOverlay.classList.toggle('show');
        });

        sidebarOverlay.addEventListener('click', () => {
            sidebar.classList.remove('mobile-open', 'active');
            sidebarOverlay.classList.remove('active', 'show');
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

/**
 * Fetch Stock alerts (low stock & near expiry) and populate dropdowns & badges
 */
async function initNotifications() {
    try {
        const response = await fetch('/api/notifications/alerts');
        if (!response.ok) return;
        const result = await response.json();
        const data = result.data;
        
        const count = data.total_alerts || 0;
        
        // Update badges
        const desktopBadge = document.getElementById('desktopNotificationBadge');
        const mobileBadge = document.getElementById('mobileNotificationBadge');
        
        if (desktopBadge) {
            if (count > 0) {
                desktopBadge.textContent = count;
                desktopBadge.classList.remove('hidden');
            } else {
                desktopBadge.classList.add('hidden');
            }
        }
        
        if (mobileBadge) {
            if (count > 0) {
                mobileBadge.textContent = count;
                mobileBadge.classList.remove('hidden');
            } else {
                mobileBadge.classList.add('hidden');
            }
        }

        // Render Dropdown List HTML
        const renderListHTML = (items) => {
            const lowStock = items.low_stock || [];
            const nearExpiry = items.near_expiry || [];
            const targetStockPage = window.location.pathname.startsWith('/admin_') || window.location.pathname.startsWith('admin_') ? 'admin_stock.html' : 'staff_stock.html';
            
            if (lowStock.length === 0 && nearExpiry.length === 0) {
                return `<div class="px-4 py-8 text-center text-green-600 font-semibold flex flex-col items-center gap-2">
                    <i class="fas fa-check-circle text-2xl"></i>
                    <span>สินค้าและสต็อกทั้งหมดปกติดี</span>
                </div>`;
            }
            
            let html = '';
            
            // Expiry alerts first (High urgency)
            nearExpiry.forEach(p => {
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                const expDate = new Date(p.expiry_date);
                expDate.setHours(0, 0, 0, 0);
                const diffDays = Math.ceil((expDate - today) / (1000 * 60 * 60 * 24));
                
                let daysLabel = '';
                if (diffDays < 0) {
                    daysLabel = 'หมดอายุแล้ว';
                } else if (diffDays === 0) {
                    daysLabel = 'หมดอายุวันนี้!';
                } else {
                    daysLabel = `ใกล้หมดอายุ (อีก ${diffDays} วัน)`;
                }
                const badgeColor = diffDays <= 7 ? 'text-red-500' : 'text-yellow-600';
                
                html += `
                <div onclick="window.location.href='${targetStockPage}'" class="px-4 py-2.5 border-b border-slate-50 hover:bg-slate-50 flex items-center gap-3 transition-colors cursor-pointer">
                    <img src="${p.image_url || '/image/non-image.png'}" onerror="this.src='/image/non-image.png'" class="w-9 h-9 rounded-lg object-cover border border-slate-100 flex-shrink-0">
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-xs text-slate-800 truncate" title="${p.product_name}">${p.product_name}</div>
                        <div class="text-[10px] ${badgeColor} font-bold mt-0.5 flex items-center gap-1">
                            <i class="fas fa-clock"></i> ${daysLabel}
                        </div>
                    </div>
                </div>`;
            });
            
            // Low stock alerts
            lowStock.forEach(p => {
                const isOut = p.stock_qty <= 0;
                const label = isOut ? 'สินค้าหมดสต็อก' : `ใกล้หมด (เหลือ ${p.stock_qty} ชิ้น)`;
                const badgeColor = isOut ? 'text-red-500' : 'text-orange-500';
                
                html += `
                <div onclick="window.location.href='${targetStockPage}'" class="px-4 py-2.5 border-b border-slate-50 hover:bg-slate-50 flex items-center gap-3 transition-colors cursor-pointer">
                    <img src="${p.image_url || '/image/non-image.png'}" onerror="this.src='/image/non-image.png'" class="w-9 h-9 rounded-lg object-cover border border-slate-100 flex-shrink-0">
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-xs text-slate-800 truncate" title="${p.product_name}">${p.product_name}</div>
                        <div class="text-[10px] ${badgeColor} font-bold mt-0.5 flex items-center gap-1">
                            <i class="fas fa-exclamation-triangle"></i> ${label}
                        </div>
                    </div>
                </div>`;
            });
            
            return html;
        };

        const listHtml = renderListHTML(data);
        const desktopList = document.getElementById('desktopNotificationList');
        const mobileList = document.getElementById('mobileNotificationList');
        
        if (desktopList) desktopList.innerHTML = listHtml;
        if (mobileList) mobileList.innerHTML = listHtml;
    } catch (e) {
        console.error("Error loading alerts:", e);
    }
}

/**
 * Bind notification dropdown toggles and LINE manual sync events
 */
function bindNotificationEvents() {
    const dBtn = document.getElementById('desktopNotificationBtn');
    const dDropdown = document.getElementById('desktopNotificationDropdown');
    const mBtn = document.getElementById('mobileNotificationBtn');
    const mDropdown = document.getElementById('mobileNotificationDropdown');
    
    const showDropdown = (dropdown) => {
        dropdown.classList.remove('scale-95', 'opacity-0', 'pointer-events-none');
        dropdown.classList.add('scale-100', 'opacity-100', 'pointer-events-auto');
    };
    
    const hideDropdown = (dropdown) => {
        dropdown.classList.add('scale-95', 'opacity-0', 'pointer-events-none');
        dropdown.classList.remove('scale-100', 'opacity-100', 'pointer-events-auto');
    };
    
    const isDropdownOpen = (dropdown) => {
        return dropdown.classList.contains('scale-100');
    };
    
    const toggleDropdown = (btn, dropdown) => {
        if (btn && dropdown) {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                if (isDropdownOpen(dropdown)) {
                    hideDropdown(dropdown);
                } else {
                    showDropdown(dropdown);
                    // Hide alternative dropdown
                    if (dropdown === dDropdown && mDropdown) hideDropdown(mDropdown);
                    if (dropdown === mDropdown && dDropdown) hideDropdown(dDropdown);
                }
            });
        }
    };
    
    toggleDropdown(dBtn, dDropdown);
    toggleDropdown(mBtn, mDropdown);
    
    // Close dropdown when clicking outside
    document.addEventListener('click', () => {
        if (dDropdown) hideDropdown(dDropdown);
        if (mDropdown) hideDropdown(mDropdown);
    });
    
    if (dDropdown) dDropdown.addEventListener('click', (e) => e.stopPropagation());
    if (mDropdown) mDropdown.addEventListener('click', (e) => e.stopPropagation());

    // Bind LINE manual sync trigger
    const bindLineTrigger = (btn) => {
        if (!btn) return;
        btn.addEventListener('click', async (e) => {
            e.stopPropagation();
            const originalHTML = btn.innerHTML;
            btn.innerHTML = `<i class="fas fa-spinner fa-spin text-sm"></i>`;
            btn.disabled = true;
            
            try {
                const response = await fetch('/api/notifications/send-line', {
                    method: 'POST'
                });
                const result = await response.json();
                
                if (window.Swal) {
                    window.Swal.fire({
                        title: response.ok ? 'สำเร็จ!' : 'ไม่สำเร็จ',
                        text: result.message,
                        icon: response.ok ? 'success' : 'error',
                        confirmButtonText: 'ตกลง',
                        confirmButtonColor: '#4D7C68'
                    });
                } else {
                    alert(result.message);
                }
            } catch (err) {
                console.error("Send LINE error:", err);
                if (window.Swal) {
                    window.Swal.fire({
                        title: 'ผิดพลาด',
                        text: 'ไม่สามารถส่งข้อความได้เนื่องจากการเชื่อมต่อขัดข้อง',
                        icon: 'error',
                        confirmButtonText: 'ตกลง',
                        confirmButtonColor: '#4D7C68'
                    });
                } else {
                    alert("เกิดข้อผิดพลาดในการส่งข้อความ");
                }
            } finally {
                btn.innerHTML = originalHTML;
                btn.disabled = false;
            }
        });
    };

    bindLineTrigger(document.getElementById('desktopSendLineBtn'));
    bindLineTrigger(document.getElementById('mobileSendLineBtn'));
}
