import { updateGlobalCartCount } from './main.js';
import { showToast, getUserProfileData, saveUserProfileData } from './utils.js';

export function initCheckoutPage() {
    // Elements
    const summaryItemsContainer = document.getElementById('summaryItemsContainer');
    if (!summaryItemsContainer) return;

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

    // Initialization
    async function init() {
        cart = JSON.parse(localStorage.getItem('cart') || '[]');
        checkoutItems = cart.filter(item => item.selected !== false);
        
        if (checkoutItems.length === 0) {
            if (window.navigateTo) {
                window.navigateTo('/cart');
            } else {
                window.location.href = '/cart';
            }
            return;
        }

        renderSummary();
        prefillAddress();
        await Promise.all([fetchDeliveryCompanies(), fetchPaymentSettings()]);
        attachEvents();
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

    function renderDeliveryOptions() {
        const container = document.getElementById('deliveryMethodsContainer');
        if (!container) return;

        const totalWeight = calculateTotalWeight();
        const extraKg = totalWeight > 1.0 ? Math.ceil(totalWeight - 1.0) : 0;

        container.innerHTML = deliveryCompanies.map((c, idx) => {
            const fee = calculateShippingForCompany(c);
            const isChecked = idx === 0 ? 'checked' : '';
            const weightDesc = totalWeight > 1.0
                ? `น้ำหนัก ${totalWeight.toFixed(2)} กก. (เกิน 1 กก. บวกเพิ่ม ${extraKg} กก.)`
                : `อัตราปกติเริ่มต้น (น้ำหนัก ${totalWeight > 0 ? totalWeight.toFixed(2) + ' กก.' : 'ไม่เกิน 1 กก.'})`;

            return `
                <label class="option-card block cursor-pointer">
                    <input type="radio" name="deliveryMethod" value="${c.company_id}" data-fee="${fee}" data-company-name="${c.company_name}" class="hidden" ${isChecked}>
                    <div class="border-2 border-gray-100 rounded-2xl p-4 md:p-5 flex items-center justify-between hover:border-gray-300 hover:bg-gray-50/80 transition-all duration-200">
                        <div class="flex items-center space-x-4">
                            <div class="radio-circle w-5 h-5 rounded-full border-2 border-gray-300 relative flex items-center justify-center transition-all shrink-0">
                                <div class="radio-dot w-2 h-2 rounded-full bg-white scale-0 opacity-0 transition-all duration-200"></div>
                            </div>
                            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 shadow-sm">
                                <i class="fas fa-truck text-lg"></i>
                            </div>
                            <div>
                                <div class="font-bold text-gray-800 flex items-center gap-2">
                                    ${c.company_name}
                                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-md bg-emerald-100/70 text-emerald-700">ขนส่งพันธมิตร</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-0.5">${weightDesc}</div>
                            </div>
                        </div>
                        <div class="font-bold text-[#FE7F9C] text-base">+฿${fee.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                    </div>
                </label>
            `;
        }).join('');

        // Bind radio change listeners
        container.querySelectorAll('input[name="deliveryMethod"]').forEach(radio => {
            radio.addEventListener('change', (e) => {
                selectedCompanyId = parseInt(e.target.value);
                shippingFee = parseFloat(e.target.dataset.fee);
                renderSummary();
            });
        });

        // Set initial selected company and fee
        const firstRadio = container.querySelector('input[name="deliveryMethod"]:checked');
        if (firstRadio) {
            selectedCompanyId = parseInt(firstRadio.value);
            shippingFee = parseFloat(firstRadio.dataset.fee);
        }
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

        // Update Totals
        if (summarySubtotal) summarySubtotal.textContent = `฿${subtotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        if (summaryShipping) summaryShipping.textContent = `฿${shippingFee.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        if (summaryTotal) summaryTotal.textContent = `฿${(subtotal + shippingFee).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
    }

    function prefillAddress() {
        const profile = getUserProfileData();
        if (inputFullName && profile.name) inputFullName.value = profile.name;
        if (inputPhone && profile.phone) inputPhone.value = profile.phone;
        if (inputAddress && profile.address) inputAddress.value = profile.address;
        if (inputProvince && profile.province) inputProvince.value = profile.province;
        if (inputZipcode && profile.zipcode) inputZipcode.value = profile.zipcode;
    }

    function attachEvents() {
        // Confirm Order Click -> Open QR Modal
        if (confirmOrderBtn) {
            confirmOrderBtn.onclick = () => {
                // Validate address
                const requiredFields = [inputFullName, inputPhone, inputAddress, inputProvince, inputZipcode];
                const isValid = requiredFields.every(field => field && field.value.trim() !== '');
                
                if (!isValid) {
                    showToast("กรุณากรอกข้อมูลจัดส่งให้ครบถ้วน", "error");
                    const emptyField = requiredFields.find(field => field && field.value.trim() === '');
                    if (emptyField) emptyField.focus();
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
                const paymentMethod = document.querySelector('input[name="paymentMethod"]:checked')?.value || 'transfer';
                const fakeId = Math.floor(100000 + Math.random() * 900000);
                const totalAmount = subtotal + shippingFee;

                // Create Pending Order Draft
                pendingOrderData = {
                    id: fakeId,
                    company_id: selectedCompanyId,
                    company_name: companyName,
                    date: new Date().toISOString(),
                    items: checkoutItems,
                    subtotal: subtotal,
                    shipping: shippingFee,
                    total: totalAmount,
                    deliveryMethod: deliveryMethod,
                    paymentMethod: paymentMethod,
                    shippingAddress: {
                        fullName: inputFullName.value.trim(),
                        phone: inputPhone.value.trim(),
                        address: inputAddress.value.trim(),
                        province: inputProvince.value.trim(),
                        zipcode: inputZipcode.value.trim()
                    },
                    status: 'Pending Payment',
                    slipImage: null
                };

                // Display in QR Payment Modal
                if (qrModalOrderId) qrModalOrderId.textContent = fakeId;
                if (qrModalAmount) qrModalAmount.textContent = `฿${totalAmount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

                // Show QR Modal
                openQrModal();
            };
        }

        // Slip File Upload Preview
        if (slipFileInput) {
            slipFileInput.onchange = (e) => {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = (event) => {
                        attachedSlipData = event.target.result;
                        if (slipFileName) slipFileName.textContent = file.name;
                        if (slipUploadPlaceholder) slipUploadPlaceholder.classList.add('hidden');
                        if (slipPreviewContainer) slipPreviewContainer.classList.remove('hidden');
                    };
                    reader.readAsDataURL(file);
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

        // Submit Payment Button in QR Modal
        if (submitPaymentBtn) {
            submitPaymentBtn.onclick = () => {
                if (!pendingOrderData) return;

                submitPaymentBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i><span>กำลังตรวจสอบ...</span>';
                submitPaymentBtn.disabled = true;

                setTimeout(() => {
                    pendingOrderData.status = 'Pending Payment';
                    pendingOrderData.slipImage = attachedSlipData;
                    pendingOrderData.paidAt = new Date().toISOString();
                    
                    finalizeOrder(
                        "แนบหลักฐานชำระเงินเรียบร้อยแล้ว!",
                        `เราได้รับหลักฐานการโอนเงินสำหรับคำสั่งซื้อ #${pendingOrderData.id} แล้ว ทางร้านจะดำเนินการตรวจสอบยอดเงินและเริ่มแพ็คสินค้าให้โดยเร็วที่สุด`
                    );
                }, 1000);
            };
        }

        // Pay Later Button
        if (payLaterBtn) {
            payLaterBtn.onclick = () => {
                if (!pendingOrderData) return;
                
                pendingOrderData.status = 'Pending Payment';
                finalizeOrder(
                    "บันทึกคำสั่งซื้อเรียบร้อยแล้ว!",
                    `คำสั่งซื้อ #${pendingOrderData.id} ถูกบันทึกไว้ในสถานะ <span class="font-bold text-amber-600 font-sans">'ที่ต้องชำระ'</span> คุณสามารถสแกนจ่ายเงินได้ทุกเมื่อในหน้าประวัติคำสั่งซื้อ`
                );
            };
        }

        if (closeQrModalBtn) {
            closeQrModalBtn.onclick = () => {
                if (!pendingOrderData) return;
                pendingOrderData.status = 'Pending Payment';
                finalizeOrder(
                    "บันทึกคำสั่งซื้อเรียบร้อยแล้ว!",
                    `คำสั่งซื้อ #${pendingOrderData.id} ถูกบันทึกไว้ในสถานะ <span class="font-bold text-amber-600 font-sans">'ที่ต้องชำระ'</span> คุณสามารถสแกนจ่ายเงินได้ทุกเมื่อในหน้าประวัติคำสั่งซื้อ`
                );
            };
        }
    }

    function openQrModal() {
        if (!paymentQrModal) return;

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

    async function finalizeOrder(title, message) {
        closeQrModal();

        const userStr = localStorage.getItem('user');
        const user = userStr ? JSON.parse(userStr) : null;
        let customerId = user?.customer_id;
        let csrfToken = user?.csrf_token;

        if (!customerId) {
            try {
                const res = await fetch('/api/auth/me');
                if (res.ok) {
                    const result = await res.json();
                    if (result.data) {
                        customerId = result.data.customer_id;
                        csrfToken = result.data.csrf_token;
                        localStorage.setItem('user', JSON.stringify(result.data));
                    }
                }
            } catch (err) {
                console.error("Error resolving session:", err);
            }
        }

        let dbOrderId = pendingOrderData.id;

        try {
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
                payment_method: pendingOrderData.paymentMethod || 'transfer',
                slip_image: pendingOrderData.slipImage,
                csrf_token: csrfToken
            };

            const res = await fetch('/api/orders', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken || ''
                },
                body: JSON.stringify(orderPayload)
            });

            if (res.ok) {
                const resJson = await res.json();
                if (resJson.data && resJson.data.order_id) {
                    dbOrderId = resJson.data.order_id;
                    pendingOrderData.id = dbOrderId;
                    if (resJson.data.net_total !== undefined) {
                        pendingOrderData.total = parseFloat(resJson.data.net_total);
                    }
                    if (resJson.data.shipping_fee !== undefined) {
                        pendingOrderData.shipping = parseFloat(resJson.data.shipping_fee);
                    }
                }
            } else {
                const errRes = await res.json();
                console.error("Order creation failed details:", errRes);
                showToast(errRes.message || "เกิดข้อผิดพลาดในการบันทึกคำสั่งซื้อลงฐานข้อมูล", "error");
                return;
            }
        } catch (err) {
            console.error("Error creating order:", err);
            showToast("ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์เพื่อบันทึกคำสั่งซื้อได้", "error");
            return;
        }

        // Save order to localStorage 'myOrders'
        const orders = JSON.parse(localStorage.getItem('myOrders') || '[]');
        orders.unshift(pendingOrderData);
        localStorage.setItem('myOrders', JSON.stringify(orders));

        // Remove checked items from main cart
        const newCart = cart.filter(item => item.selected === false);
        localStorage.setItem('cart', JSON.stringify(newCart));
        updateGlobalCartCount();

        // Update Success Modal Content
        if (mockOrderId) mockOrderId.textContent = dbOrderId;
        if (successModalTitle) successModalTitle.textContent = title;
        if (successModalMessage) successModalMessage.innerHTML = message;

        // Show Success Modal
        if (successModal) {
            successModal.classList.remove('hidden');
            successModal.style.display = 'flex';
            requestAnimationFrame(() => {
                successModal.classList.remove('opacity-0', 'pointer-events-none');
                successModal.querySelector('div')?.classList.remove('scale-95');
                successModal.querySelector('div')?.classList.add('scale-100');
            });
        }

        const viewOrderBtn = document.getElementById('viewOrderBtn');
        if (viewOrderBtn) {
            viewOrderBtn.onclick = () => {
                const targetTab = pendingOrderData.status === 'Pending Payment' ? 'pending_payment' : 'preparing';
                if (window.navigateTo) {
                    window.navigateTo(`/orders?tab=${targetTab}`);
                } else {
                    window.location.href = `/orders?tab=${targetTab}`;
                }
            };
        }
    }

    init();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCheckoutPage);
} else {
    initCheckoutPage();
}
