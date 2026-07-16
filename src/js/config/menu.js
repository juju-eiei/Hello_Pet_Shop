// Centralized Menu Configuration for Admin & Staff Dashboard with Permission constraints
export const menuConfig = {
    admin: [
        {
            type: 'link',
            url: 'admin_dashboard.html',
            icon: 'fas fa-home',
            title: 'แดชบอร์ด',
            i18n: 'nav.dashboard',
            desktopOnly: true,
            permission: 'dashboard_view'
        },
        {
            type: 'group',
            id: 'productsGroup',
            icon: 'fas fa-box',
            title: 'สินค้า',
            i18n: 'nav.products',
            items: [
                { url: 'admin_stock.html', title: 'จัดการคลังสินค้า', i18n: 'nav.stock', permission: 'stock_view' },
                { url: 'admin_product_management.html', title: 'จัดการสินค้า', i18n: 'nav.product_mgmt', desktopOnly: true, permission: 'products_manage' },
                { url: 'admin_categories.html', title: 'จัดการหมวดหมู่', i18n: 'nav.category_mgmt', permission: 'products_manage' }
            ]
        },
        {
            type: 'link',
            url: 'admin_promotions.html',
            icon: 'fas fa-gift',
            title: 'โปรโมชั่น',
            i18n: 'nav.promotions',
            permission: 'promotions_manage'
        },
        {
            type: 'link',
            url: 'admin_staff.html',
            icon: 'fas fa-users-cog',
            title: 'จัดการพนักงาน',
            i18n: 'nav.staff',
            permission: 'staff_manage'
        },
        {
            type: 'link',
            url: 'admin_orders.html',
            icon: 'fas fa-file-invoice',
            title: 'คำสั่งซื้อ',
            i18n: 'nav.orders',
            permission: 'orders_manage'
        },
        {
            type: 'link',
            url: 'admin_customers.html',
            icon: 'fas fa-users',
            title: 'ลูกค้า',
            i18n: 'nav.customers',
            permission: 'customers_view'
        },
        {
            type: 'link',
            url: 'admin_delivery.html',
            icon: 'fas fa-truck',
            title: 'จัดการขนส่ง',
            i18n: 'nav.delivery',
            permission: 'delivery_manage'
        },
        {
            type: 'link',
            url: 'admin_reward_management.html',
            icon: 'fas fa-star',
            title: 'จัดการแต้มสะสม',
            i18n: 'nav.rewards',
            permission: 'rewards_manage'
        },
        {
            type: 'link',
            url: 'pos.html',
            icon: 'fas fa-cash-register',
            title: 'ขายหน้าร้าน',
            i18n: 'nav.pos',
            permission: 'pos_access'
        }
    ],
    staff: [
        {
            type: 'link',
            url: 'staff_stock.html',
            icon: 'fas fa-box',
            title: 'คลังสินค้า',
            i18n: 'nav.stock',
            permission: 'stock_view'
        },
        {
            type: 'link',
            url: 'staff_promotions.html',
            icon: 'fas fa-gift',
            title: 'โปรโมชั่น',
            i18n: 'nav.promotions',
            permission: 'promotions_view'
        },
        {
            type: 'link',
            url: 'staff_orders.html',
            icon: 'fas fa-file-invoice',
            title: 'คำสั่งซื้อ',
            i18n: 'nav.orders',
            permission: 'orders_manage'
        },
        {
            type: 'link',
            url: 'staff_customers.html',
            icon: 'fas fa-users',
            title: 'ลูกค้า',
            i18n: 'nav.customers',
            permission: 'customers_view'
        },
        {
            type: 'link',
            url: 'pos.html',
            icon: 'fas fa-cash-register',
            title: 'ขายหน้าร้าน',
            i18n: 'nav.pos',
            permission: 'pos_access'
        },
        {
            type: 'link',
            url: 'staff_profile.html',
            icon: 'fas fa-user-cog',
            title: 'โปรไฟล์ของฉัน',
            i18n: 'nav.profile',
            permission: 'staff_profile_manage'
        }
    ]
};
