import { updateGlobalCartCount } from './main.js';
import { showToast, getUserProfileData, saveUserProfileData, getCartData, saveCartData, getUserOrdersData, saveUserOrdersData } from './utils.js';
import Swal from 'sweetalert2';

export function initCheckoutPage() {
    const cleanPath = (window.location.pathname || '').toLowerCase();
    if (cleanPath.includes('/staff') || cleanPath.includes('/admin') || cleanPath.includes('staff_') || cleanPath.includes('admin_')) return;
    // Elements
    const summaryItemsContainer = document.getElementById('summaryItemsContainer');
    if (!summaryItemsContainer) return;

    // Strict guard: if an order was recently completed, prevent navigating back to checkout
    if (sessionStorage.getItem('checkout_completed') === 'true') {
        sessionStorage.removeItem('checkout_completed');
        window.location.replace('/products.html');
        return;
    }

    // Force reload if page is restored from bfcache
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            window.location.reload();
        }
    });

    const summarySubtotal = document.getElementById('summarySubtotal');
    const summaryShipping = document.getElementById('summaryShipping');
    const summaryTotal = document.getElementById('summaryTotal');
    const confirmOrderBtn = document.getElementById('confirmOrderBtn');
    
    // Address Inputs
    const inputFullName = document.getElementById('fullName');
    const inputPhone = document.getElementById('phone');
    const inputAddress = document.getElementById('address');
    const inputProvince = document.getElementById('province');
    const inputZipcode = document.getElementById('zipcode');

    // Modals & QR Elements
    const paymentQrModal = document.getElementById('paymentQrModal');
    const closeQrModalBtn = document.getElementById('closeQrModalBtn');
    const qrModalAmount = document.getElementById('qrModalAmount');
    const qrModalOrderId = document.getElementById('qrModalOrderId');
    const slipFileInput = document.getElementById('slipFileInput');
    const slipUploadPlaceholder = document.getElementById('slipUploadPlaceholder');
    const slipPreviewContainer = document.getElementById('slipPreviewContainer');
    const slipFileName = document.getElementById('slipFileName');
    const removeSlipBtn = document.getElementById('removeSlipBtn');
    const submitPaymentBtn = document.getElementById('submitPaymentBtn');
    const payLaterBtn = document.getElementById('payLaterBtn');

    // Success Modal
    const successModal = document.getElementById('successModal');
    const successModalTitle = document.getElementById('successModalTitle');
    const successModalMessage = document.getElementById('successModalMessage');
    const mockOrderId = document.getElementById('mockOrderId');
    
    // State
    let cart = [];
    let checkoutItems = [];
    let subtotal = 0;
    let deliveryCompanies = [];
    let selectedCompanyId = null;
    let shippingFee = 0.00;
    let pendingOrderData = null;
    let attachedSlipData = null;
    let paymentSettings = null;
    let rewardSettings = { point_earning_baht: 100, point_earning_qty: 1 };
    let userPoints = 0;
    let pointsUsed = 0;

    // Initialization
    async function init() {
        if (sessionStorage.getItem('checkout_completed') === 'true') {
            sessionStorage.removeItem('checkout_completed');
            window.location.replace('/products.html');
            return;
        }

        cart = getCartData();
        if (!cart || cart.length === 0) {
            try {
                cart = JSON.parse(localStorage.getItem('cart') || '[]');
            } catch(e) { cart = []; }
        }
        checkoutItems = cart.filter(item => item.selected !== false);
        
        if (checkoutItems.length === 0) {
            window.location.replace('/products.html');
            return;
        }

        renderSummary();
        prefillAddress();
        await Promise.all([fetchDeliveryCompanies(), fetchPaymentSettings(), fetchRewardSettings(), fetchUserPoints()]);
        attachEvents();
    }

    async function fetchRewardSettings() {
        try {
            const res = await fetch('/api/rewards/settings');
            if (res.ok) {
                const result = await res.json();
                if (result.data) {
                    rewardSettings = result.data;
                }
            }
        } catch (e) {
            console.error("Error fetching reward settings:", e);
        }
        renderSummary();
    }

    async function fetchPaymentSettings() {
        try {
            const res = await fetch('/api/payment/settings');
            if (res.ok) {
                const result = await res.json();
                paymentSettings = result.data;
            }
        } catch (e) {
            console.error("Error fetching payment settings:", e);
        }
    }

    async function fetchUserPoints() {
        try {
            const res = await fetch('/api/customers/details');
            if (res.ok) {
                const result = await res.json();
                if (result.data && result.data.points !== undefined) {
                    userPoints = parseInt(result.data.points) || 0;
                    initPointsUI();
                }
            }
        } catch (e) {
            console.error("Error fetching user points:", e);
        }
    }

    function getMaxRedeemablePoints() {
        const maxFromPoints = Math.floor(userPoints / 10) * 10;
        const maxFromSubtotal = Math.floor(subtotal / 10) * 10;
        return Math.max(0, Math.min(maxFromPoints, maxFromSubtotal));
    }

    function initPointsUI() {
        const card = document.getElementById('checkoutPointsCard');
        if (!card) return;

        card.style.display = 'block';
        const badge = document.getElementById('checkoutUserPointsBadge');
        if (badge) badge.textContent = `${userPoints.toLocaleString()} แต้ม`;

        const chk = document.getElementById('usePointsCheckbox');
        const selectorArea = document.getElementById('checkoutPointsSelectorArea');
        const notice = document.getElementById('checkoutPointsNotice');
        const input = document.getElementById('checkoutPointsInput');
        const minusBtn = document.getElementById('btnPointsMinus');
        const plusBtn = document.getElementById('btnPointsPlus');
        const maxBtn = document.getElementById('btnPointsMax');

        if (userPoints < 10) {
            if (chk) { chk.disabled = true; chk.checked = false; }
            if (selectorArea) selectorArea.classList.add('hidden');
            if (notice) {
                notice.classList.remove('hidden');
                const nPts = document.getElementById('checkoutNoticePts');
                if (nPts) nPts.textContent = userPoints;
            }
            pointsUsed = 0;
            renderSummary();
            return;
        }

        if (chk) {
            chk.disabled = false;
            chk.onchange = () => {
                if (chk.checked) {
                    if (selectorArea) selectorArea.classList.remove('hidden');
                    const maxPts = getMaxRedeemablePoints();
                    setPointsUsed(maxPts >= 10 ? 10 : 0);
                } else {
                    if (selectorArea) selectorArea.classList.add('hidden');
                    setPointsUsed(0);
                }
            };
        }

        if (minusBtn) minusBtn.onclick = () => setPointsUsed(pointsUsed - 10);
        if (plusBtn) plusBtn.onclick = () => setPointsUsed(pointsUsed + 10);
        if (maxBtn) maxBtn.onclick = () => setPointsUsed(getMaxRedeemablePoints());
        if (input) {
            input.onchange = (e) => {
                let val = parseInt(e.target.value) || 0;
                setPointsUsed(val);
            };
        }
    }

    function setPointsUsed(pts) {
        const maxPts = getMaxRedeemablePoints();
        let target = Math.floor((parseInt(pts) || 0) / 10) * 10;
        if (target > maxPts) target = maxPts;
        if (target < 0) target = 0;
        pointsUsed = target;

        const input = document.getElementById('checkoutPointsInput');
        const discountAmt = document.getElementById('checkoutPointsDiscountAmount');
        if (input) {
            input.value = pointsUsed;
            input.max = maxPts;
        }
        if (discountAmt) {
            discountAmt.textContent = ((pointsUsed / 10) * 10).toFixed(2);
        }

        renderSummary();
    }

    function calculateTotalWeight() {
        return checkoutItems.reduce((acc, it) => {
            let w = parseFloat(it.weight || 0);
            let u = (it.weight_unit || 'kg').toLowerCase().trim();
            if (u === 'g' || u === 'ml' || u === 'กรัม' || u === 'มิลลิลิตร') {
                w = w / 1000.0;
            }
            return acc + (w * (it.quantity || 1));
        }, 0);
    }

    async function fetchDeliveryCompanies() {
        try {
            const res = await fetch('/api/delivery/companies');
            if (res.ok) {
                const result = await res.json();
                const data = result.data || [];
                deliveryCompanies = data.filter(c => c.is_active !== undefined ? c.is_active == 1 : true);
            }
        } catch (e) {
            console.error("Error fetching delivery companies:", e);
        }

        renderDeliveryOptions();
        renderSummary();
    }

    function calculateShippingForCompany(comp) {
        const baseRate = parseFloat(comp.base_rate) || 0;
        const ratePerKg = parseFloat(comp.rate_per_kg) || 0;
        const totalWeight = calculateTotalWeight();
        const extraKg = totalWeight > 1.0 ? Math.ceil(totalWeight - 1.0) : 0;
        let fee = baseRate + (extraKg * ratePerKg);
        if (fee <= 0) fee = baseRate > 0 ? baseRate : 40.00;
        return fee;
    }

    function updateCardUI() {
        document.querySelectorAll('.option-card').forEach(label => {
            const radio = label.querySelector('input[type="radio"]');
            const cardDiv = label.querySelector(':scope > div');
            const circle = label.querySelector('.radio-circle');
            const dot = label.querySelector('.radio-dot');

            if (radio && radio.checked) {
                label.classList.add('active-card');
                if (cardDiv) {
                    cardDiv.classList.add('border-[#16a34a]', 'bg-emerald-50/60', 'shadow-sm');
                    cardDiv.classList.remove('border-gray-100');
                }
                if (circle) {
                    circle.classList.add('border-[#16a34a]', 'bg-[#16a34a]');
                    circle.classList.remove('border-gray-300');
                }
                if (dot) {
                    dot.classList.add('scale-100', 'opacity-100');
                    dot.classList.remove('scale-0', 'opacity-0');
                }
            } else {
                label.classList.remove('active-card');
                if (cardDiv) {
                    cardDiv.classList.remove('border-[#16a34a]', 'bg-emerald-50/60', 'shadow-sm');
                    cardDiv.classList.add('border-gray-100');
                }
                if (circle) {
                    circle.classList.remove('border-[#16a34a]', 'bg-[#16a34a]');
                    circle.classList.add('border-gray-300');
                }
                if (dot) {
                    dot.classList.remove('scale-100', 'opacity-100');
                    dot.classList.add('scale-0', 'opacity-0');
                }
            }
        });
    }

    function renderDeliveryOptions() {
        const container = document.getElementById('deliveryMethodsContainer');
        if (!container) return;

        const totalWeight = calculateTotalWeight();

        container.innerHTML = deliveryCompanies.map((c, idx) => {
            const fee = calculateShippingForCompany(c);
            const isChecked = idx === 0 ? 'checked' : '';
            const activeClass = idx === 0 ? 'active-card' : '';
            const weightDesc = totalWeight > 0
                ? `น้ำหนัก ${totalWeight.toFixed(2)} กก.`
                : `จัดส่งแบบมาตรฐาน`;

            return `
                <label class="option-card block cursor-pointer ${activeClass}" data-company-id="${c.company_id}">
                    <input type="radio" name="deliveryMethod" value="${c.company_id}" data-fee="${fee}" data-company-name="${c.company_name}" class="sr-only" ${isChecked}>
                    <div class="border-2 border-gray-100 rounded-2xl p-3.5 sm:p-4 md:p-5 flex items-center justify-between gap-3 hover:border-gray-300 hover:bg-gray-50/80 transition-all duration-200">
                        <div class="flex items-center space-x-3 sm:space-x-3.5 min-w-0 flex-1">
                            <div class="radio-circle w-5 h-5 rounded-full border-2 border-gray-300 relative flex items-center justify-center transition-all shrink-0">
                                <div class="radio-dot w-2 h-2 rounded-full bg-white scale-0 opacity-0 transition-all duration-200"></div>
                            </div>
                            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 shadow-sm">
                                <i class="fas fa-truck text-base sm:text-lg"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="font-bold text-gray-800 text-sm sm:text-base leading-tight">
                                    ${c.company_name}
                                </div>
                                <div class="text-[11px] sm:text-xs text-gray-500 mt-1 leading-snug">
                                    ${weightDesc}
                                </div>
                            </div>
                        </div>
                        <div class="shrink-0 text-right pl-2">
                            <div class="font-bold text-[#FE7F9C] text-sm sm:text-base whitespace-nowrap">
                                +฿${fee.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                            </div>
                        </div>
                    </div>
                </label>
            `;
        }).join('');

        // Bind radio change listeners for delivery methods
        container.querySelectorAll('input[name="deliveryMethod"]').forEach(radio => {
            radio.addEventListener('change', (e) => {
                selectedCompanyId = parseInt(e.target.value);
                shippingFee = parseFloat(e.target.dataset.fee) || 0;
                updateCardUI();
                renderSummary();
            });
        });

        // Bind click on label cards to ensure selection works reliably
        container.querySelectorAll('.option-card').forEach(card => {
            card.addEventListener('click', (e) => {
                const radio = card.querySelector('input[name="deliveryMethod"]');
                if (radio && !radio.checked) {
                    radio.checked = true;
                    selectedCompanyId = parseInt(radio.value);
                    shippingFee = parseFloat(radio.dataset.fee) || 0;
                    updateCardUI();
                    renderSummary();
                }
            });
        });

        // Set initial selected company and fee
        if (deliveryCompanies.length > 0) {
            const firstRadio = container.querySelector('input[name="deliveryMethod"]:checked') || container.querySelector('input[name="deliveryMethod"]');
            if (firstRadio) {
                firstRadio.checked = true;
                selectedCompanyId = parseInt(firstRadio.value);
                shippingFee = parseFloat(firstRadio.dataset.fee) || 0;
            }
        }

        updateCardUI();
        renderSummary();
    }

    function renderSummary() {
        summaryItemsContainer.innerHTML = '';
        subtotal = 0;

        checkoutItems.forEach(item => {
            const itemTotal = parseFloat(item.price) * item.quantity;
            subtotal += itemTotal;
            
            const imageUrl = item.image || '/image/non-image.png';

            summaryItemsContainer.innerHTML += `
                <div class="flex items-center space-x-3">
                    <img src="${imageUrl}" onerror="this.src='/image/non-image.png'" alt="" class="w-12 h-12 bg-gray-50 rounded-lg object-contain p-1 border border-gray-100 shrink-0">
                    <div class="flex-1 min-w-0">
                        <h4 class="text-sm font-bold text-gray-800 truncate">${item.name}</h4>
                        <div class="text-xs text-gray-500">จำนวน: ${item.quantity}</div>
                    </div>
                    <div class="text-sm font-semibold text-gray-800 whitespace-nowrap">
                        ฿${itemTotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                    </div>
                </div>
            `;
        });

        // Update Totals with Points Discount
        const pointsDiscount = (pointsUsed / 10) * 10.0;
        const netTotal = Math.max(0, (subtotal - pointsDiscount) + shippingFee);

        const summaryPointsRow = document.getElementById('summaryPointsRow');
        if (summaryPointsRow) {
            if (pointsUsed > 0) {
                summaryPointsRow.style.display = 'flex';
                const ptsUsedText = document.getElementById('summaryPointsUsedText');
                const ptsDiscText = document.getElementById('summaryPointsDiscount');
                if (ptsUsedText) ptsUsedText.textContent = pointsUsed.toLocaleString();
                if (ptsDiscText) ptsDiscText.textContent = `-฿${pointsDiscount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
            } else {
                summaryPointsRow.style.display = 'none';
            }
        }

        if (summarySubtotal) summarySubtotal.textContent = `฿${subtotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        if (summaryShipping) summaryShipping.textContent = `฿${shippingFee.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        if (summaryTotal) summaryTotal.textContent = `฿${netTotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

        // Calculate Points to be Earned
        const peBaht = parseFloat(rewardSettings.point_earning_baht) || 100;
        const peQty = parseInt(rewardSettings.point_earning_qty) || 1;
        let pointsEarned = 0;
        if (peBaht > 0 && peQty > 0) {
            pointsEarned = Math.floor(netTotal / peBaht) * peQty;
        }

        const summaryPointsEarned = document.getElementById('summaryPointsEarned');
        if (summaryPointsEarned) {
            summaryPointsEarned.textContent = `+${pointsEarned.toLocaleString()} แต้ม`;
        }
    }

    function prefillAddress() {
        const profile = getUserProfileData();
        if (inputFullName && profile.name) inputFullName.value = profile.name;
        if (inputPhone && profile.phone) inputPhone.value = profile.phone;
        if (inputAddress && profile.address) inputAddress.value = profile.address;
        if (inputProvince && profile.province) inputProvince.value = profile.province;
        if (inputZipcode && profile.zipcode) inputZipcode.value = profile.zipcode;
    }

    function updateSubmitButtonState() {
        const btn = document.getElementById('submitPaymentBtn') || submitPaymentBtn;
        if (!btn) return;
        btn.disabled = false;
        if (attachedSlipData) {
            btn.className = "w-full bg-[#1b4332] hover:bg-[#15803d] text-white py-3.5 rounded-xl font-bold text-sm shadow-md hover:shadow-lg ring-2 ring-emerald-400/60 transition-all flex items-center justify-center space-x-2 cursor-pointer active:scale-[0.99]";
        } else {
            btn.className = "w-full bg-[#1b4332] hover:bg-[#15803d] text-white py-3.5 rounded-xl font-bold text-sm shadow-md hover:shadow-lg transition-all flex items-center justify-center space-x-2 cursor-pointer active:scale-[0.99]";
        }
    }

    function attachEvents() {
        document.querySelectorAll('#paymentOptionsContainer .option-card').forEach(card => {
            card.addEventListener('click', () => {
                const radio = card.querySelector('input[type="radio"]');
                if (radio && !radio.checked) {
                    radio.checked = true;
                    updateCardUI();
                }
            });
        });
        updateCardUI();

        // Helper: Remove ONLY ordered items from user cart, preserving other items (Req 4 & 5)
        function clearOrderedItemsFromCart(orderedItems, user) {
            try {
                const currentCart = getCartData();
                const cartSource = (Array.isArray(currentCart) && currentCart.length > 0) ? currentCart : (cart || []);
                const orderedProductIds = new Set((orderedItems || []).map(i => String(i.id || i.product_id || '')));

                // Keep only items that were NOT ordered
                const remainingCart = cartSource.filter(item => {
                    const itemId = String(item.id || item.product_id || '');
                    return !orderedProductIds.has(itemId);
                });

                saveCartData(remainingCart);
                localStorage.setItem('cart', JSON.stringify(remainingCart));

                const allUserKeys = [
                    user?.user_id,
                    user?.customer_id,
                    user?.id,
                    user?.username
                ].filter(Boolean).map(String);

                allUserKeys.forEach(k => {
                    localStorage.setItem(`cart_${k}`, JSON.stringify(remainingCart));
                });

                try {
                    const savedUserCarts = JSON.parse(localStorage.getItem('savedUserCarts') || '{}');
                    allUserKeys.forEach(k => {
                        savedUserCarts[k] = remainingCart;
                    });
                    localStorage.setItem('savedUserCarts', JSON.stringify(savedUserCarts));
                } catch (e) {}

                // Update cart count immediately (Req 6)
                updateGlobalCartCount();
                return remainingCart;
            } catch (e) {
                console.error("Error clearing ordered items from cart:", e);
            }
        }

        // Helper: Show Success Modal with 2 choices: "กลับหน้าแรก" & "ดูประวัติคำสั่งซื้อ"
        function showOrderSuccessModal(orderId, title, message, targetTab = 'pending_payment') {
            closeQrModal();

            const modal = document.getElementById('successModal') || successModal;
            const modalOrderId = document.getElementById('mockOrderId') || mockOrderId;
            const modalTitle = document.getElementById('successModalTitle') || successModalTitle;
            const modalMsg = document.getElementById('successModalMessage') || successModalMessage;

            if (modalOrderId) modalOrderId.textContent = orderId;
            if (modalTitle) modalTitle.textContent = title || 'สั่งซื้อสำเร็จ';
            if (modalMsg) {
                modalMsg.innerHTML = message || `เราได้รับคำสั่งซื้อของคุณเรียบร้อยแล้ว<br><span class="inline-block mt-2 font-mono text-xs font-semibold bg-gray-100 px-3 py-1 rounded-lg text-gray-700">รหัสคำสั่งซื้อ: #${orderId}</span>`;
            }

            // Navigation functions using location.replace to prevent back-button resubmission
            const goToHome = () => {
                sessionStorage.removeItem('checkout_completed');
                window.location.replace('/products.html');
            };

            const goToOrderHistory = () => {
                sessionStorage.removeItem('checkout_completed');
                window.location.replace(`/order-history.html?tab=${targetTab}`);
            };

            const goHomeBtn = document.getElementById('goHomeBtn');
            if (goHomeBtn) {
                goHomeBtn.onclick = (e) => {
                    e.preventDefault();
                    goToHome();
                };
            }

            const viewOrderBtn = document.getElementById('viewOrderBtn');
            if (viewOrderBtn) {
                viewOrderBtn.onclick = (e) => {
                    e.preventDefault();
                    goToOrderHistory();
                };
            }

            if (modal) {
                modal.style.display = 'flex';
                modal.classList.remove('hidden', 'opacity-0', 'pointer-events-none');
                const innerBox = modal.querySelector('div');
                if (innerBox) {
                    innerBox.classList.remove('scale-95');
                    innerBox.classList.add('scale-100');
                }
            }
        }

        // Execute Order Creation in Database
        async function executeOrderCreation({ withSlip, slipData }) {
            if (!pendingOrderData) return;

            // Resolve customer session
            const userStr = localStorage.getItem('user');
            let user = userStr ? JSON.parse(userStr) : null;
            let customerId = user?.customer_id;
            let csrfToken = user?.csrf_token;

            if (!customerId) {
                try {
                    const res = await fetch('/api/auth/me');
                    if (res.ok) {
                        const result = await res.json();
                        if (result.data) {
                            user = result.data;
                            customerId = result.data.customer_id;
                            csrfToken = result.data.csrf_token;
                            localStorage.setItem('user', JSON.stringify(result.data));
                        }
                    }
                } catch (err) {
                    console.error("Error resolving session:", err);
                }
            }

            const orderPayload = {
                customer_id: customerId || 1,
                company_id: pendingOrderData.company_id,
                shipping_address: pendingOrderData.shippingAddress,
                items: pendingOrderData.items.map(item => ({
                    product_id: item.id,
                    quantity: item.quantity
                })),
                shipping_fee: pendingOrderData.shipping,
                discount_amount: 0,
                points_used: pendingOrderData.points_used || 0,
                payment_method: 'transfer',
                slip_image: withSlip ? slipData : null,
                csrf_token: csrfToken
            };

            let dbOrderId = null;
            let finalTotal = pendingOrderData.total;
            let finalShipping = pendingOrderData.shipping;

            try {
                const res = await fetch('/api/orders', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken || ''
                    },
                    body: JSON.stringify(orderPayload)
                });

                if (!res.ok) {
                    let errMsg = "เกิดข้อผิดพลาดในการบันทึกคำสั่งซื้อลงฐานข้อมูล";
                    try {
                        const errRes = await res.json();
                        if (errRes.message) errMsg = errRes.message;
                    } catch (e) {}
                    showToast(errMsg, "error");
                    if (submitPaymentBtn) {
                        submitPaymentBtn.disabled = false;
                        submitPaymentBtn.innerHTML = '<i class="fas fa-check-circle"></i><span>ยืนยันการชำระเงิน</span>';
                    }
                    if (payLaterBtn) {
                        payLaterBtn.disabled = false;
                        payLaterBtn.innerHTML = '<i class="fas fa-clock text-amber-600"></i><span>ชำระเงินภายหลัง</span>';
                    }
                    return;
                }

                const resJson = await res.json();
                if (resJson.data && resJson.data.order_id) {
                    dbOrderId = resJson.data.order_id;
                    if (resJson.data.net_total !== undefined) {
                        finalTotal = parseFloat(resJson.data.net_total);
                    }
                    if (resJson.data.shipping_fee !== undefined) {
                        finalShipping = parseFloat(resJson.data.shipping_fee);
                    }
                } else {
                    dbOrderId = Math.floor(100000 + Math.random() * 900000);
                }
            } catch (err) {
                console.error("Error creating order:", err);
                showToast("ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์เพื่อบันทึกคำสั่งซื้อได้", "error");
                if (submitPaymentBtn) {
                    submitPaymentBtn.disabled = false;
                    submitPaymentBtn.innerHTML = '<i class="fas fa-check-circle"></i><span>ยืนยันการชำระเงิน</span>';
                }
                if (payLaterBtn) {
                    payLaterBtn.disabled = false;
                    payLaterBtn.innerHTML = '<i class="fas fa-clock text-amber-600"></i><span>ชำระเงินภายหลัง</span>';
                }
                return;
            }

            // 1. Order successfully saved in DB!
            // Save order to per-user isolated local store (without storing heavy base64 image in localStorage)
            const orderRecord = {
                id: dbOrderId,
                company_id: pendingOrderData.company_id,
                company_name: pendingOrderData.company_name,
                date: new Date().toISOString(),
                items: pendingOrderData.items,
                subtotal: pendingOrderData.subtotal,
                shipping: finalShipping,
                points_used: pendingOrderData.points_used,
                points_discount: pendingOrderData.points_discount,
                points_earned: pendingOrderData.points_earned,
                total: finalTotal,
                deliveryMethod: pendingOrderData.deliveryMethod,
                paymentMethod: 'transfer',
                shippingAddress: pendingOrderData.shippingAddress,
                status: withSlip ? 'Waiting Verification' : 'Pending Payment',
                slipImage: null, // Stored safely in database payments table; keeping localStorage fast and light
                has_slip: withSlip,
                hasSlip: withSlip,
                payment_status: 0,
                paidAt: withSlip ? new Date().toISOString() : null
            };

            try {
                const orders = getUserOrdersData();
                orders.unshift(orderRecord);
                saveUserOrdersData(orders);
            } catch (storageErr) {
                console.warn("Storage warning:", storageErr);
            }

            // 2. Remove ONLY ordered items from cart (keeping all non-ordered items)
            try {
                clearOrderedItemsFromCart(pendingOrderData.items, user);
            } catch (cartErr) {
                console.warn("Cart clearing warning:", cartErr);
            }

            // 3. Flag checkout completed to prevent re-submitting via Back button
            sessionStorage.setItem('checkout_completed', 'true');

            // 4. Close QR Payment Modal
            closeQrModal();

            // 5. Show Respective Success Modal:
            if (withSlip) {
                // กรณีที่ 1: แนบสลิปยืนยันการชำระเงิน
                showOrderSuccessModal(
                    dbOrderId,
                    "สั่งซื้อสำเร็จ",
                    `เราได้รับคำสั่งซื้อและหลักฐานการชำระเงินของคุณเรียบร้อยแล้ว ทางร้านจะตรวจสอบยอดเงินและเริ่มแพ็คสินค้าเพื่อจัดส่งให้โดยเร็วที่สุด<br><span class="inline-block mt-2 font-mono text-xs font-semibold bg-gray-100 px-3 py-1 rounded-lg text-gray-700">รหัสคำสั่งซื้อ: #${dbOrderId}</span>`,
                    'pending_payment'
                );
            } else {
                // กรณีที่ 2: ชำระเงินภายหลัง
                showOrderSuccessModal(
                    dbOrderId,
                    "สร้างคำสั่งซื้อสำเร็จ",
                    `คำสั่งซื้อของคุณถูกบันทึกไว้ในสถานะ <span class="font-bold text-amber-600 font-sans">'ที่ต้องชำระ'</span> คุณสามารถสแกนจ่ายเงินได้ทุกเมื่อในหน้าประวัติคำสั่งซื้อ<br><span class="inline-block mt-2 font-mono text-xs font-semibold bg-gray-100 px-3 py-1 rounded-lg text-gray-700">รหัสคำสั่งซื้อ: #${dbOrderId}</span>`,
                    'pending_payment'
                );
            }
        }

        // Helper: Compress image file before uploading to avoid huge payloads and browser lag
        function compressSlipImage(file, maxWidth = 1200, maxHeight = 1200, quality = 0.75) {
            return new Promise((resolve) => {
                if (!file || !file.type || !file.type.startsWith('image/')) {
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
                        resolve(canvas.toDataURL('image/jpeg', quality));
                    };
                    img.onerror = () => resolve(event.target.result);
                    img.src = event.target.result;
                };
                reader.onerror = () => resolve(null);
                reader.readAsDataURL(file);
            });
        }

        // Confirm Order Click -> Open Payment Modal with QR, Bank Info, Slip Upload, and Action Buttons
        if (confirmOrderBtn) {
            confirmOrderBtn.onclick = () => {
                // Validate shipping address
                const requiredFields = [inputFullName, inputPhone, inputAddress, inputProvince, inputZipcode];
                const isValid = requiredFields.every(field => field && field.value.trim() !== '');
                
                if (!isValid) {
                    showToast("กรุณากรอกข้อมูลจัดส่งให้ครบถ้วน", "error");
                    const emptyField = requiredFields.find(field => field && field.value.trim() === '');
                    if (emptyField) emptyField.focus();
                    return;
                }

                if (!checkoutItems || checkoutItems.length === 0) {
                    showToast("ไม่มีสินค้าที่เลือกสำหรับสั่งซื้อ", "error");
                    window.location.replace('/cart.html');
                    return;
                }

                // Save user profile address automatically for future checkouts
                saveUserProfileData({
                    name: inputFullName.value.trim(),
                    phone: inputPhone.value.trim(),
                    address: inputAddress.value.trim(),
                    province: inputProvince.value.trim(),
                    zipcode: inputZipcode.value.trim()
                });

                const checkedDeliveryInput = document.querySelector('input[name="deliveryMethod"]:checked');
                const deliveryMethod = checkedDeliveryInput ? checkedDeliveryInput.value : 'standard';
                const companyName = checkedDeliveryInput?.dataset?.companyName || 'ขนส่งเอกชน';
                const pointsDiscount = (pointsUsed / 10) * 10.0;
                const totalAmount = Math.max(0, (subtotal - pointsDiscount) + shippingFee);

                // Calculate Points to be Earned
                const peBaht = parseFloat(rewardSettings.point_earning_baht) || 100;
                const peQty = parseInt(rewardSettings.point_earning_qty) || 1;
                let pointsEarned = 0;
                if (peBaht > 0 && peQty > 0) {
                    pointsEarned = Math.floor(totalAmount / peBaht) * peQty;
                }

                // Create Order Draft in memory
                pendingOrderData = {
                    company_id: selectedCompanyId,
                    company_name: companyName,
                    items: checkoutItems,
                    subtotal: subtotal,
                    shipping: shippingFee,
                    points_used: pointsUsed,
                    points_discount: pointsDiscount,
                    points_earned: pointsEarned,
                    total: totalAmount,
                    deliveryMethod: deliveryMethod,
                    paymentMethod: 'transfer',
                    shippingAddress: {
                        fullName: inputFullName.value.trim(),
                        phone: inputPhone.value.trim(),
                        address: inputAddress.value.trim(),
                        province: inputProvince.value.trim(),
                        zipcode: inputZipcode.value.trim()
                    }
                };

                // Display amount & points in Payment Modal
                if (qrModalAmount) {
                    qrModalAmount.textContent = `฿${totalAmount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                }
                const qrModalPointsEarned = document.getElementById('qrModalPointsEarned');
                if (qrModalPointsEarned) {
                    qrModalPointsEarned.textContent = `+${pointsEarned.toLocaleString()} แต้ม`;
                }

                // Open Payment Popup/Modal
                openQrModal();
            };
        }

        // Slip File Upload Preview
        if (slipFileInput) {
            slipFileInput.onchange = async (e) => {
                const file = e.target.files[0];
                if (file) {
                    if (slipFileName) slipFileName.textContent = file.name;
                    if (slipUploadPlaceholder) slipUploadPlaceholder.classList.add('hidden');
                    if (slipPreviewContainer) slipPreviewContainer.classList.remove('hidden');

                    // Fast client-side image compression
                    attachedSlipData = await compressSlipImage(file);
                } else {
                    attachedSlipData = null;
                }
            };
        }

        if (removeSlipBtn) {
            removeSlipBtn.onclick = (e) => {
                e.stopPropagation();
                if (slipFileInput) slipFileInput.value = '';
                attachedSlipData = null;
                if (slipPreviewContainer) slipPreviewContainer.classList.add('hidden');
                if (slipUploadPlaceholder) slipUploadPlaceholder.classList.remove('hidden');
            };
        }

        // กรณีที่ 1: กด "ยืนยันการชำระเงิน"
        if (submitPaymentBtn) {
            submitPaymentBtn.onclick = async () => {
                if (!pendingOrderData) return;

                // ตรวจสอบว่าผู้ใช้แนบสลิปแล้วหรือไม่
                if (!attachedSlipData) {
                    showToast("กรุณาแนบหลักฐานการชำระเงิน", "error");
                    const dropzone = document.getElementById('slipUploadPlaceholder')?.parentElement;
                    if (dropzone) {
                        dropzone.classList.add('border-red-500', 'bg-red-50/50');
                        setTimeout(() => dropzone.classList.remove('border-red-500', 'bg-red-50/50'), 2500);
                    }
                    return;
                }

                // ป้องกันการกดปุ่มซ้ำ
                if (submitPaymentBtn.disabled || payLaterBtn?.disabled) return;
                submitPaymentBtn.disabled = true;
                if (payLaterBtn) payLaterBtn.disabled = true;
                submitPaymentBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-2"></i><span>กำลังบันทึกคำสั่งซื้อ...</span>';

                await executeOrderCreation({
                    withSlip: true,
                    slipData: attachedSlipData
                });
            };
        }

        // กรณีที่ 2: กด "ชำระเงินภายหลัง"
        if (payLaterBtn) {
            payLaterBtn.onclick = async () => {
                if (!pendingOrderData) return;

                // ป้องกันการกดปุ่มซ้ำ
                if (payLaterBtn.disabled || submitPaymentBtn?.disabled) return;
                payLaterBtn.disabled = true;
                if (submitPaymentBtn) submitPaymentBtn.disabled = true;
                payLaterBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-2"></i><span>กำลังบันทึกคำสั่งซื้อ...</span>';

                await executeOrderCreation({
                    withSlip: false,
                    slipData: null
                });
            };
        }

        if (closeQrModalBtn) {
            closeQrModalBtn.onclick = () => {
                closeQrModal();
            };
        }

        if (paymentQrModal) {
            paymentQrModal.onclick = (e) => {
                if (e.target === paymentQrModal) {
                    closeQrModal();
                }
            };
        }
    }

    function openQrModal() {
        if (!paymentQrModal) return;

        attachedSlipData = null;
        if (slipFileInput) slipFileInput.value = '';
        if (slipPreviewContainer) slipPreviewContainer.classList.add('hidden');
        if (slipUploadPlaceholder) slipUploadPlaceholder.classList.remove('hidden');

        if (submitPaymentBtn) {
            submitPaymentBtn.disabled = false;
            submitPaymentBtn.innerHTML = '<i class="fas fa-check-circle"></i><span>ยืนยันการชำระเงิน</span>';
        }
        if (payLaterBtn) {
            payLaterBtn.disabled = false;
            payLaterBtn.innerHTML = '<i class="fas fa-clock text-amber-600"></i><span>ชำระเงินภายหลัง</span>';
        }

        if (paymentSettings) {
            const qrImg = document.getElementById('qrCodeDisplayImg');
            const accName = document.getElementById('qrAccountName');
            const bankNum = document.getElementById('qrBankAndNumber');
            const instructions = document.getElementById('qrInstructions');

            if (qrImg && paymentSettings.qr_image_url) qrImg.src = paymentSettings.qr_image_url;
            if (accName && paymentSettings.account_name) accName.textContent = `ชื่อบัญชี: ${paymentSettings.account_name}`;
            if (bankNum && paymentSettings.bank_name) bankNum.textContent = `${paymentSettings.bank_name} • บัญชี: ${paymentSettings.account_number}`;
            if (instructions) instructions.textContent = paymentSettings.instructions || '';
        }

        paymentQrModal.classList.remove('hidden');
        paymentQrModal.style.display = 'flex';
        requestAnimationFrame(() => {
            paymentQrModal.classList.remove('opacity-0', 'pointer-events-none');
            paymentQrModal.querySelector('div')?.classList.remove('scale-95');
            paymentQrModal.querySelector('div')?.classList.add('scale-100');
        });
    }

    function closeQrModal() {
        if (!paymentQrModal) return;
        paymentQrModal.classList.add('opacity-0', 'pointer-events-none');
        paymentQrModal.querySelector('div')?.classList.remove('scale-100');
        paymentQrModal.querySelector('div')?.classList.add('scale-95');
        setTimeout(() => {
            paymentQrModal.classList.add('hidden');
            paymentQrModal.style.display = 'none';
        }, 300);
    }

    init();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCheckoutPage);
} else {
    initCheckoutPage();
}
