import { showToast, escapeHTML } from './utils.js';

document.addEventListener('DOMContentLoaded', () => {
    const ordersContainer = document.getElementById('ordersContainer');
    const emptyOrders = document.getElementById('emptyOrders');
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

    let orders = [];
    let currentFilterTab = 'all';
    let currentPayingOrder = null;
    let attachedSlipData = null;

    // Check URL parameters for tab selection (e.g. ?tab=pending_payment)
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab') || urlParams.get('status');
    if (tabParam) {
        currentFilterTab = tabParam.toLowerCase();
    }

    function loadOrders() {
        const storedOrders = localStorage.getItem('myOrders');
        if (storedOrders) {
            try {
                orders = JSON.parse(storedOrders);
            } catch (e) {
                console.error("Error parsing orders", e);
                orders = [];
            }
        } else {
            orders = [];
        }

        updatePendingBadge();
        renderOrders();
        updateActiveTabUI();

        // Always sync with backend database to keep prices and statuses 100% accurate
        syncOrdersWithBackend();
    }

    async function syncOrdersWithBackend() {
        try {
            const res = await fetch('/api/orders');
            if (res.ok) {
                const json = await res.json();
                const backendOrders = json.data || [];
                if (backendOrders.length > 0) {
                    let updated = false;

                    backendOrders.forEach(bOrder => {
                        const bId = bOrder.id || bOrder.order_id;
                        let existing = orders.find(o => o.id == bId);
                        
                        if (existing) {
                            const newTotal = parseFloat(bOrder.amount);
                            if (!isNaN(newTotal) && existing.total !== newTotal) {
                                existing.total = newTotal;
                                updated = true;
                            }
                            if (bOrder.slip_image && existing.slipImage !== bOrder.slip_image) {
                                existing.slipImage = bOrder.slip_image;
                                updated = true;
                            }
                            if (bOrder.status) {
                                let newStatus = bOrder.status;
                                if (newStatus === 'Pending') {
                                    newStatus = 'Pending Payment';
                                } else if (newStatus === 'Processing') {
                                    newStatus = 'Preparing';
                                } else if (newStatus === 'In Transit') {
                                    newStatus = 'Shipping';
                                } else if (newStatus === 'Completed') {
                                    newStatus = 'Completed';
                                } else if (newStatus === 'Cancelled') {
                                    newStatus = 'Cancelled';
                                }
                                if (existing.status !== newStatus) {
                                    existing.status = newStatus;
                                    updated = true;
                                }
                            }
                        }
                    });

                    if (updated) {
                        localStorage.setItem('myOrders', JSON.stringify(orders));
                        updatePendingBadge();
                        renderOrders();
                    }
                }
            }
        } catch (err) {
            console.warn("Could not sync orders from backend:", err);
        }
    }

    function updatePendingBadge() {
        const pendingCount = orders.filter(o => isPendingPaymentStatus(o.status)).length;
        if (pendingCount > 0 && pendingBadge) {
            pendingBadge.textContent = pendingCount;
            pendingBadge.classList.remove('hidden');
        } else if (pendingBadge) {
            pendingBadge.classList.add('hidden');
        }
    }

    function isPendingPaymentStatus(status) {
        return status === 'Pending Payment' || status === 'ที่ต้องชำระ' || status === 'Pending';
    }

    function isPreparingStatus(status) {
        return status === 'Preparing' || status === 'กำลังเตรียมสินค้า' || status === 'ที่ต้องจัดส่ง' || status === 'Processing';
    }

    function isShippingStatus(status) {
        return status === 'Shipping' || status === 'กำลังจัดส่ง' || status === 'ที่ต้องได้รับ' || status === 'In Transit';
    }

    function isCompletedStatus(status) {
        return status === 'Completed' || status === 'สำเร็จ' || status === 'สำเร็จแล้ว';
    }

    function getFilteredOrders() {
        if (currentFilterTab === 'all') return orders;
        if (currentFilterTab === 'pending_payment') {
            return orders.filter(o => isPendingPaymentStatus(o.status));
        }
        if (currentFilterTab === 'preparing') {
            return orders.filter(o => isPreparingStatus(o.status));
        }
        if (currentFilterTab === 'shipping') {
            return orders.filter(o => isShippingStatus(o.status));
        }
        if (currentFilterTab === 'completed') {
            return orders.filter(o => isCompletedStatus(o.status));
        }
        return orders;
    }

    function renderOrders() {
        ordersContainer.innerHTML = '';
        const filteredList = getFilteredOrders();
        
        if (filteredList.length === 0) {
            ordersContainer.classList.add('hidden');
            emptyOrders.classList.remove('hidden');
            return;
        }

        ordersContainer.classList.remove('hidden');
        emptyOrders.classList.add('hidden');

        filteredList.forEach(order => {
            let orderDateStr = order.date;
            if (typeof orderDateStr === 'string' && orderDateStr.includes(' ') && !orderDateStr.includes('T')) {
                orderDateStr = orderDateStr.replace(' ', 'T');
            }
            const date = new Date(orderDateStr || Date.now()).toLocaleDateString('th-TH', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });

            const isPending = isPendingPaymentStatus(order.status);
            const isPrep = isPreparingStatus(order.status);
            const isShip = isShippingStatus(order.status);
            const isDone = isCompletedStatus(order.status);

            let statusLabel = 'สำเร็จแล้ว';
            let statusBadgeStyle = 'bg-emerald-100 text-emerald-800 border border-emerald-200';

            if (isPending) {
                statusLabel = 'ที่ต้องชำระ (รอชำระเงิน)';
                statusBadgeStyle = 'bg-amber-100 text-amber-800 border border-amber-200';
            } else if (isPrep) {
                statusLabel = 'ที่ต้องจัดส่ง (กำลังเตรียมสินค้า)';
                statusBadgeStyle = 'bg-blue-100 text-blue-800 border border-blue-200';
            } else if (isShip) {
                statusLabel = 'ที่ต้องได้รับ (สินค้ากำลังจัดส่ง)';
                statusBadgeStyle = 'bg-purple-100 text-purple-800 border border-purple-200';
            }

            const card = document.createElement('div');
            card.className = "bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow";
            card.innerHTML = `
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-5 gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-bold text-[#16a34a] uppercase tracking-wide">คำสั่งซื้อ #${order.id}</span>
                            ${order.slipImage ? '<span class="bg-emerald-50 text-emerald-700 text-[10px] px-2 py-0.5 rounded-md font-semibold"><i class="fas fa-paperclip mr-1"></i>มีสลิป</span>' : ''}
                        </div>
                        <div class="text-xs text-gray-400 mt-0.5">${date}</div>
                    </div>
                    <div class="px-3.5 py-1.5 rounded-full text-xs font-bold ${statusBadgeStyle} flex items-center gap-1.5 shadow-2xs">
                        <span class="w-2 h-2 rounded-full ${isPending ? 'bg-amber-500 animate-pulse' : isPrep ? 'bg-blue-500 animate-pulse' : isShip ? 'bg-purple-500 animate-pulse' : 'bg-emerald-500'}"></span>
                        ${statusLabel}
                    </div>
                </div>

                <!-- Order Items Preview -->
                <div class="space-y-3 mb-5 bg-gray-50/60 p-3.5 rounded-xl">
                    ${(order.items || []).slice(0, 2).map(item => `
                        <div class="flex items-center space-x-3">
                            <img src="${escapeHTML(item.image || '/image/non-image.png')}" onerror="this.src='/image/non-image.png'" alt="" class="w-10 h-10 bg-white rounded-lg object-contain p-1 border border-gray-100 shrink-0">
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-bold text-gray-800 truncate">${escapeHTML(item.name)}</div>
                                <div class="text-[11px] text-gray-500">จำนวน: ${escapeHTML(item.quantity)}</div>
                            </div>
                            <div class="text-xs font-semibold text-gray-700">฿${(parseFloat(item.price) * item.quantity).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                        </div>
                    `).join('')}
                    ${(order.items || []).length > 2 ? `<div class="text-[11px] text-gray-400 text-center font-medium">+ อีก ${(order.items || []).length - 2} รายการ</div>` : ''}
                </div>
                
                <div class="border-t border-gray-100 pt-4 flex flex-wrap justify-between items-center gap-4">
                    <div class="flex items-center space-x-2">
                        <span class="text-xs text-gray-400">ยอดสุทธิ:</span>
                        <span class="text-lg font-extrabold text-[#16a34a]">฿${order.total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <!-- Stage 1: Pending Payment Action -->
                        ${isPending ? `
                            <button class="pay-now-btn px-4 py-2 bg-[#003B6A] text-white text-xs font-bold rounded-xl hover:bg-blue-900 transition-all shadow-sm flex items-center gap-1.5" data-id="${order.id}">
                                <i class="fas fa-qrcode text-blue-200"></i>
                                <span>ชำระเงินตอนนี้</span>
                            </button>
                        ` : ''}

                        <!-- Stage 2: Preparing Order Simulation Action -->
                        ${isPrep ? `
                            <button class="ship-demo-btn px-3 py-1.5 bg-blue-50 text-blue-700 hover:bg-blue-100 text-xs font-semibold rounded-xl transition-all flex items-center gap-1" data-id="${order.id}">
                                <i class="fas fa-truck-fast"></i>
                                <span>ร้านค้าจัดส่งสินค้าแล้ว (จำลอง)</span>
                            </button>
                        ` : ''}

                        <!-- Stage 3: In Transit Action (Confirm Received) -->
                        ${isShip ? `
                            <button class="confirm-receive-btn px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition-all shadow-sm flex items-center gap-1.5" data-id="${order.id}">
                                <i class="fas fa-box-open"></i>
                                <span>ยืนยันได้รับสินค้าแล้ว</span>
                            </button>
                        ` : ''}

                        <!-- Stage 4: Completed -->
                        ${isDone ? `
                            <span class="text-xs font-semibold text-emerald-700 bg-emerald-50 px-3 py-1.5 rounded-xl border border-emerald-100">
                                <i class="fas fa-circle-check mr-1"></i>สั่งซื้อเสร็จสมบูรณ์
                            </span>
                        ` : ''}

                        <button class="view-details-btn px-4 py-2 bg-gray-100 text-gray-700 text-xs font-bold rounded-xl hover:bg-gray-200 transition-colors" data-id="${order.id}">
                            ดูรายละเอียด
                        </button>
                    </div>
                </div>
            `;
            ordersContainer.appendChild(card);
        });

        // Add event listeners
        document.querySelectorAll('.view-details-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = e.target.closest('button').dataset.id;
                showOrderDetails(id);
            });
        });

        document.querySelectorAll('.pay-now-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = e.target.closest('button').dataset.id;
                openPayNowModal(id);
            });
        });

        document.querySelectorAll('.ship-demo-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = e.target.closest('button').dataset.id;
                markAsShipped(id);
            });
        });

        document.querySelectorAll('.confirm-receive-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = e.target.closest('button').dataset.id;
                confirmReceived(id);
            });
        });
    }

    async function markAsShipped(orderId) {
        const order = orders.find(o => String(o.id) === String(orderId));
        if (!order) return;

        const trackingNumber = 'TH' + Math.floor(100000000 + Math.random() * 900000000);

        try {
            await fetch('/api/orders/update-status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    order_id: order.id,
                    status: 'In Transit',
                    tracking_number: trackingNumber
                })
            });
        } catch (err) {
            console.warn("Backend update-status sync:", err);
        }

        order.status = 'Shipping'; // Move to Stage 3: ที่ต้องได้รับ
        order.shippedAt = new Date().toISOString();
        order.trackingNumber = trackingNumber;

        localStorage.setItem('myOrders', JSON.stringify(orders));
        renderOrders();
        showToast(`จัดส่งสินค้าสำหรับคำสั่งซื้อ #${order.id} แล้ว! (เลขพัสดุ: ${order.trackingNumber})`, "success");
    }

    async function confirmReceived(orderId) {
        const order = orders.find(o => String(o.id) === String(orderId));
        if (!order) return;

        try {
            await fetch('/api/orders/update-status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    order_id: order.id,
                    status: 'Completed'
                })
            });
        } catch (err) {
            console.warn("Backend update-status sync:", err);
        }

        order.status = 'Completed'; // Move to Stage 4: สำเร็จแล้ว / จัดส่งสำเร็จ
        order.completedAt = new Date().toISOString();

        localStorage.setItem('myOrders', JSON.stringify(orders));
        renderOrders();
        showToast(`ขอบคุณที่ยืนยันรับสินค้า! คำสั่งซื้อ #${order.id} เสร็จสมบูรณ์แล้ว (จัดส่งสำเร็จ)`, "success");
    }

    function updateActiveTabUI() {
        document.querySelectorAll('.tab-btn').forEach(btn => {
            const tab = btn.dataset.tab;
            if (tab === currentFilterTab) {
                btn.className = 'tab-btn px-4 py-2.5 rounded-xl font-bold text-xs md:text-sm transition-all whitespace-nowrap bg-[#4D7C68] text-white shadow-sm flex items-center gap-1.5';
            } else {
                btn.className = 'tab-btn px-4 py-2.5 rounded-xl font-semibold text-xs md:text-sm transition-all whitespace-nowrap bg-white text-gray-600 hover:bg-gray-100 border border-gray-200 flex items-center gap-1.5';
            }
        });
    }

    // Attach Tab Filter Click Listeners
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            currentFilterTab = e.currentTarget.dataset.tab;
            updateActiveTabUI();
            renderOrders();
        });
    });

    async function showOrderDetails(orderId) {
        let order = orders.find(o => o.id == orderId);

        try {
            const res = await fetch(`/api/orders/details?id=${orderId}`);
            if (res.ok) {
                const json = await res.json();
                if (json.data) {
                    const d = json.data;
                    if (!order) {
                        order = { id: d.id, items: [] };
                        orders.unshift(order);
                    }
                    if (d.summary) {
                        order.subtotal = parseFloat(d.summary.subtotal) || order.subtotal || 0;
                        order.shipping = parseFloat(d.summary.shipping) || order.shipping || 0;
                        order.total = parseFloat(d.summary.total) || order.total || 0;
                        order.discount = parseFloat(d.summary.discount) || 0;
                    }
                    if (d.date) order.date = d.date;
                    if (d.shipping_provider) order.deliveryProvider = d.shipping_provider;
                    if (d.slip_image) order.slipImage = d.slip_image;
                    if (d.status) {
                        let newStatus = d.status;
                        if (newStatus === 'Pending' && (d.has_slip || d.slip_image)) newStatus = 'Preparing';
                        else if (newStatus === 'Pending') newStatus = 'Pending Payment';
                        else if (newStatus === 'Processing') newStatus = 'Preparing';
                        else if (newStatus === 'In Transit') newStatus = 'Shipping';
                        order.status = newStatus;
                    }
                    localStorage.setItem('myOrders', JSON.stringify(orders));
                }
            }
        } catch (err) {
            console.warn("Fetch order details fallback:", err);
        }

        if (!order) return;

        modalOrderId.textContent = `#${order.id}`;
        let modalDateStr = order.date;
        if (typeof modalDateStr === 'string' && modalDateStr.includes(' ') && !modalDateStr.includes('T')) {
            modalDateStr = modalDateStr.replace(' ', 'T');
        }
        modalDate.textContent = new Date(modalDateStr || Date.now()).toLocaleDateString('th-TH', {
            year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
        });
        
        const isPending = isPendingPaymentStatus(order.status);
        const isPrep = isPreparingStatus(order.status);
        const isShip = isShippingStatus(order.status);
        
        if (isPending) {
            modalStatus.textContent = 'ที่ต้องชำระ (รอชำระเงิน)';
            modalStatus.className = 'text-sm font-bold uppercase text-amber-600';
        } else if (isPrep) {
            modalStatus.textContent = 'ที่ต้องจัดส่ง (ร้านกำลังเตรียมสินค้า)';
            modalStatus.className = 'text-sm font-bold uppercase text-blue-600';
        } else if (isShip) {
            modalStatus.textContent = `ที่ต้องได้รับ (กำลังจัดส่ง ${order.trackingNumber ? ' - ' + order.trackingNumber : ''})`;
            modalStatus.className = 'text-sm font-bold uppercase text-purple-600';
        } else {
            modalStatus.textContent = 'สำเร็จแล้ว';
            modalStatus.className = 'text-sm font-bold uppercase text-green-600';
        }

        modalPayment.textContent = 'โอนผ่านธนาคาร / PromptPay';
        modalShipping.textContent = order.deliveryProvider || (order.deliveryMethod === 'standard' ? 'ส่งธรรมดา (3-5 วัน)' : 'Kerry Express');
        
        // Render items
        modalItems.innerHTML = (order.items || []).map(item => `
            <div class="flex items-center space-x-4">
                <div class="w-14 h-14 bg-gray-50 rounded-xl border border-gray-100 flex items-center justify-center p-2 shrink-0">
                    <img src="${escapeHTML(item.image || '/image/non-image.png')}" onerror="this.src='/image/non-image.png'" alt="" class="w-full h-full object-contain">
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-bold text-gray-800 truncate">${escapeHTML(item.name)}</div>
                    <div class="text-xs text-gray-500">จำนวน: ${escapeHTML(item.quantity)} × ฿${parseFloat(item.price).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                </div>
                <div class="text-sm font-bold text-gray-800">
                    ฿${(parseFloat(item.price) * item.quantity).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                </div>
            </div>
        `).join('');

        const subtotalVal = parseFloat(order.subtotal) || ((order.items || []).reduce((s, it) => s + (parseFloat(it.price) * it.quantity), 0));
        const totalVal = parseFloat(order.total) || (subtotalVal + (parseFloat(order.shipping) || 0));
        const shippingVal = parseFloat(order.shipping) !== undefined ? parseFloat(order.shipping) : Math.max(0, totalVal - subtotalVal);

        modalSubtotal.textContent = `฿${subtotalVal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        modalShippingFee.textContent = `฿${shippingVal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        modalTotal.textContent = `฿${totalVal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

        // Open modal
        orderDetailModal.classList.remove('opacity-0', 'pointer-events-none');
        orderDetailModal.querySelector('div').classList.remove('scale-95');
        orderDetailModal.querySelector('div').classList.add('scale-100');
    }

    // Pay Now Modal Logic
    function openPayNowModal(orderId) {
        const order = orders.find(o => o.id == orderId);
        if (!order) return;

        currentPayingOrder = order;
        payModalOrderId.textContent = `#${order.id}`;
        payModalAmount.textContent = `฿${order.total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

        // Reset slip inputs
        attachedSlipData = null;
        if (payModalSlipInput) payModalSlipInput.value = '';
        if (payModalPreview) payModalPreview.classList.add('hidden');
        if (payModalPlaceholder) payModalPlaceholder.classList.remove('hidden');

        payNowModal.classList.remove('opacity-0', 'pointer-events-none');
        payNowModal.querySelector('div').classList.remove('scale-95');
        payNowModal.querySelector('div').classList.add('scale-100');
    }

    function closePayNowModal() {
        payNowModal.classList.add('opacity-0', 'pointer-events-none');
        payNowModal.querySelector('div').classList.remove('scale-100');
        payNowModal.querySelector('div').classList.add('scale-95');
        currentPayingOrder = null;
    }

    payModalSlipInput?.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (event) => {
                attachedSlipData = event.target.result;
                payModalFileName.textContent = file.name;
                payModalPlaceholder.classList.add('hidden');
                payModalPreview.classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        }
    });

    payModalRemoveSlip?.addEventListener('click', (e) => {
        e.stopPropagation();
        payModalSlipInput.value = '';
        attachedSlipData = null;
        payModalPreview.classList.add('hidden');
        payModalPlaceholder.classList.remove('hidden');
    });

    confirmPayNowBtn?.addEventListener('click', async () => {
        if (!currentPayingOrder) return;

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

        currentPayingOrder.status = 'Preparing'; // Change status to Stage 2: 'ที่ต้องจัดส่ง'
        currentPayingOrder.slipImage = attachedSlipData;
        currentPayingOrder.paidAt = new Date().toISOString();

        localStorage.setItem('myOrders', JSON.stringify(orders));

        closePayNowModal();
        confirmPayNowBtn.innerHTML = '<i class="fas fa-check-circle"></i><span>ยืนยันการชำระเงิน</span>';
        confirmPayNowBtn.disabled = false;

        updatePendingBadge();
        renderOrders();

        showToast(`ชำระเงินคำสั่งซื้อ #${currentPayingOrder.id} สำเร็จ! ย้ายไปที่สถานะ 'ที่ต้องจัดส่ง'`, "success");
    });

    closeModalBtn?.addEventListener('click', () => {
        orderDetailModal.classList.add('opacity-0', 'pointer-events-none');
        orderDetailModal.querySelector('div').classList.remove('scale-100');
        orderDetailModal.querySelector('div').classList.add('scale-95');
    });

    closePayModalBtn?.addEventListener('click', closePayNowModal);

    window.addEventListener('storage', loadOrders);

    loadOrders();
});
