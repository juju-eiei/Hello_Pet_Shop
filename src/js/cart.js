import { updateGlobalCartCount } from './main.js';
import { getCartData, saveCartData } from './utils.js';

export function initCartPage() {
    const cartItemsContainer = document.getElementById('cartItemsContainer');
    if (!cartItemsContainer) return;

    const cartSubtotal = document.getElementById('cartSubtotal');
    
    function renderCart() {
        const cart = getCartData();
        
        if (cart.length === 0) {
            cartItemsContainer.innerHTML = `
                <div class="py-12 text-center text-gray-500">
                    <i class="fas fa-shopping-basket text-5xl mb-4 text-gray-300"></i>
                    <h2 class="text-xl font-medium text-gray-700">ไม่มีสินค้าในตะกร้าของคุณ</h2>
                    <p class="mt-2 text-gray-400">ดูเหมือนว่าคุณยังไม่ได้เพิ่มสินค้าใด ๆ ลงในตะกร้าเลย</p>
                    <a href="/products" class="inline-block mt-6 px-6 py-2 bg-[#4D7C68] text-white font-medium rounded-lg hover:bg-[#3D6353] transition-colors">เริ่มเลือกซื้อสินค้า</a>
                </div>
            `;
            if (cartSubtotal) cartSubtotal.textContent = '฿0.00';
            updateGlobalCartCount();
            return;
        }

        const allSelected = cart.every(item => item.selected !== false);
        let total = 0;
        
        let htmlSnippet = `
            <div class="flex items-center mb-6 pb-5 border-b border-gray-200/60">
                <label class="flex items-center cursor-pointer group">
                    <input type="checkbox" id="selectAllCheckbox" class="w-[18px] h-[18px] rounded border-gray-300 text-[#16a34a] focus:ring-[#16a34a] cursor-pointer transition-all" ${allSelected ? 'checked' : ''}>
                    <span class="ml-3 text-[#1f2937] font-medium group-hover:text-[#16a34a] transition-colors">เลือกสินค้าทั้งหมด</span>
                </label>
            </div>
        `;

        htmlSnippet += cart.map((item) => {
            const isSelected = item.selected !== false;
            const itemTotal = parseFloat(item.price) * item.quantity;
            
            if (isSelected) {
                total += itemTotal;
            }
            
            const imageUrl = item.image || '/image/non-image.png';
            
            return `
                <div class="flex items-center justify-between py-4 border-b border-gray-100 last:border-0 group">
                    <div class="flex items-center space-x-4">
                        <input type="checkbox" class="item-checkbox w-4 h-4 rounded border-gray-300 text-[#16a34a] focus:ring-[#16a34a] cursor-pointer" data-id="${item.id}" ${isSelected ? 'checked' : ''}>
                        <img src="${imageUrl}" onerror="this.src='/image/non-image.png'" alt="${item.name}" class="w-16 h-16 object-contain rounded-lg border border-gray-100 p-1">
                        <div>
                            <h3 class="font-semibold text-gray-800 group-hover:text-[#16a34a] transition-colors">${item.name}</h3>
                            <p class="text-sm text-gray-500 font-mono mt-0.5">฿${parseFloat(item.price).toFixed(2)}</p>
                        </div>
                    </div>

                    <div class="flex items-center space-x-6">
                        <div class="flex items-center border border-gray-200 rounded-lg overflow-hidden bg-white shadow-xs">
                            <button class="decrease-btn px-3 py-1 bg-gray-50 hover:bg-gray-100 text-gray-600 transition-colors" data-id="${item.id}">-</button>
                            <span class="px-3 py-1 text-sm font-semibold text-gray-700 min-w-[32px] text-center">${item.quantity}</span>
                            <button class="increase-btn px-3 py-1 bg-gray-50 hover:bg-gray-100 text-gray-600 transition-colors" data-id="${item.id}">+</button>
                        </div>

                        <span class="font-bold text-gray-800 font-mono min-w-[80px] text-right">฿${itemTotal.toFixed(2)}</span>

                        <button class="remove-btn text-gray-400 hover:text-red-500 transition-colors p-1" data-id="${item.id}">
                            <i class="fas fa-trash-can text-sm"></i>
                        </button>
                    </div>
                </div>
            `;
        }).join('');
        
        cartItemsContainer.innerHTML = htmlSnippet;
        
        if (cartSubtotal) {
            cartSubtotal.textContent = `฿${total.toFixed(2)}`;
        }
        
        document.querySelectorAll('.increase-btn').forEach(btn => {
            btn.onclick = () => updateQuantity(btn.dataset.id, 1);
        });

        document.querySelectorAll('.decrease-btn').forEach(btn => {
            btn.onclick = () => updateQuantity(btn.dataset.id, -1);
        });

        document.querySelectorAll('.remove-btn').forEach(btn => {
            btn.onclick = () => removeItem(btn.dataset.id);
        });

        document.querySelectorAll('.item-checkbox').forEach(chk => {
            chk.onchange = (e) => toggleItemSelection(e.target.dataset.id, e.target.checked);
        });

        const selectAllCheckbox = document.getElementById('selectAllCheckbox');
        if (selectAllCheckbox) {
            selectAllCheckbox.onchange = (e) => toggleAllSelection(e.target.checked);
        }

        updateGlobalCartCount();
    }

    function updateQuantity(id, change) {
        let cart = getCartData();
        const index = cart.findIndex(item => item.id == id);
        if (index > -1) {
            cart[index].quantity += change;
            if (cart[index].quantity <= 0) {
                cart.splice(index, 1);
            }
            saveCartData(cart);
            renderCart();
        }
    }

    function removeItem(id) {
        let cart = getCartData();
        const index = cart.findIndex(item => item.id == id);
        if (index > -1) {
            cart.splice(index, 1);
            saveCartData(cart);
            renderCart();
        }
    }

    function toggleItemSelection(id, isSelected) {
        let cart = getCartData();
        const index = cart.findIndex(item => item.id == id);
        if (index > -1) {
            cart[index].selected = isSelected;
            saveCartData(cart);
            renderCart();
        }
    }

    function toggleAllSelection(isSelected) {
        let cart = getCartData();
        cart = cart.map(item => ({ ...item, selected: isSelected }));
        saveCartData(cart);
        renderCart();
    }

    const checkoutBtn = document.getElementById('checkoutBtn');
    if (checkoutBtn) {
        checkoutBtn.onclick = () => {
            const cart = JSON.parse(localStorage.getItem('cart') || '[]');
            const hasSelected = cart.some(item => item.selected !== false);
            if (!hasSelected || cart.length === 0) {
                // If nothing is selected or cart is empty
                const toast = document.getElementById('toast');
                if (toast) {
                    toast.className = `fixed bottom-8 left-1/2 -translate-x-1/2 px-6 py-3 rounded-xl shadow-xl transition-all duration-500 z-50 bg-red-500 text-white font-medium opacity-100 translate-y-0`;
                    toast.textContent = "กรุณาเลือกสินค้าอย่างน้อยหนึ่งชิ้นก่อนไปชำระเงิน";
                    setTimeout(() => {
                        toast.className = `fixed bottom-8 left-1/2 -translate-x-1/2 px-6 py-3 rounded-xl shadow-xl transition-all duration-500 z-50 opacity-0 translate-y-4 pointer-events-none`;
                    }, 3000);
                }
                return;
            }
            if (window.navigateTo) {
                window.navigateTo('/checkout');
            } else {
                window.location.href = '/checkout';
            }
        };
    }

    // Initial render
    renderCart();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCartPage);
} else {
    initCartPage();
}

