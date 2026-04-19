import { updateGlobalCartCount } from './main.js';
import { showToast } from './utils.js';

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

    // Modals
    const successModal = document.getElementById('successModal');
    const mockOrderId = document.getElementById('mockOrderId');
    
    // State
    let cart = [];
    let checkoutItems = [];
    let subtotal = 0;
    let shippingFee = 2.00; // Default Standard

    // Initialization
    function init() {
        // Fetch cart
        cart = JSON.parse(localStorage.getItem('cart') || '[]');
        
        // Filter only selected items
        checkoutItems = cart.filter(item => item.selected !== false);
        
        if (checkoutItems.length === 0) {
            window.location.href = '/cart'; // Redirect back if empty/none selected
            return;
        }

        // Pre-fill user data if available
        const userStr = localStorage.getItem('userProfileData');
        if (userStr) {
            try {
                const userData = JSON.parse(userStr);
                if (userData.name) {
                    inputFullName.value = userData.name;
                }
                if (userData.phone) inputPhone.value = userData.phone;
                if (userData.address) {
                    // Very simple parse attempt
                    const addressParts = userData.address.split('\n');
                    inputAddress.value = addressParts.slice(0, 2).join(' ');
                    const lastLine = addressParts[addressParts.length - 1];
                    if (lastLine) {
                        const zipMatch = lastLine.match(/\d{5}/);
                        if (zipMatch) inputZipcode.value = zipMatch[0];
                        inputProvince.value = lastLine.replace(/\d{5}/, '').trim();
                    }
                }
            } catch(e) {}
        }

        renderSummary();
        attachEvents();
    }

    function renderSummary() {
        summaryItemsContainer.innerHTML = '';
        subtotal = 0;

        checkoutItems.forEach(item => {
            const itemTotal = parseFloat(item.price) * item.quantity;
            subtotal += itemTotal;
            
            const imageUrl = item.image || `https://placehold.co/100x100?text=${encodeURIComponent(item.name)}`;

            summaryItemsContainer.innerHTML += `
                <div class="flex items-center space-x-3">
                    <img src="${imageUrl}" alt="" class="w-12 h-12 bg-gray-50 rounded-lg object-contain p-1 border border-gray-100">
                    <div class="flex-1">
                        <h4 class="text-sm font-bold text-gray-800 line-clamp-1">${item.name}</h4>
                        <div class="text-xs text-gray-500">Qty: ${item.quantity}</div>
                    </div>
                    <div class="text-sm border-gray-800 font-medium whitespace-nowrap">
                        $${itemTotal.toFixed(2)}
                    </div>
                </div>
            `;
        });

        // Update Totals
        summarySubtotal.textContent = `$${subtotal.toFixed(2)}`;
        summaryShipping.textContent = `$${shippingFee.toFixed(2)}`;
        summaryTotal.textContent = `$${(subtotal + shippingFee).toFixed(2)}`;
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

        // Form Submit
        confirmOrderBtn.addEventListener('click', () => {
            // Validate address
            const requiredFields = [inputFullName, inputPhone, inputAddress, inputProvince, inputZipcode];
            const isValid = requiredFields.every(field => field.value.trim() !== '');
            
            if (!isValid) {
                showToast("กรุณากรอกข้อมูลจัดส่งให้ครบถ้วน", "error");
                // Focus first empty field
                const emptyField = requiredFields.find(field => field.value.trim() === '');
                if (emptyField) emptyField.focus();
                return;
            }

            // Process order (MOCK)
            const deliveryMethod = document.querySelector('input[name="deliveryMethod"]:checked').value;
            const paymentMethod = document.querySelector('input[name="paymentMethod"]:checked').value;
            
            confirmOrderBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i><span>กำลังประมวลผล...</span>';
            confirmOrderBtn.disabled = true;

            setTimeout(() => {
                completeOrder();
            }, 1500);
        });
    }

    function completeOrder() {
        const deliveryMethod = document.querySelector('input[name="deliveryMethod"]:checked').value;
        const paymentMethod = document.querySelector('input[name="paymentMethod"]:checked').value;
        const fakeId = Math.floor(100000 + Math.random() * 900000);

        // Save to Orders History
        const orders = JSON.parse(localStorage.getItem('myOrders') || '[]');
        const newOrder = {
            id: fakeId,
            date: new Date().toISOString(),
            items: checkoutItems,
            subtotal: subtotal,
            shipping: shippingFee,
            total: subtotal + shippingFee,
            deliveryMethod: deliveryMethod,
            paymentMethod: paymentMethod,
            status: 'Preparing'
        };
        orders.unshift(newOrder); // Add to beginning
        localStorage.setItem('myOrders', JSON.stringify(orders));

        // Remove checked out items from main cart
        const newCart = cart.filter(item => item.selected === false);
        localStorage.setItem('cart', JSON.stringify(newCart));
        updateGlobalCartCount(); // Fix badge

        // Generate mock order ID
        mockOrderId.textContent = fakeId;

        // Show Success Modal
        successModal.classList.remove('opacity-0', 'pointer-events-none');
        successModal.querySelector('div').classList.remove('scale-95');
        successModal.querySelector('div').classList.add('scale-100');

        const viewOrderBtn = document.getElementById('viewOrderBtn');
        viewOrderBtn.addEventListener('click', () => {
             window.location.href = '/order-history';
        });
    }

    init();
});
