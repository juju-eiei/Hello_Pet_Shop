// Centralized Menu Configuration for Admin & Staff Dashboard with Permission constraints
export const menuConfig = {
    admin: [
        {
            type: 'group',
            id: 'overviewGroup',
            icon: 'fas fa-home',
            title: 'ภาพรวม',
            i18n: 'nav.overview',
            items: [
                { url: '/admin/dashboard', icon: 'fas fa-tachometer-alt', title: 'แดชบอร์ด', i18n: 'nav.dashboard', permission: 'dashboard_view' }
            ]
        },
        {
            type: 'group',
            id: 'productsPromotionsGroup',
            icon: 'fas fa-box-open',
            title: 'สินค้าและโปรโมชั่น',
            i18n: 'nav.products_promotions',
            items: [
                { url: '/admin/products', icon: 'fas fa-boxes', title: 'จัดการสินค้า', i18n: 'nav.product_mgmt', permission: 'products_manage' },
                { url: '/admin/stock', icon: 'fas fa-warehouse', title: 'จัดการคลังสินค้า', i18n: 'nav.stock', permission: 'stock_view' },
                { url: '/admin/categories', icon: 'fas fa-tags', title: 'จัดการหมวดหมู่', i18n: 'nav.category_mgmt', permission: 'products_manage' },
                { url: '/admin/promotions', icon: 'fas fa-gift', title: 'โปรโมชั่น', i18n: 'nav.promotions', permission: 'promotions_manage' }
            ]
        },
        {
            type: 'group',
            id: 'salesOrdersGroup',
            icon: 'fas fa-shopping-cart',
            title: 'การขายและคำสั่งซื้อ',
            i18n: 'nav.sales_orders',
            items: [
                { url: '/pos', icon: 'fas fa-cash-register', title: 'ขายหน้าร้าน', i18n: 'nav.pos', permission: 'pos_access' },
                { url: '/admin/orders', icon: 'fas fa-file-invoice', title: 'คำสั่งซื้อ', i18n: 'nav.orders', permission: 'orders_manage' },
                { url: '/admin/customers', icon: 'fas fa-users', title: 'ลูกค้า', i18n: 'nav.customers', permission: 'customers_view' },
                { url: '/admin/delivery', icon: 'fas fa-truck', title: 'จัดการขนส่ง', i18n: 'nav.delivery', permission: 'delivery_manage' },
                { url: '/admin/rewards', icon: 'fas fa-star', title: 'จัดการแต้มสะสม', i18n: 'nav.rewards', permission: 'rewards_manage' }
            ]
        },
        {
            type: 'group',
            id: 'employeesGroup',
            icon: 'fas fa-users-cog',
            title: 'พนักงาน',
            i18n: 'nav.employees',
            items: [
                { url: '/admin/staff', icon: 'fas fa-user-cog', title: 'จัดการพนักงาน', i18n: 'nav.staff', permission: 'staff_manage' },
                { url: '/admin/schedule', icon: 'fas fa-calendar-alt', title: 'ตารางงานพนักงาน', i18n: 'nav.schedule', permission: 'staff_manage' },
                { url: '/admin/attendance', icon: 'fas fa-user-check', title: 'ตรวจสอบการเข้าทำงาน', i18n: 'nav.attendance', permission: 'staff_manage' },
                { url: '/admin/payroll/settings', icon: 'fas fa-sliders-h', title: 'กำหนดอัตราค่าจ้าง', i18n: 'nav.pay_settings', permission: 'staff_manage' }
            ]
        },
        {
            type: 'group',
            id: 'financeGroup',
            icon: 'fas fa-wallet',
            title: 'การเงิน',
            i18n: 'nav.finance',
            items: [
                { url: '/admin/payroll', icon: 'fas fa-money-check-alt', title: 'จัดการจ่ายเงินเดือน', i18n: 'nav.payroll', permission: 'staff_manage' },
                { url: '/admin/transactions', icon: 'fas fa-receipt', title: 'จัดการรายรับรายจ่าย', i18n: 'nav.transactions', permission: 'dashboard_view' }
            ]
        }
    ],
    staff: [
        {
            type: 'group',
            id: 'staffSalesOrdersGroup',
            icon: 'fas fa-shopping-cart',
            title: 'การขายและคำสั่งซื้อ',
            i18n: 'nav.sales_orders',
            items: [
                { url: '/pos', icon: 'fas fa-cash-register', title: 'ขายหน้าร้าน', i18n: 'nav.pos', permission: 'pos_access' },
                { url: '/staff/orders', icon: 'fas fa-file-invoice', title: 'คำสั่งซื้อ', i18n: 'nav.orders', permission: 'orders_manage' },
                { url: '/staff/customers', icon: 'fas fa-users', title: 'ลูกค้า', i18n: 'nav.customers', permission: 'customers_view' }
            ]
        },
        {
            type: 'group',
            id: 'staffProductsPromotionsGroup',
            icon: 'fas fa-box-open',
            title: 'สินค้าและโปรโมชั่น',
            i18n: 'nav.products_promotions',
            items: [
                { url: '/staff/stock', icon: 'fas fa-warehouse', title: 'คลังสินค้า', i18n: 'nav.stock', permission: 'stock_view' },
                { url: '/staff/promotions', icon: 'fas fa-gift', title: 'โปรโมชั่น', i18n: 'nav.promotions', permission: 'promotions_view' }
            ]
        },
        {
            type: 'group',
            id: 'staffPersonalGroup',
            icon: 'fas fa-user-circle',
            title: 'ส่วนตัวและตารางงาน',
            i18n: 'nav.personal_schedule',
            items: [
                { url: '/staff/schedule', icon: 'fas fa-calendar-alt', title: 'จองตารางงาน', permission: 'staff_profile_manage' },
                { url: '/staff/profile', icon: 'fas fa-user-cog', title: 'โปรไฟล์ของฉัน', i18n: 'nav.profile', permission: 'staff_profile_manage' }
            ]
        }
    ]
};
