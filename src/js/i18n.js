// Clean, Lightweight & Bulletproof i18n System (Data Attribute Standard)
const dictionary = {
    th: {
        // Navigation & Menu
        "nav.dashboard": "แดชบอร์ด",
        "nav.products": "สินค้า",
        "nav.stock": "จัดการคลังสินค้า",
        "nav.product_mgmt": "จัดการสินค้า",
        "nav.category_mgmt": "จัดการหมวดหมู่",
        "nav.promotions": "โปรโมชั่น",
        "nav.staff": "จัดการพนักงาน",
        "nav.permissions": "ตั้งค่าระดับสิทธิ์การเข้าถึง",
        "nav.orders": "คำสั่งซื้อ",
        "nav.customers": "ลูกค้า",
        "nav.delivery": "จัดการขนส่ง",
        "nav.rewards": "จัดการแต้มสะสม",
        "nav.pos": "ขายหน้าร้าน",
        "nav.logout": "ออกจากระบบ",
        "nav.profile": "โปรไฟล์",
        "nav.my_pets": "สัตว์เลี้ยงของฉัน",
        "nav.cart": "ตะกร้าสินค้า",
        "nav.contact": "ติดต่อเรา",
        
        // Page Titles & Headers
        "title.rewards": "ระบบจัดการแต้มสะสม",
        "title.dashboard": "แดชบอร์ด",
        "title.staff_mgmt": "จัดการพนักงาน",
        "title.pos": "POS - ขายหน้าร้าน",
        "title.orders": "คำสั่งซื้อ",
        "title.delivery": "จัดการระบบขนส่ง",
        "title.customers": "จัดการลูกค้า",
        "title.stock": "จัดการคลังสินค้า",
        "title.product_mgmt": "จัดการสินค้า",
        "title.categories": "จัดการหมวดหมู่สินค้า",
        "title.promotions": "จัดการโปรโมชั่น",
        "title.cart": "ตะกร้าสินค้า",
        "title.profile": "โปรไฟล์ส่วนตัว",
        "title.contact": "ติดต่อ Hello Pet Shop",
        
        // Common Buttons & Actions
        "btn.add_staff": "เพิ่มพนักงานใหม่",
        "btn.add_product": "เพิ่มสินค้าใหม่",
        "btn.add_category": "เพิ่มหมวดหมู่ใหม่",
        "btn.add_promo": "เพิ่มโปรโมชั่นใหม่",
        "btn.save": "บันทึกข้อมูล",
        "btn.cancel": "ยกเลิก",
        "btn.delete": "ลบ",
        "btn.edit": "แก้ไข",
        "btn.search": "ค้นหา",
        "btn.clear": "ล้าง",
        "btn.checkout": "ชำระเงิน",
        "btn.barcode": "บาร์โค้ด",
        "btn.print": "พิมพ์",
        "btn.confirm": "ยืนยัน",
        "btn.close": "ปิด",
        "btn.back": "ย้อนกลับ",
        "btn.refresh": "รีเฟรช",
        "btn.add": "เพิ่ม",
        "btn.update": "อัปเดต",
        "btn.reset": "รีเซ็ต",

        // Common Labels & Table Headers
        "label.search_placeholder": "ค้นหา...",
        "label.name": "ชื่อ-นามสกุล",
        "label.product_name": "ชื่อสินค้า",
        "label.category": "หมวดหมู่",
        "label.role": "บทบาท",
        "label.contact": "ข้อมูลติดต่อ",
        "label.action": "การดำเนินการ",
        "label.status": "สถานะ",
        "label.price": "ราคา",
        "label.stock_qty": "จำนวนในสต็อก",
        "label.barcode": "รหัสบาร์โค้ด",
        "label.created_at": "วันที่สร้าง",
        "label.total_amount": "ราคารวม",
        "label.phone": "เบอร์โทรศัพท์",
        "label.email": "อีเมล",
        "label.address": "ที่อยู่",
        "label.description": "รายละเอียด",
        "label.image": "รูปภาพ",
        "label.unit": "หน่วยนับ",
        "label.sku": "รหัสสินค้า (SKU)",
        "label.min_stock": "สต็อกขั้นต่ำ",
        "title.salary_details": "รายละเอียดเงินเดือน",
        "title.system_role": "บทบาทในระบบ",
        "label.base_salary": "เงินเดือนพื้นฐาน",
        "label.payment_frequency": "รอบความถี่ในการจ่ายเงิน",
        "label.monthly": "รายเดือน",
        "label.weekly": "รายสัปดาห์",
        "label.daily": "รายวัน",
        "label.bank_account_details": "ข้อมูลบัญชีธนาคาร",
        "placeholder.enter_base_salary": "ระบุเงินเดือนพื้นฐาน",
        "placeholder.enter_bank_details": "ระบุข้อมูลบัญชีธนาคาร (เช่น ชื่อธนาคาร เลขที่บัญชี)",
        "title.attendance": "ปฏิทินเวลาทำงาน",
        "btn.attendance": "เวลาทำงาน",
        "label.attendance_days": "จำนวนวันที่เข้าทำงานจริง",
        "tab.attendance": "เวลาเข้างาน",
        "btn.view_attendance": "เปิดปฏิทินเวลาทำงาน",
        "label.days": "วัน",
        "title.attendance_history": "ประวัติเวลาทำงาน",
        "tab.employee_info": "ข้อมูลพนักงาน",
        "tab.salary_details": "รายละเอียด",

        // Statuses
        "status.active": "ใช้งาน",
        "status.inactive": "ปิดใช้งาน",
        "status.pending": "รอดำเนินการ",
        "status.processing": "กำลังดำเนินการ",
        "status.completed": "สำเร็จ",
        "status.cancelled": "ยกเลิก",
        "status.out_of_stock": "สินค้าหมด",
        "status.low_stock": "สต็อกต่ำ",
        "status.paid": "ชำระเงินแล้ว",
        "status.unpaid": "ยังไม่ชำระเงิน"
    },
    en: {
        // Navigation & Menu
        "nav.dashboard": "Dashboard",
        "nav.products": "Products",
        "nav.stock": "Stock Management",
        "nav.product_mgmt": "Product Management",
        "nav.category_mgmt": "Category Management",
        "nav.promotions": "Promotions",
        "nav.staff": "Staff Management",
        "nav.permissions": "Role Permissions",
        "nav.orders": "Orders",
        "nav.customers": "Customers",
        "nav.delivery": "Shipping",
        "nav.rewards": "Rewards",
        "nav.pos": "POS",
        "nav.logout": "Logout",
        "nav.profile": "Profile",
        "nav.my_pets": "My Pets",
        "nav.cart": "Cart",
        "nav.contact": "Contact Us",

        // Page Titles & Headers
        "title.rewards": "Reward Points Management",
        "title.dashboard": "Dashboard",
        "title.staff_mgmt": "Staff Management",
        "title.pos": "POS - Point of Sale",
        "title.orders": "Orders",
        "title.delivery": "Shipping Management",
        "title.customers": "Customer Management",
        "title.stock": "Stock Management",
        "title.product_mgmt": "Product Management",
        "title.categories": "Category Management",
        "title.promotions": "Promotions Management",
        "title.cart": "Shopping Cart",
        "title.profile": "User Profile",
        "title.contact": "Contact Hello Pet Shop",

        // Common Buttons & Actions
        "btn.add_staff": "Add New Staff",
        "btn.add_product": "Add New Product",
        "btn.add_category": "Add New Category",
        "btn.add_promo": "Add New Promotion",
        "btn.save": "Save",
        "btn.cancel": "Cancel",
        "btn.delete": "Delete",
        "btn.edit": "Edit",
        "btn.search": "Search",
        "btn.clear": "Clear",
        "btn.checkout": "Checkout",
        "btn.barcode": "Barcode",
        "btn.print": "Print",
        "btn.confirm": "Confirm",
        "btn.close": "Close",
        "btn.back": "Back",
        "btn.refresh": "Refresh",
        "btn.add": "Add",
        "btn.update": "Update",
        "btn.reset": "Reset",

        // Common Labels & Table Headers
        "label.search_placeholder": "Search...",
        "label.name": "Name",
        "label.product_name": "Product Name",
        "label.category": "Category",
        "label.role": "Role",
        "label.contact": "Contact Info",
        "label.action": "Actions",
        "label.status": "Status",
        "label.price": "Price",
        "label.stock_qty": "Stock Qty",
        "label.barcode": "Barcode",
        "label.created_at": "Date Created",
        "label.total_amount": "Total Amount",
        "label.phone": "Phone Number",
        "label.email": "Email",
        "label.address": "Address",
        "label.description": "Description",
        "label.image": "Image",
        "label.unit": "Unit",
        "label.sku": "SKU Code",
        "label.min_stock": "Min Stock",
        "title.salary_details": "Salary Details",
        "title.system_role": "System Role",
        "label.base_salary": "Base Salary",
        "label.payment_frequency": "Payment Frequency",
        "label.monthly": "Monthly",
        "label.weekly": "Weekly",
        "label.daily": "Daily",
        "label.bank_account_details": "Bank Account Details",
        "placeholder.enter_base_salary": "Enter base salary",
        "placeholder.enter_bank_details": "Enter bank account details",
        "title.attendance": "Attendance Calendar",
        "btn.attendance": "Attendance",
        "label.attendance_days": "Days Worked",
        "tab.attendance": "Attendance Logs",
        "btn.view_attendance": "View Attendance Calendar",
        "label.days": "days",
        "title.attendance_history": "Attendance History",
        "tab.employee_info": "Employee Information",
        "tab.salary_details": "Details",

        // Statuses
        "status.active": "Active",
        "status.inactive": "Inactive",
        "status.pending": "Pending",
        "status.processing": "Processing",
        "status.completed": "Completed",
        "status.cancelled": "Cancelled",
        "status.out_of_stock": "Out of Stock",
        "status.low_stock": "Low Stock",
        "status.paid": "Paid",
        "status.unpaid": "Unpaid"
    }
};

export function getLanguage() {
    return 'th';
}

export function setLanguage(lang) {
    applyTranslations();
}

export function toggleLanguage() {
    // Disabled: Always Thai
}

export function t(key, defaultVal = '') {
    const dict = dictionary.th;
    return dict[key] || defaultVal || key;
}

export function applyTranslations() {
    const dict = dictionary.th;

    document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        if (dict[key]) {
            el.textContent = dict[key];
        }
    });

    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
        const key = el.getAttribute('data-i18n-placeholder');
        if (dict[key]) {
            el.placeholder = dict[key];
        }
    });

    document.querySelectorAll('[data-i18n-title]').forEach(el => {
        const key = el.getAttribute('data-i18n-title');
        if (dict[key]) {
            el.title = dict[key];
        }
    });
}

export function updateLanguageButtons() {
    // Disabled: No language buttons
}

export function injectLanguageToggleButtons() {
    // Disabled: No language buttons
}

// Global initialization & Window API registration
if (typeof window !== 'undefined') {
    window.setLanguage = setLanguage;
    window.toggleLanguage = toggleLanguage;
    window.applyTranslations = applyTranslations;
    window.t = t;
    window.i18n = {
        getLanguage,
        setLanguage,
        toggleLanguage,
        applyTranslations,
        translatePage: applyTranslations,
        updateLanguageButtons,
        injectLanguageToggleButtons,
        t,
        dictionary
    };
}

const init = () => {
    applyTranslations();
    injectLanguageToggleButtons();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
