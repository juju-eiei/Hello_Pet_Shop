import { showToast, escapeHTML, getUserOrdersData, saveUserOrdersData } from './utils.js';

export function initOrderHistoryPage() {
    const cleanPath = (window.location.pathname || '').toLowerCase();
    const isStaffOrAdmin = cleanPath.includes('/staff') || cleanPath.includes('/admin') || cleanPath.includes('staff_') || cleanPath.includes('admin_');
    const isCustomerOrders = cleanPath.includes('/orders') || cleanPath.includes('/order-history') || cleanPath.includes('order-history.html');
    const emptyOrders = document.getElementById('emptyOrders');
    const ordersContainer = document.getElementById('ordersContainer');

    // Strict guard: Never run on staff or admin pages, and only run on customer order history
    if (isStaffOrAdmin || !isCustomerOrders || !emptyOrders || !ordersContainer) return;
    const orderDetailModal = document.getElementById('orderDetailModal');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const pendingBadge = document.getElementById('pendingBadge');

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
        seedDemoOrdersIfEmpty();
        fetchPaymentSettings();
        
        let localOrders = getUserOrdersData();
        
        try {
            const res = await fetch('/api/orders');
            if (res.ok) {
                const apiRes = await res.json();
                if (apiRes.data && Array.isArray(apiRes.data)) {
                    const mappedApiOrders = apiRes.data.map(o => ({
                        id: o.order_id || o.id,
                        date: o.order_date || o.created_at || new Date().toISOString(),
                        status: mapDbStatusToUi(o.status),
                        items: (o.items || []).map(i => ({
                            name: i.product_name,
                            price: parseFloat(i.unit_price || i.price),
                            quantity: parseInt(i.quantity),
                            image: i.image_url || '/image/713815-00-allonline-hg.jpg'
                        })),
                        subtotal: parseFloat(o.total_amount) - (parseFloat(o.shipping_fee) || 0),
                        shipping: parseFloat(o.shipping_fee) || 0,
                        total: parseFloat(o.total_amount),
                        deliveryMethod: o.company_name || 'Standard Express',
                        paymentMethod: o.payment_method || 'transfer',
                        slipImage: o.slip_image || null
                    }));

                    const merged = [...mappedApiOrders];
                    localOrders.forEach(lo => {
                        if (!merged.some(mo => String(mo.id) === String(lo.id))) {
                            merged.push(lo);
                        }
                    });
                    localOrders = merged;
                }
            }
        } catch (e) {
            console.warn("Backend orders sync note:", e);
        }

        orders = localOrders;
        saveUserOrdersData(orders);
        updatePendingBadge();
        renderOrders();
        updateActiveTabUI();
    }

    function mapDbStatusToUi(dbStatus) {
        if (dbStatus === null || dbStatus === undefined) return 'Pending Payment';
        const s = String(dbStatus).toLowerCase();
        if (s === '1' || s.includes('pending') || s.includes('unpaid') || s.includes('ที่ต้องชำระ')) return 'Pending Payment';
        if (s === '2' || s.includes('preparing') || s.includes('paid') || s.includes('ที่ต้องจัดส่ง') || s.includes('จัดเตรียม')) return 'Preparing';
        if (s === '3' || s.includes('shipping') || s.includes('shipped') || s.includes('กำลังจัดส่ง') || s.includes('ส่งแล้ว')) return 'Shipping';
        if (s === '4' || s.includes('completed') || s.includes('success') || s.includes('สำเร็จ')) return 'Completed';
        if (s === '5' || s.includes('cancel') || s.includes('ยกเลิก')) return 'Cancelled';
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
                    shipping: 0,
                    total: 320,
                    deliveryMethod: "standard",
                    paymentMethod: "transfer",
                    status: "Pending Payment",
                    slipImage: null
                }
            ];
            localStorage.setItem('myOrders', JSON.stringify(demoOrders));
        }
    }

    function updatePendingBadge() {
        const pendingCount = orders.filter(o => o.status === 'Pending Payment').length;
        if (pendingBadge) {
            if (pendingCount > 0) {
                pendingBadge.textContent = pendingCount;
                pendingBadge.classList.remove('hidden');
            } else {
                pendingBadge.classList.add('hidden');
            }
        }
    }

    function updateActiveTabUI() {
        tabBtns.forEach(btn => {
            if (btn.dataset.tab === currentTab) {
                btn.classList.add('bg-[#4D7C68]', 'text-white', 'shadow-sm', 'border-[#4D7C68]');
                btn.classList.remove('bg-white', 'text-gray-600', 'hover:bg-gray-100', 'border-gray-200');
            } else {
                btn.classList.remove('bg-[#4D7C68]', 'text-white', 'shadow-sm', 'border-[#4D7C68]');
                btn.classList.add('bg-white', 'text-gray-600', 'hover:bg-gray-100', 'border-gray-200');
            }
        });
    }

    function renderOrders() {
        let filtered = orders;
        if (currentTab === 'pending_payment') {
            filtered = orders.filter(o => o.status === 'Pending Payment');
        } else if (currentTab === 'preparing') {
            filtered = orders.filter(o => o.status === 'Preparing');
        } else if (currentTab === 'shipping') {
            filtered = orders.filter(o => o.status === 'Shipping');
        } else if (currentTab === 'completed') {
            filtered = orders.filter(o => o.status === 'Completed' || o.status === 'สำเร็จแล้ว');
        } else if (currentTab === 'cancelled') {
            filtered = orders.filter(o => o.status === 'Cancelled' || o.status === 'ยกเลิกแล้ว' || o.status === 'ยกเลิก');
        }

        if (filtered.length === 0) {
            ordersContainer.innerHTML = '';
            if (emptyOrders) emptyOrders.classList.remove('hidden');
            return;
        }

        if (emptyOrders) emptyOrders.classList.add('hidden');

        ordersContainer.innerHTML = filtered.map(order => {
            const statusConfig = getStatusBadge(order.status);
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
            <div class="bg-white rounded-2xl p-5 md:p-6 shadow-sm border border-gray-100/80 hover:shadow-md transition-shadow">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-gray-100 pb-3.5 mb-4">
                    <div class="flex items-center space-x-3">
                        <span class="font-bold text-gray-800 text-sm md:text-base">#${order.id}</span>
                        <span class="text-xs text-gray-400">•</span>
                        <span class="text-xs text-gray-500">${formattedDate}</span>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold ${statusConfig.class}">
                        ${statusConfig.icon}
                        <span class="ml-1.5">${statusConfig.label}</span>
                    </span>
                </div>

                <!-- Product Preview -->
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center space-x-4 min-w-0">
                        <div class="w-16 h-16 bg-gray-50 rounded-xl flex-shrink-0 flex items-center justify-center p-2 border border-gray-100">
                            <img src="${escapeHTML(firstItem.image || '/image/713815-00-allonline-hg.jpg')}" onerror="this.src='/image/713815-00-allonline-hg.jpg'" alt="${escapeHTML(firstItem.name)}" class="w-full h-full object-contain">
                        </div>
                        <div class="min-w-0">
                            <h4 class="font-semibold text-gray-800 text-sm truncate">${escapeHTML(firstItem.name)}</h4>
                            <p class="text-xs text-gray-400 mt-0.5">จำนวน: ${firstItem.quantity} ชิ้น ${moreCount > 0 ? `<span class="text-[#4D7C68] font-medium">+ อีก ${moreCount} รายการ</span>` : ''}</p>
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="text-xs text-gray-400">ยอดสุทธิ</div>
                        <div class="text-base md:text-lg font-bold text-gray-800">฿${parseFloat(order.total).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end space-x-2.5 mt-4 pt-3.5 border-t border-gray-50">
                    <button class="view-detail-btn px-4 py-2 rounded-xl text-xs font-semibold bg-gray-50 hover:bg-gray-100 text-gray-700 transition-colors" data-id="${order.id}">
                        ดูรายละเอียด
                    </button>
                    ${order.status === 'Pending Payment' ? (
                        !order.slipImage ? `
                            <button class="cancel-order-btn px-4 py-2 rounded-xl text-xs font-semibold text-rose-600 bg-rose-50 hover:bg-rose-100 transition-all border border-rose-200 flex items-center space-x-1" data-id="${order.id}">
                                <i class="fas fa-times-circle text-[11px]"></i>
                                <span>ยกเลิกคำสั่งซื้อ</span>
                            </button>
                            <button class="pay-now-btn px-4 py-2 rounded-xl text-xs font-bold bg-[#4D7C68] hover:bg-[#3D6353] text-white shadow-sm transition-all flex items-center space-x-1.5" data-id="${order.id}">
                                <i class="fas fa-qrcode"></i>
                                <span>ชำระเงิน</span>
                            </button>
                        ` : `
                            <span class="text-xs text-amber-700 bg-amber-50 px-3 py-1.5 rounded-xl font-semibold border border-amber-200/60 flex items-center">
                                <i class="fas fa-clock mr-1 text-amber-500"></i>รอร้านค้าตรวจสอบสลิป (แจ้งชำระแล้ว - ยกเลิกไม่ได้)
                            </span>
                        `
                    ) : ''}
                </div>
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
    }

    async function cancelOrderAction(orderId) {
        const targetOrder = orders.find(o => o.id == orderId);
        if (!targetOrder) return;

        if (targetOrder.slipImage || targetOrder.status !== 'Pending Payment') {
            showToast("คำสั่งซื้อนี้มีการแจ้งชำระเงินแล้ว ไม่สามารถยกเลิกได้", "error");
            return;
        }

        let confirmed = false;
        if (typeof Swal !== 'undefined') {
            const res = await Swal.fire({
                title: 'ยืนยันการยกเลิกคำสั่งซื้อ?',
                text: `คุณต้องการยกเลิกคำสั่งซื้อ #${orderId} หรือไม่? รายการสินค้าจะถูกยกเลิกและคืนเข้าสต็อกร้านค้า`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e11d48',
                cancelButtonColor: '#94a3b8',
                confirmButtonText: 'ยืนยันการยกเลิก',
                cancelButtonText: 'ย้อนกลับ'
            });
            confirmed = res.isConfirmed;
        } else {
            confirmed = confirm(`คุณต้องการยกเลิกคำสั่งซื้อ #${orderId} หรือไม่?`);
        }

        if (!confirmed) return;

        try {
            const token = localStorage.getItem('token');
            const res = await fetch('/api/orders/update-status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    ...(token ? { 'Authorization': `Bearer ${token}` } : {})
                },
                body: JSON.stringify({
                    order_id: orderId,
                    status: 'Cancelled',
                    cancel_reason: 'ลูกค้ายกเลิกคำสั่งซื้อผ่านหน้าประวัติคำสั่งซื้อ'
                })
            });

            const resData = await res.json();
            if (res.ok) {
                targetOrder.status = 'Cancelled';
                if (typeof saveUserOrdersData === 'function') {
                    saveUserOrdersData(orders);
                }
                localStorage.setItem('myOrders', JSON.stringify(orders));
                updatePendingBadge();
                renderOrders();
                showToast(`ยกเลิกคำสั่งซื้อ #${orderId} เรียบร้อยแล้ว`, "success");
            } else {
                showToast(resData.message || 'เกิดข้อผิดพลาดในการยกเลิกคำสั่งซื้อ', "error");
            }
        } catch (err) {
            console.error("Cancel order error:", err);
            showToast('เกิดข้อผิดพลาดในการเชื่อมต่อกับเซิร์ฟเวอร์', "error");
        }
    }

    function getStatusBadge(status) {
        switch (status) {
            case 'Pending Payment':
                return { label: 'ที่ต้องชำระ', class: 'bg-amber-50 text-amber-600 border border-amber-200/50', icon: '<i class="fas fa-clock text-[10px]"></i>' };
            case 'Preparing':
                return { label: 'ที่ต้องจัดส่ง', class: 'bg-blue-50 text-blue-600 border border-blue-200/50', icon: '<i class="fas fa-box text-[10px]"></i>' };
            case 'Shipping':
                return { label: 'กำลังจัดส่ง', class: 'bg-purple-50 text-purple-600 border border-purple-200/50', icon: '<i class="fas fa-truck text-[10px]"></i>' };
            case 'Completed':
                return { label: 'สำเร็จแล้ว', class: 'bg-emerald-50 text-emerald-600 border border-emerald-200/50', icon: '<i class="fas fa-check-circle text-[10px]"></i>' };
            case 'Cancelled':
                return { label: 'ยกเลิกแล้ว', class: 'bg-gray-100 text-gray-500 border border-gray-200', icon: '<i class="fas fa-times-circle text-[10px]"></i>' };
            default:
                return { label: status, class: 'bg-gray-100 text-gray-600', icon: '' };
        }
    }

    function openDetailModal(orderId) {
        const order = orders.find(o => o.id == orderId);
        if (!order) return;

        if (modalOrderId) modalOrderId.textContent = `#${order.id}`;
        if (modalDate) modalDate.textContent = new Date(order.date).toLocaleString('th-TH');
        if (modalStatus) {
            const statusConfig = getStatusBadge(order.status);
            modalStatus.innerHTML = `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ${statusConfig.class}">${statusConfig.label}</span>`;
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

        if (modalSubtotal) modalSubtotal.textContent = `฿${(order.subtotal || 0).toFixed(2)}`;
        if (modalShippingFee) modalShippingFee.textContent = `฿${(order.shipping || 0).toFixed(2)}`;
        if (modalTotal) modalTotal.textContent = `฿${(order.total || 0).toFixed(2)}`;

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

    // Slip file input inside pay now modal
    if (payModalSlipInput) {
        payModalSlipInput.onchange = (e) => {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (event) => {
                    attachedSlipData = event.target.result;
                    if (payModalFileName) payModalFileName.textContent = file.name;
                    if (payModalPlaceholder) payModalPlaceholder.classList.add('hidden');
                    if (payModalPreview) payModalPreview.classList.remove('hidden');
                    updateHistoryPayBtnState();
                };
                reader.readAsDataURL(file);
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
                    await fetch('/api/orders/upload-slip', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            order_id: currentPayingOrder.id,
                            slip_image: attachedSlipData
                        })
                    });
                }
            } catch (err) {
                console.warn("Backend slip sync note:", err);
            }

            currentPayingOrder.status = 'Preparing';
            currentPayingOrder.slipImage = attachedSlipData;
            currentPayingOrder.paidAt = new Date().toISOString();

            saveUserOrdersData(orders);

            closePayNowModal();
            confirmPayNowBtn.innerHTML = '<i class="fas fa-check-circle"></i><span>ยืนยันการชำระเงิน</span>';
            confirmPayNowBtn.disabled = false;

            updatePendingBadge();
            renderOrders();

            showToast(`ชำระเงินคำสั่งซื้อ #${currentPayingOrder.id} สำเร็จ! ย้ายไปที่สถานะ 'ที่ต้องจัดส่ง'`, "success");
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

    loadOrders();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initOrderHistoryPage);
} else {
    initOrderHistoryPage();
}
