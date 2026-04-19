import { updateGlobalCartCount } from './main.js';

document.addEventListener('DOMContentLoaded', () => {
    const cartItemsContainer = document.getElementById('cartItemsContainer');
    const cartSubtotal = document.getElementById('cartSubtotal');
    
    function renderCart() {
        const cart = JSON.parse(localStorage.getItem('cart') || '[]');
        
        if (cart.length === 0) {
            cartItemsContainer.innerHTML = `
                <div class="py-12 text-center text-gray-500">
                    <i class="fas fa-shopping-basket text-5xl mb-4 text-gray-300"></i>
                    <h2 class="text-xl font-medium text-gray-700">Your cart is empty</h2>
                    <p class="mt-2 text-gray-400">Looks like you haven't added anything yet.</p>
                    <a href="/products" class="inline-block mt-6 px-6 py-2 bg-blue-100 text-blue-700 font-medium rounded-lg hover:bg-blue-200 transition-colors">Start Shopping</a>
                </div>
            `;
            cartSubtotal.textContent = '$0.00';
            updateGlobalCartCount();
            return;
        }

        // Determine if all are selected
        const allSelected = cart.every(item => item.selected !== false);

        let total = 0;
        
        // Header for Select All
        let htmlSnippet = `
            <div class="flex items-center mb-6 pb-5 border-b border-gray-200/60">
                <label class="flex items-center cursor-pointer group">
                    <input type="checkbox" id="selectAllCheckbox" class="w-[18px] h-[18px] rounded border-gray-300 text-[#8bb35c] focus:ring-[#8bb35c] cursor-pointer cursor-pointer transition-all" ${allSelected ? 'checked' : ''}>
                    <span class="ml-3 text-[#1f2937] font-medium group-hover:text-[#8bb35c] transition-colors">เลือกสินค้าทั้งหมด</span>
                </label>
            </div>
        `;

        htmlSnippet += cart.map((item, index) => {
            const isSelected = item.selected !== false;
            const itemTotal = parseFloat(item.price) * item.quantity;
            
            if (isSelected) {
                total += itemTotal;
            }
            
            // Image fallback logic handling
            const imageUrl = item.image || `https://placehold.co/400x400?text=${encodeURIComponent(item.name)}`;
            
            return `
            <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-8 pb-8 border-b border-[#e2e8f0] last:border-0 last:pb-0 last:mb-0 relative py-2 transition-opacity ${!isSelected ? 'opacity-60' : ''}">
                <div class="flex items-center space-x-5">
                    <!-- Checkbox -->
                    <input type="checkbox" class="item-checkbox w-[18px] h-[18px] rounded border-gray-300 text-[#8bb35c] focus:ring-[#8bb35c] cursor-pointer" data-id="${item.id}" ${isSelected ? 'checked' : ''}>
                    
                    <!-- Product Image -->
                    <div class="w-24 h-24 bg-white rounded-2xl flex-shrink-0 flex items-center justify-center p-3 shadow-sm ${!isSelected ? 'grayscale-[30%]' : ''}">
                        <img src="${imageUrl}" alt="${item.name}" class="w-full h-full object-contain">
                    </div>
                    <!-- Product Info -->
                    <div>
                        <h3 class="text-[#1f2937] font-medium text-[17px]">${item.name}</h3>
                        <p class="text-[#5e8b7e] text-[15px] mt-1.5 font-normal">จำนวน: ${item.quantity}</p>
                    </div>
                </div>
                
                <div class="flex items-center justify-between sm:w-auto w-full mt-6 sm:mt-0 px-2 sm:px-0 sm:ml-0 ml-10 sm:space-x-24">
                    <!-- Price -->
                    <span class="text-[#1f2937] font-medium text-[17px]">$${itemTotal.toFixed(2)}</span>
                    
                    <!-- Internal Quantity Adjuster -->
                    <div class="flex items-center space-x-6">
                        <button class="cart-minus w-8 h-8 rounded-full bg-[#f2eef4] flex items-center justify-center hover:bg-gray-200 text-gray-700 transition-colors" data-id="${item.id}">
                            <i class="fas fa-minus text-[10px] pointer-events-none"></i>
                        </button>
                        <span class="w-2 text-center font-medium text-gray-800 text-[15px]">${item.quantity}</span>
                        <button class="cart-plus w-8 h-8 rounded-full bg-[#f2eef4] flex items-center justify-center hover:bg-gray-200 text-gray-700 transition-colors" data-id="${item.id}">
                            <i class="fas fa-plus text-[10px] pointer-events-none"></i>
                        </button>
                    </div>
                </div>
            </div>
            `;
        }).join('');
        
        cartItemsContainer.innerHTML = htmlSnippet;
        
        cartSubtotal.textContent = `$${total.toFixed(2)}`;
        
        // Attach events
        document.querySelectorAll('.cart-minus').forEach(btn => {
            btn.addEventListener('click', (e) => {
                updateQuantity(e.currentTarget.dataset.id, -1);
            });
        });
        document.querySelectorAll('.cart-plus').forEach(btn => {
            btn.addEventListener('click', (e) => {
                updateQuantity(e.currentTarget.dataset.id, 1);
            });
        });

        // Attach Checkbox Events
        const selectAllBtn = document.getElementById('selectAllCheckbox');
        if (selectAllBtn) {
            selectAllBtn.addEventListener('change', (e) => {
                toggleAllSelection(e.target.checked);
            });
        }
        
        document.querySelectorAll('.item-checkbox').forEach(chk => {
            chk.addEventListener('change', (e) => {
                toggleItemSelection(e.target.dataset.id, e.target.checked);
            });
        });

        updateGlobalCartCount();
    }

    function updateQuantity(id, change) {
        let cart = JSON.parse(localStorage.getItem('cart') || '[]');
        const index = cart.findIndex(item => item.id == id);
        if (index > -1) {
            cart[index].quantity += change;
            if (cart[index].quantity <= 0) {
                // remove item
                cart.splice(index, 1);
            }
            localStorage.setItem('cart', JSON.stringify(cart));
            renderCart();
        }
    }

    function toggleItemSelection(id, isSelected) {
        let cart = JSON.parse(localStorage.getItem('cart') || '[]');
        const index = cart.findIndex(item => item.id == id);
        if (index > -1) {
            cart[index].selected = isSelected;
            localStorage.setItem('cart', JSON.stringify(cart));
            renderCart();
        }
    }

    function toggleAllSelection(isSelected) {
        let cart = JSON.parse(localStorage.getItem('cart') || '[]');
        cart = cart.map(item => ({ ...item, selected: isSelected }));
        localStorage.setItem('cart', JSON.stringify(cart));
        renderCart();
    }



    const checkoutBtn = document.getElementById('checkoutBtn');
    if (checkoutBtn) {
        checkoutBtn.addEventListener('click', () => {
            const cart = JSON.parse(localStorage.getItem('cart') || '[]');
            const hasSelected = cart.some(item => item.selected !== false);
            if (!hasSelected || cart.length === 0) {
                // If nothing is selected or cart is empty
                const toast = document.getElementById('toast');
                if (toast) {
                    toast.className = `fixed bottom-8 left-1/2 -translate-x-1/2 px-6 py-3 rounded-xl shadow-xl transition-all duration-500 z-50 bg-red-500 text-white font-medium opacity-100 translate-y-0`;
                    toast.textContent = "Please select at least one item to checkout.";
                    setTimeout(() => {
                        toast.className = `fixed bottom-8 left-1/2 -translate-x-1/2 px-6 py-3 rounded-xl shadow-xl transition-all duration-500 z-50 opacity-0 translate-y-4 pointer-events-none`;
                    }, 3000);
                }
                return;
            }
            window.location.href = '/checkout';
        });
    }

    // Initial render
    renderCart();
});
