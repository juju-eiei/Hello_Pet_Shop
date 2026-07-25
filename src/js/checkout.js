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
    let shippingFee = 2.00; // Default Standard
    let pendingOrderData = null;
    let attachedSlipData = null;

    // Initialization
    function init() {
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

        renderSummary();
        attachEvents();
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
                        ฿${itemTotal.toFixed(2)}
                    </div>
                </div>
            `;
        });

        // Update Totals
        summarySubtotal.textContent = `฿${subtotal.toFixed(2)}`;
        summaryShipping.textContent = `฿${shippingFee.toFixed(2)}`;
        summaryTotal.textContent = `฿${(subtotal + shippingFee).toFixed(2)}`;
    }

    function attachEvents() {
        // Delivery Option Change
        document.querySelectorAll('input[name="deliveryMethod"]').forEach(radio => {
            radio.addEventListener('change', (e) => {
                const feeText = e.target.nextElementSibling.querySelector('.font-bold[data-fee]').dataset.fee;
                shippingFee = parseFloat(feeText);
                renderSummary();
            });
        });

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

            const deliveryMethod = document.querySelector('input[name="deliveryMethod"]:checked').value;
            const paymentMethod = document.querySelector('input[name="paymentMethod"]:checked')?.value || 'transfer';
            const fakeId = Math.floor(100000 + Math.random() * 900000);
            const totalAmount = subtotal + shippingFee;

            // Create Pending Order Draft
            pendingOrderData = {
                id: fakeId,
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
            qrModalAmount.textContent = `฿${totalAmount.toFixed(2)}`;

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

    function finalizeOrder(title, message) {
        closeQrModal();

        // Save order to localStorage 'myOrders'
        const orders = JSON.parse(localStorage.getItem('myOrders') || '[]');
        orders.unshift(pendingOrderData);
        localStorage.setItem('myOrders', JSON.stringify(orders));

        // Remove checked items from main cart
        const newCart = cart.filter(item => item.selected === false);
        localStorage.setItem('cart', JSON.stringify(newCart));
        updateGlobalCartCount();

        // Update Success Modal Content
        mockOrderId.textContent = pendingOrderData.id;
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
