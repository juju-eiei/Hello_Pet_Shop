import { showToast, escapeHTML, getUserOrdersData, saveUserOrdersData, cleanLegacyOrderStorage } from './utils.js';

export function initOrderHistoryPage() {
    const cleanPath = (window.location.pathname || '').toLowerCase();
    const isStaffOrAdmin = cleanPath.includes('/staff') || cleanPath.includes('/admin') || cleanPath.includes('staff_') || cleanPath.includes('admin_');
    const isCustomerOrders = cleanPath.includes('/orders') || cleanPath.includes('/order-history') || cleanPath.includes('order-history.html');
    const emptyOrders = document.getElementById('emptyOrders');
    const ordersContainer = document.getElementById('ordersContainer');

    // Strict guard: Never run on staff or admin pages, and only run on customer order history
    if (isStaffOrAdmin || !isCustomerOrders || !emptyOrders || !ordersContainer) return;
    const orderDetailModal = document.getElementById('orderDetailModal');
    const pendingBadge = document.getElementById('pendingBadge');
    const preparingBadge = document.getElementById('preparingBadge');
    const shippingBadge = document.getElementById('shippingBadge');

    // Pay Now Modal Elements
    const payNowModal = document.getElementById('payNowModal');
    const closePayModalBtn = document.getElementById('closePayModalBtn');
    const payModalOrderId = document.getElementById('payModalOrderId');
    const payModalAmount = document.getElementById('payModalAmount');
    const payModalSlipInput = document.getElementById('payModalSlipInput');
    const payModalPlaceholder = document.getElementById('payModalPlaceholder');
    const payModalPreview = document.getElementById('payModalPreview');
    const payModalFileName = document.getElementById('payModalFileName');
    const payModalRemoveSlip = document.getElementById('payModalRemoveSlip');
    const confirmPayNowBtn = document.getElementById('confirmPayNowBtn');

    // Detail Modal fields
    const modalOrderId = document.getElementById('modalOrderId');
    const modalDate = document.getElementById('modalDate');
    const modalStatus = document.getElementById('modalStatus');
    const modalPayment = document.getElementById('modalPayment');
    const modalShipping = document.getElementById('modalShipping');
    const modalItems = document.getElementById('modalItems');
    const modalSubtotal = document.getElementById('modalSubtotal');
    const modalShippingFee = document.getElementById('modalShippingFee');
    const modalTotal = document.getElementById('modalTotal');

    // Tabs
    const tabBtns = document.querySelectorAll('.tab-btn');
    
    let orders = [];
    let currentTab = 'all';
    let currentPayingOrder = null;
    let attachedSlipData = null;
    let paymentSettings = null;

    // Check URL parameters for tab selection (e.g. /orders?tab=pending_payment)
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    if (tabParam) {
        currentTab = tabParam;
    }

    // Immediately trigger order loading and cache rendering
    loadOrders();

    async function fetchPaymentSettings() {
        try {
            const res = await fetch('/api/payment/settings');
            if (res.ok) {
                const result = await res.json();
                paymentSettings = result.data;
            }
        } catch (e) {
            console.error("Error fetching payment settings in order history:", e);
        }
    }

    // Load orders
    async function loadOrders() {
        cleanLegacyOrderStorage();
        seedDemoOrdersIfEmpty();
        fetchPaymentSettings();
        
        let localOrders = getUserOrdersData();

        // 1. Instant Cache Render: If orders exist in local storage, render immediately (0ms)
        if (localOrders && localOrders.length > 0) {
            orders = localOrders.map(o => ({
                ...o,
                status: mapDbStatusToUi(o.status)
            }));
            updatePendingBadge();
            renderOrders();
            updateActiveTabUI();
        }
        
        try {
            const res = await fetch('/api/orders');
            if (res.ok) {
                const apiRes = await res.json();
                if (apiRes.data && Array.isArray(apiRes.data)) {
                    const mappedApiOrders = apiRes.data.map(o => {
                        const rawTotal = parseFloat(o.total_amount ?? o.amount ?? o.total ?? 0);
                        const rawShipping = parseFloat(o.shipping_fee || 0);
                        const rawSubtotal = parseFloat(o.subtotal ?? (rawTotal - rawShipping));
                        
                        let items = (o.items || []).map(i => ({
                            name: i.product_name || i.name || 'สินค้าในรายการ',
                            price: parseFloat(i.unit_price || i.price || 0),
                            quantity: parseInt(i.quantity || i.qty || 1),
                            image: (i.image_url || i.image || '').startsWith('data:') ? '/image/713815-00-allonline-hg.jpg' : (i.image_url || i.image || '/image/713815-00-allonline-hg.jpg')
                        }));

                        // If API didn't have items, check if local order had items
                        if (items.length === 0) {
                            const existingLocal = localOrders.find(lo => String(lo.id) === String(o.order_id || o.id));
                            if (existingLocal && existingLocal.items && existingLocal.items.length > 0) {
                                items = existingLocal.items;
                            }
                        }

                        const hasSlip = Boolean(o.has_slip || o.slip_image);

                        return {
                            id: o.order_id || o.id,
                            date: o.date || o.order_date || o.created_at || new Date().toISOString(),
                            status: mapDbStatusToUi(o.status),
                            items: items,
                            subtotal: isNaN(rawSubtotal) ? 0 : rawSubtotal,
                            shipping: isNaN(rawShipping) ? 0 : rawShipping,
                            total: isNaN(rawTotal) ? 0 : rawTotal,
                            deliveryMethod: o.company_name || o.shipping_provider || 'Standard Express',
                            paymentMethod: o.payment_method || 'transfer',
                            slipImage: null, // Never keep Base64 in frontend memory/storage
                            slip_image: o.slip_image || null,
                            has_slip: hasSlip,
                            hasSlip: hasSlip,
                            payment_status: o.payment_status !== null && o.payment_status !== undefined ? Number(o.payment_status) : null,
                            customer: o.customer || null,
                            shippingAddress: o.shippingAddress || null
                        };
                    });

                    // Database/API is the single source of truth
                    orders = mappedApiOrders;
                    saveUserOrdersData(orders);
                    updatePendingBadge();
                    renderOrders();
                    updateActiveTabUI();
                    return;
                }
            }
        } catch (e) {
            console.warn("Backend orders sync note:", e);
        }

        // Fallback to local cached orders only if network failed / offline
        orders = localOrders.map(o => ({
            ...o,
            status: mapDbStatusToUi(o.status)
        }));
        saveUserOrdersData(orders);
        updatePendingBadge();
        renderOrders();
        updateActiveTabUI();
    }

    function isStatusPending(status) {
        const s = String(status || '').toLowerCase().trim();
        return s === '1' || s.includes('pending') || s.includes('unpaid') || s.includes('ที่ต้องชำระ') || s.includes('รอดำเนินการ');
    }

    function isStatusPreparing(status) {
        const s = String(status || '').toLowerCase().trim();
        return s === '2' || s.includes('preparing') || s.includes('processing') || s.includes('paid') || s.includes('กำลังแพ็คสินค้า') || s.includes('ที่ต้องจัดส่ง') || s.includes('จัดเตรียม') || s.includes('กำลังดำเนินการ') || s.includes('แพ็คสินค้า') || s.includes('กำลังเตรียมสินค้า');
    }

    function isStatusShipping(status) {
        const s = String(status || '').toLowerCase().trim();
        return s === '3' || s.includes('shipping') || s.includes('shipped') || s.includes('in transit') || s.includes('transit') || s.includes('กำลังจัดส่ง') || s.includes('ส่งแล้ว') || s.includes('ที่ต้องได้รับ') || s.includes('มอบให้ขนส่งแล้ว');
    }

    function isStatusCompleted(status) {
        const s = String(status || '').toLowerCase().trim();
        return s === '4' || s.includes('completed') || s.includes('success') || s.includes('สำเร็จ') || s.includes('จัดส่งสำเร็จ') || s.includes('ลูกค้าได้รับสินค้า');
    }

    function isStatusCancelled(status) {
        const s = String(status || '').toLowerCase().trim();
        return s === '5' || s.includes('cancel') || s.includes('ยกเลิก');
    }

    function mapDbStatusToUi(dbStatus) {
        if (dbStatus === null || dbStatus === undefined) return 'Pending Payment';
        if (isStatusPending(dbStatus)) return 'Pending Payment';
        if (isStatusPreparing(dbStatus)) return 'Preparing';
        if (isStatusShipping(dbStatus)) return 'Shipping';
        if (isStatusCompleted(dbStatus)) return 'Completed';
        if (isStatusCancelled(dbStatus)) return 'Cancelled';
        return 'Pending Payment';
    }

    function seedDemoOrdersIfEmpty() {
        const userObj = JSON.parse(localStorage.getItem('user') || '{}');
        if (userObj.user_id || userObj.username) return; // Real registered user: do NOT seed demo orders!
        const stored = localStorage.getItem('myOrders');
        if (!stored || stored === '[]') {
            const demoOrders = [
                {
                    id: 849201,
                    date: new Date(Date.now() - 3600000 * 2).toISOString(),
                    items: [
                        { name: "แปรงหวีขนสัตว์เลี้ยง สแตนเลส", price: 320, quantity: 1, image: "/image/713815-00-allonline-hg.jpg" }
                    ],
                    subtotal: 320,
                    shipping: 2,
                    total: 322,
                    deliveryMethod: "standard",
                    paymentMethod: "transfer",
                    status: "Pending Payment",
                    slipImage: null
                },
                {
                    id: 739102,
                    date: new Date(Date.now() - 3600000 * 24).toISOString(),
                    items: [
                        { name: "อาหารแมววิสการ์ส 1.2kg", price: 185, quantity: 2, image: "/image/713815-00-allonline-hg.jpg" }
                    ],
                    subtotal: 370,
                    shipping: 2,
                    total: 372,
                    deliveryMethod: "standard",
                    paymentMethod: "transfer",
                    status: "Preparing",
                    slipImage: null
                },
                {
                    id: 619283,
                    date: new Date(Date.now() - 3600000 * 48).toISOString(),
                    items: [
                        { name: "แชมพูสูตรกำจัดเห็บเหา 500ml", price: 250, quantity: 1, image: "/image/713815-00-allonline-hg.jpg" }
                    ],
                    subtotal: 250,
                    shipping: 5,
                    total: 255,
                    deliveryMethod: "express",
                    paymentMethod: "transfer",
                    status: "Shipping",
                    trackingNumber: "TH982341092",
                    slipImage: null
                },
                {
                    id: 510928,
                    date: new Date(Date.now() - 3600000 * 96).toISOString(),
                    items: [
                        { name: "ของเล่นคอนโดแมว 3 ชั้น", price: 890, quantity: 1, image: "/image/713815-00-allonline-hg.jpg" }
                    ],
                    subtotal: 890,
                    shipping: 5,
                    total: 895,
                    deliveryMethod: "express",
                    paymentMethod: "transfer",
                    status: "Completed",
                    slipImage: null
                }
            ];
            localStorage.setItem('myOrders', JSON.stringify(demoOrders));
        }
    }

    function updatePendingBadge() {
        const pBadge = document.getElementById('pendingBadge');
        const prepBadge = document.getElementById('preparingBadge');
        const shipBadge = document.getElementById('shippingBadge');

        const pendingCount = orders.filter(o => isStatusPending(o.status)).length;
        const preparingCount = orders.filter(o => isStatusPreparing(o.status)).length;
        const shippingCount = orders.filter(o => isStatusShipping(o.status)).length;

        if (pBadge) {
            if (pendingCount > 0) {
                pBadge.textContent = pendingCount;
                pBadge.classList.remove('hidden');
            } else {
                pBadge.classList.add('hidden');
            }
        }
        if (prepBadge) {
            if (preparingCount > 0) {
                prepBadge.textContent = preparingCount;
                prepBadge.classList.remove('hidden');
            } else {
                prepBadge.classList.add('hidden');
            }
        }
        if (shipBadge) {
            if (shippingCount > 0) {
                shipBadge.textContent = shippingCount;
                shipBadge.classList.remove('hidden');
            } else {
                shipBadge.classList.add('hidden');
            }
        }
    }

    function updateActiveTabUI() {
        tabBtns.forEach(btn => {
            if (btn.dataset.tab === currentTab) {
                btn.classList.add('bg-[#1b4332]', 'text-white', 'shadow-sm', 'border-[#1b4332]', 'font-bold');
                btn.classList.remove('bg-white', 'text-gray-700', 'hover:bg-gray-50', 'border-gray-200', 'font-medium');
                // Auto scroll active tab into view INSIDE the tabs container ONLY (never scroll window/body)
                setTimeout(() => {
                    const container = document.getElementById('orderTabs');
                    if (container && btn) {
                        const btnLeft = btn.offsetLeft;
                        const btnWidth = btn.offsetWidth;
                        const containerWidth = container.clientWidth;
                        const targetScroll = btnLeft - (containerWidth / 2) + (btnWidth / 2);
                        container.scrollTo({
                            left: Math.max(0, targetScroll),
                            behavior: 'smooth'
                        });
                    }
                    if (window.scrollX !== 0) {
                        window.scrollTo({ left: 0 });
                    }
                }, 80);
            } else {
                btn.classList.remove('bg-[#1b4332]', 'text-white', 'shadow-sm', 'border-[#1b4332]', 'font-bold');
                btn.classList.add('bg-white', 'text-gray-700', 'hover:bg-gray-50', 'border-gray-200', 'font-medium');
            }
        });
    }

    function renderOrders() {
        let filtered = orders;
        if (currentTab === 'pending_payment') {
            filtered = orders.filter(o => isStatusPending(o.status));
        } else if (currentTab === 'preparing') {
            filtered = orders.filter(o => isStatusPreparing(o.status));
        } else if (currentTab === 'shipping') {
            filtered = orders.filter(o => isStatusShipping(o.status));
        } else if (currentTab === 'completed') {
            filtered = orders.filter(o => isStatusCompleted(o.status));
        } else if (currentTab === 'cancelled') {
            filtered = orders.filter(o => isStatusCancelled(o.status));
        }

        if (filtered.length === 0) {
            ordersContainer.innerHTML = '';
            if (emptyOrders) emptyOrders.classList.remove('hidden');
            return;
        }

        if (emptyOrders) emptyOrders.classList.add('hidden');

        ordersContainer.innerHTML = filtered.map(order => {
            const statusConfig = getStatusBadge(order);
            const hasSlip = !!(order.has_slip || order.hasSlip || order.slipImage || order.slip_image);
            const isSlipRejected = Number(order.payment_status) === 2;
            const isSlipPendingReview = hasSlip && !isSlipRejected;

            const formattedDate = new Date(order.date).toLocaleDateString('th-TH', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });

            const firstItem = order.items && order.items.length > 0 ? order.items[0] : { name: "สินค้าในรายการ", price: order.total, quantity: 1, image: "/image/713815-00-allonline-hg.jpg" };
            const moreCount = (order.items || []).length - 1;

            return `
            <div class="bg-white rounded-2xl p-4 sm:p-5 md:p-6 shadow-sm border border-gray-100/80 hover:shadow-md transition-shadow">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-gray-100 pb-3 mb-3.5 text-xs sm:text-sm">
                    <div class="flex items-center space-x-2 sm:space-x-3">
                        <span class="font-bold text-gray-800 text-sm md:text-base">#${order.id}</span>
                        <span class="text-xs text-gray-400">•</span>
                        <span class="text-xs text-gray-500">${formattedDate}</span>
                    </div>
                    <span class="inline-flex items-center px-2 sm:px-2.5 py-1 rounded-full text-[11px] sm:text-xs font-semibold ${statusConfig.class}">
                        ${statusConfig.icon}
                        <span class="ml-1 sm:ml-1.5">${statusConfig.label}</span>
                    </span>
                </div>

                <!-- Product Preview -->
                <div class="flex items-center justify-between gap-3 sm:gap-4">
                    <div class="flex items-center space-x-3 sm:space-x-4 min-w-0">
                        <div class="w-14 h-14 sm:w-16 sm:h-16 bg-gray-50 rounded-xl flex-shrink-0 flex items-center justify-center p-1.5 border border-gray-100">
                            <img src="${escapeHTML(firstItem.image || '/image/713815-00-allonline-hg.jpg')}" onerror="this.src='/image/713815-00-allonline-hg.jpg'" alt="${escapeHTML(firstItem.name)}" class="w-full h-full object-contain">
                        </div>
                        <div class="min-w-0">
                            <h4 class="font-semibold text-gray-800 text-xs sm:text-sm truncate">${escapeHTML(firstItem.name)}</h4>
                            <p class="text-xs text-gray-400 mt-0.5">จำนวน: ${firstItem.quantity} ชิ้น ${moreCount > 0 ? `<span class="text-[#1b4332] font-medium">+ อีก ${moreCount} รายการ</span>` : ''}</p>
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="text-[11px] sm:text-xs text-gray-400">ยอดสุทธิ</div>
                        <div class="text-sm sm:text-base md:text-lg font-bold text-gray-800">฿${(parseFloat(order.total) || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between mt-3.5 pt-3 border-t border-gray-50 gap-2.5">
                    <div class="flex items-center text-xs">
                        ${order.status === 'Pending Payment' ? (
                            isSlipRejected ? `
                                <span class="text-xs text-rose-700 bg-rose-50 px-3 py-1.5 rounded-xl font-semibold border border-rose-200/80 flex items-center">
                                    <i class="fas fa-exclamation-circle mr-1.5 text-rose-500"></i>ชำระเงินไม่สำเร็จ / สลิปไม่ถูกต้อง (กรุณาแนบสลิปใหม่)
                                </span>
                            ` : isSlipPendingReview ? `
                                <span class="text-xs text-blue-700 bg-blue-50 px-3 py-1.5 rounded-xl font-semibold border border-blue-200/80 flex items-center">
                                    <i class="fas fa-hourglass-half mr-1.5 text-blue-500"></i>รอตรวจสอบการชำระเงิน (ร้านค้ากำลังตรวจสอบสลิป)
                                </span>
                            ` : `
                                <span class="text-xs text-amber-700 bg-amber-50 px-3 py-1.5 rounded-xl font-semibold border border-amber-200/80 flex items-center">
                                    <i class="fas fa-info-circle mr-1 text-amber-500"></i>ยังไม่ชำระเงิน (กรุณาชำระเงินและแนบสลิป)
                                </span>
                            `
                        ) : (order.status === 'Preparing' || order.status === 'Processing') ? `
                            <span class="text-xs text-emerald-700 bg-emerald-50 px-3 py-1.5 rounded-xl font-semibold border border-emerald-200/60 flex items-center">
                                <i class="fas fa-box-open mr-1.5 text-emerald-600"></i>ชำระเงินแล้ว • ร้านค้ากำลังแพ็คสินค้าเพื่อจัดส่ง
                            </span>
                        ` : (order.status === 'Shipping' || order.status === 'In Transit') ? `
                            <span class="text-xs text-sky-700 bg-sky-50 px-3 py-1.5 rounded-xl font-semibold border border-sky-200/60 flex items-center">
                                <i class="fas fa-truck mr-1.5 text-sky-600"></i>บริษัทขนส่งกำลังจัดส่งพัสดุ
                            </span>
                        ` : (order.status === 'Completed') ? `
                            <span class="text-xs text-teal-700 bg-teal-50 px-3 py-1.5 rounded-xl font-semibold border border-teal-200/60 flex items-center">
                                <i class="fas fa-check-circle mr-1.5 text-teal-600"></i>จัดส่งสำเร็จเรียบร้อย
                            </span>
                        ` : `
                            <span class="text-xs text-rose-600 flex items-center font-semibold">
                                <i class="fas fa-times-circle mr-1 text-rose-500"></i>ยกเลิกคำสั่งซื้อแล้ว
                            </span>
                        `}
                    </div>

                    <div class="flex items-center space-x-2 self-end sm:self-auto">
                        <button class="view-detail-btn px-3.5 py-1.5 sm:px-4 sm:py-2 rounded-xl text-xs font-semibold bg-gray-50 hover:bg-gray-100 text-gray-700 transition-colors cursor-pointer" data-id="${order.id}">
                            ดูรายละเอียด
                        </button>
                        ${(order.status === 'Shipping' || order.status === 'In Transit' || order.status === 'กำลังจัดส่ง' || order.status === 'ที่ต้องได้รับ') ? `
                            <button class="confirm-received-btn px-3.5 py-1.5 sm:px-4 sm:py-2 rounded-xl text-xs font-bold bg-[#1b4332] hover:bg-[#15803d] text-white shadow-sm transition-all flex items-center space-x-1.5 cursor-pointer" data-id="${order.id}">
                                <i class="fas fa-check-circle text-xs"></i>
                                <span>ยืนยันได้รับสินค้าแล้ว</span>
                            </button>
                        ` : ''}
                        ${order.status === 'Pending Payment' ? (
                            isSlipRejected ? `
                                <button class="cancel-order-btn px-3.5 py-1.5 sm:px-4 sm:py-2 rounded-xl text-xs font-semibold text-rose-600 bg-rose-50 hover:bg-rose-100 transition-all border border-rose-200 flex items-center space-x-1 cursor-pointer" data-id="${order.id}">
                                    <i class="fas fa-times-circle text-[11px]"></i>
                                    <span>ยกเลิกคำสั่งซื้อ</span>
                                </button>
                                <button class="pay-now-btn px-3.5 py-1.5 sm:px-4 sm:py-2 rounded-xl text-xs font-bold bg-[#1b4332] hover:bg-[#15803d] text-white shadow-sm transition-all flex items-center space-x-1.5 cursor-pointer" data-id="${order.id}">
                                    <i class="fas fa-file-upload"></i>
                                    <span>แนบสลิปใหม่</span>
                                </button>
                            ` : !hasSlip ? `
                                <button class="cancel-order-btn px-3.5 py-1.5 sm:px-4 sm:py-2 rounded-xl text-xs font-semibold text-rose-600 bg-rose-50 hover:bg-rose-100 transition-all border border-rose-200 flex items-center space-x-1 cursor-pointer" data-id="${order.id}">
                                    <i class="fas fa-times-circle text-[11px]"></i>
                                    <span>ยกเลิกคำสั่งซื้อ</span>
                                </button>
                                <button class="pay-now-btn px-3.5 py-1.5 sm:px-4 sm:py-2 rounded-xl text-xs font-bold bg-[#1b4332] hover:bg-[#15803d] text-white shadow-sm transition-all flex items-center space-x-1.5 cursor-pointer" data-id="${order.id}">
                                    <i class="fas fa-qrcode"></i>
                                    <span>ชำระเงิน</span>
                                </button>
                            ` : ''
                        ) : ''}
                        ${(order.status === 'Cancelled' || order.status === 'ยกเลิกแล้ว') && (order.slipImage || order.has_slip || order.wasPaid) ? `
                            <button class="show-refund-notice-btn px-3.5 py-1.5 sm:px-4 sm:py-2 rounded-xl text-xs font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 transition-all flex items-center space-x-1.5 shadow-2xs cursor-pointer" data-id="${order.id}">
                                <i class="fab fa-line text-emerald-600 text-sm"></i>
                                <span>แคปรูปขอเงินคืน</span>
                            </button>
                        ` : ''}
                    </div>
                </div>

                ${(order.status === 'Cancelled' || order.status === 'ยกเลิกแล้ว') && (order.slipImage || order.has_slip || order.wasPaid) ? `
                    <div class="mt-3 pt-2 border-t border-gray-100">
                        <div class="bg-rose-50/90 border border-rose-200/80 rounded-2xl p-3.5 text-left">
                            <div class="flex items-center justify-between mb-1.5 flex-wrap gap-2">
                                <span class="text-xs font-bold text-rose-700 flex items-center gap-1.5">
                                    <i class="fas fa-exclamation-triangle text-rose-500"></i>
                                    ออเดอร์ถูกยกเลิก (แจ้งขอรับเงินคืนผ่าน LINE)
                                </span>
                            </div>
                            <div class="text-[12px] text-rose-900 bg-white p-2.5 rounded-xl border border-rose-200/70 font-semibold leading-relaxed shadow-2xs mb-2">
                                "ให้แคปรูปภาพข้อความนี้เพื่อเป็นหลักฐานในการโอนเงินคืนผ่านทาง LINE โดยให้ลูกค้าส่งข้อความมาทาง LINE ร้าน"
                            </div>
                            <div class="flex items-center justify-between text-[11px] text-gray-500">
                                <span>ยอดเงินคืน: <b class="text-rose-600">฿${(parseFloat(order.total) || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</b></span>
                                <button class="show-refund-notice-btn font-bold text-emerald-700 hover:text-emerald-800 underline flex items-center gap-1 cursor-pointer" data-id="${order.id}">
                                    <i class="fab fa-line text-emerald-600"></i> แสดง QR Code แอด LINE
                                </button>
                            </div>
                        </div>
                    </div>
                ` : ''}
            </div>
            `;
        }).join('');

        // Attach buttons
        document.querySelectorAll('.view-detail-btn').forEach(btn => {
            btn.onclick = (e) => {
                const id = e.currentTarget.dataset.id;
                openDetailModal(id);
            };
        });

        document.querySelectorAll('.confirm-received-btn').forEach(btn => {
            btn.onclick = async (e) => {
                const id = e.currentTarget.dataset.id;
                await confirmOrderReceivedAction(id);
            };
        });

        document.querySelectorAll('.pay-now-btn').forEach(btn => {
            btn.onclick = (e) => {
                const id = e.currentTarget.dataset.id;
                openPayNowModal(id);
            };
        });

        document.querySelectorAll('.cancel-order-btn').forEach(btn => {
            btn.onclick = async (e) => {
                const id = e.currentTarget.dataset.id;
                await cancelOrderAction(id);
            };
        });

        document.querySelectorAll('.show-refund-notice-btn').forEach(btn => {
            btn.onclick = (e) => {
                const id = e.currentTarget.dataset.id;
                window.showCustomerRefundModal(id);
            };
        });
    }

    window.showCustomerRefundModal = function(orderId) {
        const order = orders.find(o => String(o.id) === String(orderId));
        const ordNum = order ? (order.number || `#${order.id}`) : `#${orderId}`;
        const amount = order ? (order.total || order.amount || 0) : 0;
        const formattedAmount = parseFloat(amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: '<span style="color: #e11d48; font-weight: 800; font-size: 20px;">⚠️ แจ้งหลักฐานการโอนเงินคืน</span>',
                html: `
                    <div style="text-align: center; font-family: 'Kanit', sans-serif;">
                        <div style="background: #fff1f2; border: 1px solid #fecdd3; border-radius: 14px; padding: 14px; margin-bottom: 16px; text-align: left;">
                            <div style="font-weight: 700; color: #9f1239; font-size: 13px; margin-bottom: 6px;">
                                📌 แคปรูปภาพหน้าจอนี้เพื่อเป็นหลักฐาน:
                            </div>
                            <div style="color: #881337; font-size: 13px; line-height: 1.5; font-weight: 700; background: white; padding: 12px; border-radius: 10px; border: 1px dashed #fda4af;">
                                "ให้แคปรูปภาพข้อความนี้เพื่อเป็นหลักฐานในการโอนเงินคืนผ่านทาง LINE โดยให้ลูกค้าส่งข้อความมาทาง LINE ร้าน"
                            </div>
                            <div style="margin-top: 12px; font-size: 12px; color: #334155; line-height: 1.6;">
                                <div><b>คำสั่งซื้อ:</b> ${ordNum}</div>
                                <div><b>ยอดเงินที่ต้องคืน:</b> <span style="color: #e11d48; font-weight: bold; font-size: 15px;">฿${formattedAmount}</span></div>
                            </div>
                        </div>
                        
                        <div style="font-weight: 700; color: #1e293b; font-size: 13px; margin-bottom: 8px;">
                            📲 QR Code สแกนเพิ่มเพื่อน LINE ร้านค้า
                        </div>
                        <div style="display: flex; justify-content: center; align-items: center; margin-bottom: 10px;">
                            <img src="/image/line_qr.png" alt="LINE QR Code" style="width: 170px; height: 170px; border-radius: 12px; border: 2px solid #06C755; padding: 6px; background: white; box-shadow: 0 4px 12px rgba(6,199,85,0.15);" onerror="this.src='/image/non-image.png'">
                        </div>
                        <a href="https://lin.ee/XxKOiGF" target="_blank" style="display: inline-flex; align-items: center; gap: 6px; color: #06C755; font-weight: 700; font-size: 13px; text-decoration: none; background: #f0fdf4; padding: 6px 16px; border-radius: 20px; border: 1px solid #bbf7d0;">
                            <i class="fab fa-line text-lg"></i> LINE Official: @HelloPetShop
                        </a>
                    </div>
                `,
                confirmButtonText: 'รับทราบ / ปิดหน้าต่าง',
                confirmButtonColor: '#1b4332',
                borderRadius: '20px',
                width: '460px'
            });
        } else {
            alert(`คำสั่งซื้อ ${ordNum} ถูกยกเลิกแล้ว\n\nยอดเงินคืน: ฿${formattedAmount}\n\nให้แคปรูปภาพข้อความนี้เพื่อเป็นหลักฐานในการโอนเงินคืนผ่านทาง LINE โดยให้ลูกค้าส่งข้อความมาทาง LINE ร้าน`);
        }
    };

    async function cancelOrderAction(orderId) {
        const targetOrder = orders.find(o => o.id == orderId);
        if (!targetOrder) return;

        const isSlipRejected = Number(targetOrder.payment_status) === 2;
        const hasSlip = !!(targetOrder.has_slip || targetOrder.hasSlip || targetOrder.slipImage || targetOrder.slip_image);
        const canCancel = targetOrder.status === 'Pending Payment' && (!hasSlip || isSlipRejected);

        if (!canCancel) {
            showToast("คำสั่งซื้อนี้ได้รับการชำระเงินแล้ว ไม่สามารถยกเลิกคำสั่งซื้อได้ด้วยตนเอง หากต้องการยกเลิกกรุณาติดต่อทางร้านผ่านช่องทาง LINE", "error");
            return;
        }

        let confirmed = false;
        if (typeof Swal !== 'undefined') {
            const res = await Swal.fire({
                title: 'ต้องการยกเลิกคำสั่งซื้อนี้หรือไม่?',
                text: `คุณต้องการยกเลิกคำสั่งซื้อ #${orderId} หรือไม่?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e11d48',
                cancelButtonColor: '#94a3b8',
                confirmButtonText: 'ยกเลิกคำสั่งซื้อ',
                cancelButtonText: 'กลับ',
                didOpen: () => {
                    const btn = Swal.getConfirmButton();
                    if (btn) {
                        btn.style.setProperty('background-color', '#e11d48', 'important');
                        btn.style.setProperty('box-shadow', '0 4px 6px -1px rgba(225, 29, 72, 0.25)', 'important');
                    }
                }
            });
            confirmed = res.isConfirmed;
        } else {
            confirmed = confirm('ต้องการยกเลิกคำสั่งซื้อนี้หรือไม่?');
        }

        if (!confirmed) return;

        try {
            const userStr = localStorage.getItem('user');
            const user = userStr ? JSON.parse(userStr) : null;
            const token = user?.csrf_token || localStorage.getItem('csrf_token') || '';
            const uid = user?.user_id || user?.customer_id || user?.id || '';

            const res = await fetch('/api/orders/update-status', {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    ...(token ? { 'X-CSRF-Token': token } : {}),
                    ...(uid ? { 'X-User-Id': String(uid) } : {}),
                    ...(user?.role_name ? { 'X-User-Role': String(user.role_name) } : {})
                },
                body: JSON.stringify({
                    order_id: parseInt(orderId),
                    customer_id: user?.customer_id || user?.id || null,
                    status: 'Cancelled',
                    cancel_reason: 'ลูกค้ายกเลิกคำสั่งซื้อผ่านหน้าประวัติคำสั่งซื้อ',
                    csrf_token: token
                })
            });

            let resData = {};
            try {
                resData = await res.json();
            } catch(e) {}

            if (res.ok || res.status === 200) {
                targetOrder.status = 'Cancelled';
                if (typeof saveUserOrdersData === 'function') {
                    saveUserOrdersData(orders);
                }
                updatePendingBadge();
                renderOrders();
                showToast(`ยกเลิกคำสั่งซื้อ #${orderId} เรียบร้อยแล้ว`, "success");
            } else {
                showToast(resData.message || 'เกิดข้อผิดพลาดในการยกเลิกคำสั่งซื้อ', "error");
            }
        } catch (err) {
            console.error("Cancel order error:", err);
            showToast(err.message || 'เกิดข้อผิดพลาดในการเชื่อมต่อกับเซิร์ฟเวอร์', "error");
        }
    }

    async function confirmOrderReceivedAction(orderId) {
        const order = orders.find(o => String(o.id) === String(orderId));
        const ordNum = order ? `#${order.id}` : `#${orderId}`;

        let isConfirmed = false;
        if (typeof Swal !== 'undefined') {
            const result = await Swal.fire({
                title: '<span style="color: #1b4332; font-weight: 800; font-size: 20px;">ยืนยันการรับสินค้า</span>',
                html: `
                    <div style="font-size: 14px; color: #475569; line-height: 1.6; margin-top: 8px;">
                        คุณได้รับสินค้าสำหรับคำสั่งซื้อ <b>${ordNum}</b> ครบถ้วนและอยู่ในสภาพสมบูรณ์เรียบร้อยแล้วใช่หรือไม่?
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-check-circle mr-1.5"></i> ยืนยัน ได้รับแล้ว',
                cancelButtonText: 'ยกเลิก',
                confirmButtonColor: '#1b4332',
                cancelButtonColor: '#94a3b8',
                reverseButtons: true
            });
            isConfirmed = result.isConfirmed;
        } else {
            isConfirmed = confirm(`คุณได้รับสินค้าสำหรับคำสั่งซื้อ ${ordNum} ครบถ้วนถูกต้องแล้วใช่หรือไม่?`);
        }

        if (!isConfirmed) return;

        try {
            const userStr = localStorage.getItem('user');
            const user = userStr ? JSON.parse(userStr) : null;
            const token = user?.csrf_token || localStorage.getItem('csrf_token') || '';
            const uid = user?.user_id || user?.customer_id || user?.id || '';

            const res = await fetch('/api/orders/update-status', {
                method: 'POST',
                credentials: 'include',
                headers: { 
                    'Content-Type': 'application/json',
                    ...(token ? { 'X-CSRF-Token': token } : {}),
                    ...(uid ? { 'X-User-Id': String(uid) } : {}),
                    ...(user?.role_name ? { 'X-User-Role': String(user.role_name) } : {})
                },
                body: JSON.stringify({
                    order_id: parseInt(orderId),
                    status: 'Completed',
                    csrf_token: token
                })
            });

            let resData = {};
            try {
                resData = await res.json();
            } catch(e) {}

            const isSuccess = res.ok || res.status === 200 || resData.status === 200 || (resData.message && resData.message.includes('already completed'));

            if (isSuccess) {
                // Update local orders list & persistent storage
                const targetOrder = orders.find(o => String(o.id) === String(orderId));
                if (targetOrder) {
                    targetOrder.status = 'Completed';
                    if (typeof saveUserOrdersData === 'function') {
                        saveUserOrdersData(orders);
                    }
                }
                updatePendingBadge();
                renderOrders();

                // If detail modal is open, re-render modal with new status
                if (orderDetailModal && !orderDetailModal.classList.contains('hidden') && orderDetailModal.style.display !== 'none') {
                    openDetailModal(orderId);
                }

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: '<span style="color: #1b4332; font-weight: 800;">จัดส่งสำเร็จ!</span>',
                        html: `
                            <div style="font-size: 14px; color: #475569; line-height: 1.6;">
                                บันทึกสถานะคำสั่งซื้อ <b>${ordNum}</b> เป็น <span style="color: #0d9488; font-weight: bold;">"จัดส่งสำเร็จ"</span> เรียบร้อยแล้ว<br>
                                <span style="font-size: 12px; color: #64748b; margin-top: 6px; display: block;">ขอบคุณที่เลือกซื้อสินค้ากับ Hello Pet Shop 🐶🐱</span>
                            </div>
                        `,
                        icon: 'success',
                        confirmButtonColor: '#1b4332',
                        confirmButtonText: 'ตกลง'
                    });
                } else {
                    showToast(`บันทึกคำสั่งซื้อ ${ordNum} จัดส่งสำเร็จเรียบร้อยแล้ว`, "success");
                }
            } else {
                showToast(resData.message || 'เกิดข้อผิดพลาดในการยืนยันรับสินค้า', "error");
            }
        } catch (err) {
            console.error("Confirm received error:", err);
            // Re-sync with API to check if status was updated on database
            try {
                const checkRes = await fetch('/api/orders', { credentials: 'include' });
                if (checkRes.ok) {
                    const checkData = await checkRes.json();
                    const found = (checkData.data || []).find(o => String(o.id || o.order_id) === String(orderId));
                    if (found && (found.status === 'Completed' || found.status === 4)) {
                        const targetOrder = orders.find(o => String(o.id) === String(orderId));
                        if (targetOrder) {
                            targetOrder.status = 'Completed';
                            if (typeof saveUserOrdersData === 'function') {
                                saveUserOrdersData(orders);
                            }
                        }
                        updatePendingBadge();
                        renderOrders();
                        showToast(`บันทึกคำสั่งซื้อ ${ordNum} จัดส่งสำเร็จเรียบร้อยแล้ว`, "success");
                        return;
                    }
                }
            } catch (reCheckErr) {}

            showToast(err.message || 'เกิดข้อผิดพลาดในการเชื่อมต่อกับเซิร์ฟเวอร์', "error");
        }
    }

    function getStatusBadge(orderOrStatus) {
        let s = '';
        let hasSlip = false;
        let paymentStatus = null;

        if (typeof orderOrStatus === 'object' && orderOrStatus !== null) {
            s = String(orderOrStatus.status || '').toLowerCase().trim();
            hasSlip = !!(orderOrStatus.has_slip || orderOrStatus.hasSlip || orderOrStatus.slip_image || orderOrStatus.slipImage);
            paymentStatus = orderOrStatus.payment_status !== undefined && orderOrStatus.payment_status !== null ? Number(orderOrStatus.payment_status) : null;
        } else {
            s = String(orderOrStatus || '').toLowerCase().trim();
        }

        // 1. Pending payment (Status 1)
        if (s === '1' || s.includes('pending') || s.includes('ที่ต้องชำระ') || s.includes('unpaid') || s.includes('รอดำเนินการ')) {
            if (paymentStatus === 2 || s.includes('reject') || s.includes('ปฏิเสธ')) {
                return { 
                    label: 'ชำระเงินไม่สำเร็จ (กรุณาแนบสลิปใหม่)', 
                    class: 'bg-rose-50 text-rose-700 border border-rose-200/80', 
                    icon: '<i class="fas fa-exclamation-circle text-[10px]"></i>' 
                };
            }
            if (hasSlip) {
                return { 
                    label: 'รอตรวจสอบการชำระเงิน', 
                    class: 'bg-blue-50 text-blue-700 border border-blue-200/80', 
                    icon: '<i class="fas fa-hourglass-half text-[10px]"></i>' 
                };
            }
            return { 
                label: 'ที่ต้องชำระ', 
                class: 'bg-amber-50 text-amber-700 border border-amber-200/80', 
                icon: '<i class="fas fa-clock text-[10px]"></i>' 
            };
        }

        // 2. Preparing / Processing (Status 2 - Paid & verified)
        if (s === '2' || s.includes('preparing') || s.includes('processing') || s.includes('กำลังแพ็คสินค้า') || s.includes('ที่ต้องจัดส่ง') || s.includes('จัดเตรียม')) {
            return { 
                label: 'กำลังแพ็คสินค้า', 
                class: 'bg-emerald-50 text-emerald-700 border border-emerald-200/80', 
                icon: '<i class="fas fa-box-open text-[10px]"></i>' 
            };
        }

        // 3. Shipping / In Transit (Status 3)
        if (s === '3' || s.includes('shipping') || s.includes('transit') || s.includes('กำลังจัดส่ง') || s.includes('ที่ต้องได้รับ') || s.includes('ส่งแล้ว')) {
            return { 
                label: 'กำลังจัดส่ง', 
                class: 'bg-sky-50 text-sky-700 border border-sky-200/80', 
                icon: '<i class="fas fa-truck text-[10px]"></i>' 
            };
        }

        // 4. Completed (Status 4)
        if (s === '4' || s.includes('completed') || s.includes('success') || s.includes('จัดส่งสำเร็จ') || s.includes('สำเร็จแล้ว') || s.includes('สำเร็จ')) {
            return { 
                label: 'จัดส่งสำเร็จ', 
                class: 'bg-teal-50 text-teal-700 border border-teal-200/80', 
                icon: '<i class="fas fa-check-circle text-[10px]"></i>' 
            };
        }

        // 5. Cancelled (Status 5)
        if (s === '5' || s.includes('cancel') || s.includes('ยกเลิก')) {
            return { 
                label: 'ยกเลิกแล้ว', 
                class: 'bg-gray-100 text-gray-600 border border-gray-200', 
                icon: '<i class="fas fa-times-circle text-[10px]"></i>' 
            };
        }

        return { label: s, class: 'bg-gray-100 text-gray-600 border border-gray-200', icon: '' };
    }

    function openDetailModal(orderId) {
        const order = orders.find(o => o.id == orderId);
        if (!order) return;

        if (modalOrderId) modalOrderId.textContent = `#${order.id}`;
        if (modalDate) modalDate.textContent = new Date(order.date).toLocaleString('th-TH');
        if (modalStatus) {
            const statusConfig = getStatusBadge(order);
            modalStatus.innerHTML = `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ${statusConfig.class}">${statusConfig.icon}<span class="ml-1">${statusConfig.label}</span></span>`;
        }
        if (modalPayment) modalPayment.textContent = order.paymentMethod === 'promptpay' ? 'PromptPay QR' : 'โอนผ่านธนาคาร';
        if (modalShipping) modalShipping.textContent = order.deliveryMethod || 'Standard Express';

        if (modalItems) {
            modalItems.innerHTML = (order.items || []).map(item => `
                <div class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
                    <div class="flex items-center space-x-3">
                        <img src="${escapeHTML(item.image || '/image/713815-00-allonline-hg.jpg')}" onerror="this.src='/image/713815-00-allonline-hg.jpg'" class="w-10 h-10 object-contain rounded-lg border border-gray-100">
                        <div>
                            <div class="text-xs font-semibold text-gray-800">${escapeHTML(item.name)}</div>
                            <div class="text-[11px] text-gray-400">จำนวน: ${item.quantity}</div>
                        </div>
                    </div>
                    <span class="text-xs font-bold text-gray-800">฿${(item.price * item.quantity).toFixed(2)}</span>
                </div>
            `).join('');
        }

        if (modalSubtotal) modalSubtotal.textContent = `฿${(parseFloat(order.subtotal) || 0).toFixed(2)}`;
        if (modalShippingFee) modalShippingFee.textContent = `฿${(parseFloat(order.shipping) || 0).toFixed(2)}`;
        if (modalTotal) modalTotal.textContent = `฿${(parseFloat(order.total) || 0).toFixed(2)}`;

        // Dynamic modal actions
        const modalDynamicActions = document.getElementById('modalDynamicActions');
        if (modalDynamicActions) {
            let actionsHtml = '';
            const hasSlip = !!(order.has_slip || order.hasSlip || order.slipImage || order.slip_image);
            const isSlipRejected = Number(order.payment_status) === 2;

            if (order.status === 'Shipping' || order.status === 'In Transit' || order.status === 'กำลังจัดส่ง' || order.status === 'ที่ต้องได้รับ') {
                actionsHtml += `
                    <button type="button" class="modal-confirm-received-btn px-4 py-2 rounded-xl text-xs font-bold bg-[#1b4332] hover:bg-[#15803d] text-white shadow-sm transition-all flex items-center space-x-1.5 cursor-pointer" data-id="${order.id}">
                        <i class="fas fa-check-circle"></i>
                        <span>ยืนยันได้รับสินค้าแล้ว</span>
                    </button>
                `;
            } else if (order.status === 'Pending Payment') {
                if (isSlipRejected) {
                    actionsHtml += `
                        <button type="button" class="modal-cancel-order-btn px-4 py-2 rounded-xl text-xs font-semibold text-rose-600 bg-rose-50 hover:bg-rose-100 transition-all border border-rose-200 flex items-center space-x-1 cursor-pointer" data-id="${order.id}">
                            <i class="fas fa-times-circle text-[11px]"></i>
                            <span>ยกเลิกคำสั่งซื้อ</span>
                        </button>
                        <button type="button" class="modal-pay-now-btn px-4 py-2 rounded-xl text-xs font-bold bg-[#1b4332] hover:bg-[#15803d] text-white shadow-sm transition-all flex items-center space-x-1.5 cursor-pointer" data-id="${order.id}">
                            <i class="fas fa-file-upload"></i>
                            <span>แนบสลิปใหม่</span>
                        </button>
                    `;
                } else if (!hasSlip) {
                    actionsHtml += `
                        <button type="button" class="modal-cancel-order-btn px-4 py-2 rounded-xl text-xs font-semibold text-rose-600 bg-rose-50 hover:bg-rose-100 transition-all border border-rose-200 flex items-center space-x-1 cursor-pointer" data-id="${order.id}">
                            <i class="fas fa-times-circle text-[11px]"></i>
                            <span>ยกเลิกคำสั่งซื้อ</span>
                        </button>
                        <button type="button" class="modal-pay-now-btn px-4 py-2 rounded-xl text-xs font-bold bg-[#1b4332] hover:bg-[#15803d] text-white shadow-sm transition-all flex items-center space-x-1.5 cursor-pointer" data-id="${order.id}">
                            <i class="fas fa-qrcode"></i>
                            <span>ชำระเงิน</span>
                        </button>
                    `;
                } else {
                    actionsHtml += `
                        <span class="text-xs text-blue-700 bg-blue-50 px-3 py-1.5 rounded-xl font-semibold border border-blue-200 flex items-center">
                            <i class="fas fa-hourglass-half mr-1.5 text-blue-500"></i>ร้านค้ากำลังตรวจสอบสลิป
                        </span>
                    `;
                }
            }
            modalDynamicActions.innerHTML = actionsHtml;

            const modalCancelBtn = modalDynamicActions.querySelector('.modal-cancel-order-btn');
            if (modalCancelBtn) {
                modalCancelBtn.onclick = async () => {
                    if (orderDetailModal) {
                        orderDetailModal.classList.add('opacity-0', 'pointer-events-none');
                        orderDetailModal.classList.add('hidden');
                        orderDetailModal.style.display = 'none';
                    }
                    await cancelOrderAction(order.id);
                };
            }

            const modalConfirmBtn = modalDynamicActions.querySelector('.modal-confirm-received-btn');
            if (modalConfirmBtn) {
                modalConfirmBtn.onclick = async () => {
                    await confirmOrderReceivedAction(order.id);
                };
            }
            const modalPayBtn = modalDynamicActions.querySelector('.modal-pay-now-btn');
            if (modalPayBtn) {
                modalPayBtn.onclick = () => {
                    if (orderDetailModal) {
                        orderDetailModal.classList.add('opacity-0', 'pointer-events-none');
                        orderDetailModal.classList.add('hidden');
                        orderDetailModal.style.display = 'none';
                    }
                    openPayNowModal(order.id);
                };
            }
        }

        const modalCloseActionBtn = document.getElementById('modalCloseActionBtn');
        if (modalCloseActionBtn) {
            modalCloseActionBtn.onclick = () => {
                if (!orderDetailModal) return;
                orderDetailModal.classList.add('opacity-0', 'pointer-events-none');
                orderDetailModal.querySelector('div')?.classList.remove('scale-100');
                orderDetailModal.querySelector('div')?.classList.add('scale-95');
                setTimeout(() => {
                    orderDetailModal.classList.add('hidden');
                    orderDetailModal.style.display = 'none';
                }, 300);
            };
        }

        if (orderDetailModal) {
            orderDetailModal.classList.remove('hidden');
            orderDetailModal.style.display = 'flex';
            requestAnimationFrame(() => {
                orderDetailModal.classList.remove('opacity-0', 'pointer-events-none');
                orderDetailModal.querySelector('div')?.classList.remove('scale-95');
                orderDetailModal.querySelector('div')?.classList.add('scale-100');
            });
        }
    }

    function updateHistoryPayBtnState() {
        const btn = document.getElementById('confirmPayNowBtn') || confirmPayNowBtn;
        if (!btn) return;
        btn.disabled = false;
        if (attachedSlipData) {
            btn.className = "w-full bg-[#1b4332] hover:bg-[#15803d] text-white py-3.5 rounded-xl font-bold text-sm shadow-md hover:shadow-lg ring-2 ring-emerald-400/60 transition-all flex items-center justify-center space-x-2 cursor-pointer active:scale-[0.99]";
        } else {
            btn.className = "w-full bg-[#1b4332] hover:bg-[#15803d] text-white py-3.5 rounded-xl font-bold text-sm shadow-md hover:shadow-lg transition-all flex items-center justify-center space-x-2 cursor-pointer active:scale-[0.99]";
        }
    }

    function openPayNowModal(orderId) {
        const order = orders.find(o => String(o.id) === String(orderId));
        if (!order) {
            showToast("ไม่พบข้อมูลคำสั่งซื้อ", "error");
            return;
        }

        currentPayingOrder = order;
        if (payModalOrderId) payModalOrderId.textContent = `#${order.id}`;
        const totalNum = parseFloat(order.total || 0);
        if (payModalAmount) payModalAmount.textContent = `฿${totalNum.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

        if (paymentSettings) {
            const qrImg = document.getElementById('payModalQrImg');
            const accName = document.getElementById('payModalAccountName');
            const bankNum = document.getElementById('payModalBankAndNumber');
            const instructions = document.getElementById('payModalInstructions');

            if (qrImg && paymentSettings.qr_image_url) qrImg.src = paymentSettings.qr_image_url;
            if (accName && paymentSettings.account_name) accName.textContent = `ชื่อบัญชี: ${paymentSettings.account_name}`;
            if (bankNum && paymentSettings.bank_name) bankNum.textContent = `${paymentSettings.bank_name} • บัญชี: ${paymentSettings.account_number}`;
            if (instructions) instructions.textContent = paymentSettings.instructions || '';
        }

        attachedSlipData = null;
        if (payModalSlipInput) payModalSlipInput.value = '';
        if (payModalPreview) payModalPreview.classList.add('hidden');
        if (payModalPlaceholder) payModalPlaceholder.classList.remove('hidden');
        updateHistoryPayBtnState();

        if (payNowModal) {
            payNowModal.classList.remove('hidden');
            payNowModal.style.display = 'flex';
            requestAnimationFrame(() => {
                payNowModal.classList.remove('opacity-0', 'pointer-events-none');
                payNowModal.querySelector('div')?.classList.remove('scale-95');
                payNowModal.querySelector('div')?.classList.add('scale-100');
            });
        }
    }

    function closePayNowModal() {
        if (!payNowModal) return;
        payNowModal.classList.add('opacity-0', 'pointer-events-none');
        payNowModal.querySelector('div')?.classList.remove('scale-100');
        payNowModal.querySelector('div')?.classList.add('scale-95');
        setTimeout(() => {
            payNowModal.classList.add('hidden');
            payNowModal.style.display = 'none';
        }, 300);
        currentPayingOrder = null;
    }

    function compressSlipImage(file) {
        return new Promise((resolve) => {
            if (!file || !file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (e) => resolve(e.target.result);
                reader.onerror = () => resolve(null);
                reader.readAsDataURL(file);
                return;
            }

            const reader = new FileReader();
            reader.onload = (event) => {
                const img = new Image();
                img.onload = () => {
                    const maxWidth = 1200;
                    const maxHeight = 1200;
                    let width = img.width;
                    let height = img.height;

                    if (width > maxWidth || height > maxHeight) {
                        if (width > height) {
                            height = Math.round((height * maxWidth) / width);
                            width = maxWidth;
                        } else {
                            width = Math.round((width * maxHeight) / height);
                            height = maxHeight;
                        }
                    }

                    const canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);

                    const compressedBase64 = canvas.toDataURL('image/jpeg', 0.82);
                    resolve(compressedBase64);
                };
                img.onerror = () => resolve(event.target.result);
                img.src = event.target.result;
            };
            reader.onerror = () => resolve(null);
            reader.readAsDataURL(file);
        });
    }

    // Slip file input inside pay now modal
    if (payModalSlipInput) {
        payModalSlipInput.onchange = async (e) => {
            const file = e.target.files[0];
            if (file) {
                if (payModalPlaceholder) payModalPlaceholder.innerHTML = '<i class="fas fa-circle-notch fa-spin text-2xl text-[#1b4332]"></i><span class="text-xs text-gray-500 mt-2">กำลังประมวลผลรูปภาพ...</span>';
                const compressed = await compressSlipImage(file);
                attachedSlipData = compressed;
                if (payModalFileName) payModalFileName.textContent = file.name;
                if (payModalPlaceholder) {
                    payModalPlaceholder.classList.add('hidden');
                    payModalPlaceholder.innerHTML = '<i class="fas fa-cloud-upload-alt text-2xl text-gray-400 mb-2"></i><span class="text-xs font-semibold text-gray-700">คลิกเพื่ออัปโหลดสลิป</span><span class="text-[11px] text-gray-400 mt-0.5">รองรับไฟล์ JPG, PNG</span>';
                }
                if (payModalPreview) payModalPreview.classList.remove('hidden');
                updateHistoryPayBtnState();
            }
        };
    }

    if (payModalRemoveSlip) {
        payModalRemoveSlip.onclick = (e) => {
            e.stopPropagation();
            if (payModalSlipInput) payModalSlipInput.value = '';
            attachedSlipData = null;
            if (payModalPreview) payModalPreview.classList.add('hidden');
            if (payModalPlaceholder) payModalPlaceholder.classList.remove('hidden');
            updateHistoryPayBtnState();
        };
    }

    if (confirmPayNowBtn) {
        confirmPayNowBtn.onclick = async () => {
            if (!currentPayingOrder) return;

            // Validate that slip image is attached
            if (!attachedSlipData) {
                showToast("กรุณาแนบรูปภาพสลิปการโอนเงินก่อนยืนยันการชำระเงิน", "error");
                const dropzone = document.getElementById('payModalPlaceholder')?.parentElement;
                if (dropzone) {
                    dropzone.classList.add('border-red-500', 'bg-red-50/50');
                    setTimeout(() => dropzone.classList.remove('border-red-500', 'bg-red-50/50'), 2500);
                }
                return;
            }

            confirmPayNowBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i><span>กำลังบันทึก...</span>';
            confirmPayNowBtn.disabled = true;

            try {
                if (attachedSlipData) {
                    const res = await fetch('/api/orders/upload-slip', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            order_id: currentPayingOrder.id,
                            slip_image: attachedSlipData
                        })
                    });
                    if (res.ok) {
                        const resData = await res.json().catch(() => ({}));
                        currentPayingOrder.payment_status = 0;
                        currentPayingOrder.has_slip = true;
                        currentPayingOrder.hasSlip = true;
                        if (resData.slip_image) {
                            currentPayingOrder.slip_image = resData.slip_image;
                        }
                    }
                }
            } catch (err) {
                console.warn("Backend slip sync note:", err);
            }

            currentPayingOrder.status = 'Pending Payment';
            currentPayingOrder.slipImage = null; // NEVER save Base64 into frontend order objects
            currentPayingOrder.has_slip = true;
            currentPayingOrder.hasSlip = true;
            currentPayingOrder.payment_status = 0;
            currentPayingOrder.paidAt = new Date().toISOString();

            saveUserOrdersData(orders);

            closePayNowModal();
            confirmPayNowBtn.innerHTML = '<i class="fas fa-check-circle"></i><span>ยืนยันการชำระเงิน</span>';
            confirmPayNowBtn.disabled = false;
            updateHistoryPayBtnState();

            updatePendingBadge();
            renderOrders();

            showToast(`แจ้งชำระเงินคำสั่งซื้อ #${currentPayingOrder.id} สำเร็จ! รอร้านค้าตรวจสอบสลิป`, "success");
        };
    }

    // Tabs events
    tabBtns.forEach(btn => {
        btn.onclick = () => {
            currentTab = btn.dataset.tab;
            updateActiveTabUI();
            renderOrders();
        };
    });

    // Horizontal Scrolling Controls & Touch / Drag for Tabs
    function setupTabsScrolling() {
        const tabs = document.getElementById('orderTabs');
        const leftBtn = document.getElementById('tabScrollLeft');
        const rightBtn = document.getElementById('tabScrollRight');
        if (!tabs) return;

        if (leftBtn) {
            leftBtn.onclick = () => tabs.scrollBy({ left: -220, behavior: 'smooth' });
        }
        if (rightBtn) {
            rightBtn.onclick = () => tabs.scrollBy({ left: 220, behavior: 'smooth' });
        }

        const updateArrows = () => {
            if (leftBtn) {
                if (tabs.scrollLeft > 15) {
                    leftBtn.classList.remove('opacity-0', 'pointer-events-none');
                } else {
                    leftBtn.classList.add('opacity-0', 'pointer-events-none');
                }
            }
            if (rightBtn) {
                const maxScroll = tabs.scrollWidth - tabs.clientWidth - 15;
                if (tabs.scrollLeft < maxScroll) {
                    rightBtn.classList.remove('opacity-0', 'pointer-events-none');
                } else {
                    rightBtn.classList.add('opacity-0', 'pointer-events-none');
                }
            }
        };

        tabs.addEventListener('scroll', updateArrows, { passive: true });
        window.addEventListener('resize', updateArrows);
        setTimeout(updateArrows, 150);

        // Mouse drag scrolling
        let isDown = false;
        let startX = 0;
        let scrollStart = 0;

        tabs.addEventListener('mousedown', (e) => {
            isDown = true;
            tabs.classList.add('cursor-grabbing');
            tabs.classList.remove('cursor-grab');
            startX = e.pageX - tabs.offsetLeft;
            scrollStart = tabs.scrollLeft;
        });

        tabs.addEventListener('mouseleave', () => {
            isDown = false;
            tabs.classList.remove('cursor-grabbing');
            tabs.classList.add('cursor-grab');
        });

        tabs.addEventListener('mouseup', () => {
            isDown = false;
            tabs.classList.remove('cursor-grabbing');
            tabs.classList.add('cursor-grab');
        });

        tabs.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - tabs.offsetLeft;
            const walk = (x - startX) * 1.5;
            tabs.scrollLeft = scrollStart - walk;
        });
    }

    setupTabsScrolling();

    if (closeModalBtn) {
        closeModalBtn.onclick = () => {
            if (!orderDetailModal) return;
            orderDetailModal.classList.add('opacity-0', 'pointer-events-none');
            orderDetailModal.querySelector('div')?.classList.remove('scale-100');
            orderDetailModal.querySelector('div')?.classList.add('scale-95');
            setTimeout(() => {
                orderDetailModal.classList.add('hidden');
                orderDetailModal.style.display = 'none';
            }, 300);
        };
    }

    if (closePayModalBtn) {
        closePayModalBtn.onclick = closePayNowModal;
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initOrderHistoryPage);
} else {
    initOrderHistoryPage();
}
