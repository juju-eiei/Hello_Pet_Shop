import { updateGlobalCartCount } from './main.js';
import { showToast, getUserProfileData, saveUserProfileData } from './utils.js';

document.addEventListener('DOMContentLoaded', () => {
    // Elements
    const summaryItemsContainer = document.getElementById('summaryItemsContainer');
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

    // Initialization
    async function init() {
        // Fetch cart
        cart = JSON.parse(localStorage.getItem('cart') || '[]');
        
        // Filter only selected items
        checkoutItems = cart.filter(item => item.selected !== false);
        
        if (checkoutItems.length === 0) {
            window.location.href = 'cart.html'; // Redirect back if empty/none selected
            return;
        }

        // Pre-fill user profile data automatically
        const profile = getUserProfileData();
        if (profile.name) inputFullName.value = profile.name;
        if (profile.phone) inputPhone.value = profile.phone;
        if (profile.address) inputAddress.value = profile.address;
        if (profile.province) inputProvince.value = profile.province;
        if (profile.zipcode) inputZipcode.value = profile.zipcode;

        await loadDeliveryCompanies();
        renderSummary();
        attachEvents();
    }

    async function loadDeliveryCompanies() {
        const container = document.getElementById('deliveryMethodsContainer');
        if (!container) return;

        try {
            const res = await fetch('/api/deliveries/companies');
            const json = await res.json();
            if (res.ok && json.data && json.data.length > 0) {
                deliveryCompanies = json.data;
                renderDeliveryOptions();
                return;
            }
        } catch (err) {
            console.warn("Failed to fetch delivery companies:", err);
        }

        // Fallback default options if API has no companies
        deliveryCompanies = [
            { company_id: 1, company_name: 'Kerry Express', base_rate: 40 },
            { company_id: 2, company_name: 'Flash Express', base_rate: 35 },
            { company_id: 3, company_name: 'J&T Express', base_rate: 30 }
        ];
        renderDeliveryOptions();
    }

    function renderDeliveryOptions() {
        const container = document.getElementById('deliveryMethodsContainer');
        if (!container) return;

        container.innerHTML = deliveryCompanies.map((c, idx) => {
            const fee = parseFloat(c.base_rate) > 0 ? parseFloat(c.base_rate) : 35.00;
            const isChecked = idx === 0 ? 'checked' : '';
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
                                <div class="text-xs text-gray-500 mt-0.5">อัตราจัดส่งเริ่มต้น ฿${fee.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})} (ตั้งค่าโดยผู้ดูแลระบบ)</div>
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
        summarySubtotal.textContent = `฿${subtotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        summaryShipping.textContent = `฿${shippingFee.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        summaryTotal.textContent = `฿${(subtotal + shippingFee).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
    }

    function attachEvents() {
        // Confirm Order Click -> Open QR Modal
        confirmOrderBtn.addEventListener('click', () => {
            // Validate address
            const requiredFields = [inputFullName, inputPhone, inputAddress, inputProvince, inputZipcode];
            const isValid = requiredFields.every(field => field.value.trim() !== '');
            
            if (!isValid) {
                showToast("กรุณากรอกข้อมูลจัดส่งให้ครบถ้วน", "error");
                const emptyField = requiredFields.find(field => field.value.trim() === '');
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
                status: 'Pending Payment', // Default to 'ที่ต้องชำระ' until paid
                slipImage: null
            };

            // Display in QR Payment Modal
            qrModalOrderId.textContent = fakeId;
            qrModalAmount.textContent = `฿${totalAmount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

            // Show QR Modal
            openQrModal();
        });

        // Slip File Upload Preview
        slipFileInput?.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (event) => {
                    attachedSlipData = event.target.result;
                    slipFileName.textContent = file.name;
                    slipUploadPlaceholder.classList.add('hidden');
                    slipPreviewContainer.classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            }
        });

        removeSlipBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            slipFileInput.value = '';
            attachedSlipData = null;
            slipPreviewContainer.classList.add('hidden');
            slipUploadPlaceholder.classList.remove('hidden');
        });

        // Submit Payment Button in QR Modal
        submitPaymentBtn?.addEventListener('click', () => {
            if (!pendingOrderData) return;

            submitPaymentBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i><span>กำลังตรวจสอบ...</span>';
            submitPaymentBtn.disabled = true;

            setTimeout(() => {
                pendingOrderData.status = 'Preparing'; // Change status to 'ที่ต้องจัดส่ง'
                pendingOrderData.slipImage = attachedSlipData;
                pendingOrderData.paidAt = new Date().toISOString();
                
                finalizeOrder(
                    "ชำระเงินและสั่งซื้อสำเร็จ!",
                    `เราได้รับชำระเงินสำหรับคำสั่งซื้อ #${pendingOrderData.id} เรียบร้อยแล้ว ร้านค้าจะจัดส่งสินค้าให้โดยเร็วที่สุด`
                );
            }, 1000);
        });

        // Pay Later Button
        payLaterBtn?.addEventListener('click', () => {
            if (!pendingOrderData) return;
            
            pendingOrderData.status = 'Pending Payment'; // Keeps as 'ที่ต้องชำระ'
            finalizeOrder(
                "บันทึกคำสั่งซื้อเรียบร้อยแล้ว!",
                `คำสั่งซื้อ #${pendingOrderData.id} ถูกบันทึกไว้ในสถานะ <span class="font-bold text-amber-600 font-sans">'ที่ต้องชำระ'</span> คุณสามารถสแกนจ่ายเงินได้ทุกเมื่อในหน้าประวัติคำสั่งซื้อ`
            );
        });

        closeQrModalBtn?.addEventListener('click', () => {
            if (!pendingOrderData) return;
            pendingOrderData.status = 'Pending Payment';
            finalizeOrder(
                "บันทึกคำสั่งซื้อเรียบร้อยแล้ว!",
                `คำสั่งซื้อ #${pendingOrderData.id} ถูกบันทึกไว้ในสถานะ <span class="font-bold text-amber-600 font-sans">'ที่ต้องชำระ'</span> คุณสามารถสแกนจ่ายเงินได้ทุกเมื่อในหน้าประวัติคำสั่งซื้อ`
            );
        });
    }

    function openQrModal() {
        paymentQrModal.classList.remove('opacity-0', 'pointer-events-none');
        paymentQrModal.querySelector('div').classList.remove('scale-95');
        paymentQrModal.querySelector('div').classList.add('scale-100');
    }

    function closeQrModal() {
        paymentQrModal.classList.add('opacity-0', 'pointer-events-none');
        paymentQrModal.querySelector('div').classList.remove('scale-100');
        paymentQrModal.querySelector('div').classList.add('scale-95');
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
        if (customerId) {
            try {
                const orderPayload = {
                    customer_id: customerId,
                    company_id: pendingOrderData.company_id,
                    shipping_address: pendingOrderData.shippingAddress,
                    items: pendingOrderData.items.map(item => ({
                        product_id: item.id,
                        quantity: item.quantity
                    })),
                    shipping_fee: pendingOrderData.shipping,
                    discount_amount: 0,
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
                    }
                } else {
                    const errRes = await res.json();
                    console.error("Order creation failed details: " + JSON.stringify(errRes));
                    showToast(errRes.message || "เกิดข้อผิดพลาดในการบันทึกคำสั่งซื้อลงฐานข้อมูล", "error");
                    return;
                }
            } catch (err) {
                console.error("Error creating order:", err);
                showToast("ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์เพื่อบันทึกคำสั่งซื้อได้", "error");
                return;
            }
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
        mockOrderId.textContent = dbOrderId;
        if (successModalTitle) successModalTitle.textContent = title;
        if (successModalMessage) successModalMessage.innerHTML = message;

        // Show Success Modal
        successModal.classList.remove('opacity-0', 'pointer-events-none');
        successModal.querySelector('div').classList.remove('scale-95');
        successModal.querySelector('div').classList.add('scale-100');

        const viewOrderBtn = document.getElementById('viewOrderBtn');
        if (viewOrderBtn) {
            viewOrderBtn.addEventListener('click', () => {
                const targetTab = pendingOrderData.status === 'Pending Payment' ? 'pending_payment' : 'preparing';
                window.location.href = `order-history.html?tab=${targetTab}`;
            });
        }
    }

    init();
});
