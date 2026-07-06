// Centralized Menu Configuration for Admin & Staff Dashboard
export const menuConfig = {
    admin: [
        {
            type: 'link',
            url: 'admin_dashboard.html',
            icon: 'fas fa-home',
            title: 'แดชบอร์ด',
            i18n: 'nav.dashboard',
            desktopOnly: true
        },
        {
            type: 'group',
            id: 'productsGroup',
            icon: 'fas fa-box',
            title: 'สินค้า',
            i18n: 'nav.products',
            items: [
                { url: 'admin_stock.html', title: 'จัดการคลังสินค้า', i18n: 'nav.stock' },
                { url: 'admin_product_management.html', title: 'จัดการสินค้า', i18n: 'nav.product_mgmt', desktopOnly: true },
                { url: 'admin_categories.html', title: 'จัดการหมวดหมู่', i18n: 'nav.category_mgmt' },
                { url: 'admin_promotions.html', title: 'โปรโมชั่น', i18n: 'nav.promotions' }
            ]
        },
        {
            type: 'link',
            url: 'admin_staff.html',
            icon: 'fas fa-users-cog',
            title: 'จัดการพนักงาน',
            i18n: 'nav.staff'
        },
        {
            type: 'link',
            url: 'admin_orders.html',
            icon: 'fas fa-file-invoice',
            title: 'คำสั่งซื้อ',
            i18n: 'nav.orders'
        },
        {
            type: 'link',
            url: 'admin_customers.html',
            icon: 'fas fa-users',
            title: 'ลูกค้า',
            i18n: 'nav.customers'
        },
        {
            type: 'link',
            url: 'pos.html',
            icon: 'fas fa-cash-register',
            title: 'ขายหน้าร้าน',
            i18n: 'nav.pos'
        }
    ],
    staff: [
        {
            type: 'group',
            id: 'productsGroup',
            icon: 'fas fa-box',
            title: 'สินค้า',
            i18n: 'nav.products',
            items: [
                { url: 'staff_stock.html', title: 'คลังสินค้า', i18n: 'nav.stock' },
                { url: 'staff_promotions.html', title: 'โปรโมชั่น', i18n: 'nav.promotions' }
            ]
        },
        {
            type: 'link',
            url: 'staff_orders.html',
            icon: 'fas fa-file-invoice',
            title: 'คำสั่งซื้อ',
            i18n: 'nav.orders'
        },
        {
            type: 'link',
            url: 'staff_customers.html',
            icon: 'fas fa-users',
            title: 'ลูกค้า',
            i18n: 'nav.customers'
        },
        {
            type: 'link',
            url: 'pos.html',
            icon: 'fas fa-cash-register',
            title: 'ขายหน้าร้าน',
            i18n: 'nav.pos'
        },
        {
            type: 'link',
            url: 'staff_profile.html',
            icon: 'fas fa-user-cog',
            title: 'โปรไฟล์ของฉัน',
            i18n: 'nav.profile'
        }
    ]
};
