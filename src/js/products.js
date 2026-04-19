import { showToast } from './utils.js';
import { updateGlobalCartCount } from './main.js';

document.addEventListener('DOMContentLoaded', () => {
    const productGrid = document.getElementById('productGrid');
    const productSearch = document.getElementById('productSearch');
    const categoryBtns = document.querySelectorAll('.category-btn');
    const logoutBtn = document.getElementById('logoutBtn');
    
    let allProducts = [];
    let currentCategory = 'all';

    // 1. Fetch Products
    async function fetchProducts() {
        try {
            const response = await fetch('/api/products');
            const result = await response.json();
            
            if (response.ok) {
                allProducts = result.data;
                renderProducts();
            } else {
                showToast("Failed to load products", "error");
            }
        } catch (error) {
            console.error("Error fetching products:", error);
            showToast("Connection error", "error");
        }
    }

    // 2. Render Products
    function renderProducts() {
        const query = productSearch.value.toLowerCase();
        
        const filtered = allProducts.filter(p => {
            const matchesSearch = p.product_name.toLowerCase().includes(query);
            const matchesCategory = currentCategory === 'all' || 
                                   (p.category_name && p.category_name.toLowerCase().includes(currentCategory.toLowerCase()));
            return matchesSearch && matchesCategory;
        });

        if (filtered.length === 0) {
            productGrid.innerHTML = `
                <div class="col-span-full py-20 text-center text-gray-500">
                    <i class="fas fa-box-open text-4xl mb-4 block"></i>
                    No products found.
                </div>
            `;
            return;
        }

        productGrid.innerHTML = filtered.map(p => `
            <div class="product-card group cursor-pointer">
                <div class="relative aspect-square bg-[#f8f9fa] rounded-3xl overflow-hidden mb-4 shadow-sm group-hover:shadow-md transition-all">
                    <img src="${p.image_url || 'https://placehold.co/400x400?text=' + encodeURIComponent(p.product_name)}" 
                        alt="${p.product_name}" 
                        class="w-full h-full object-contain p-4 group-hover:scale-105 transition-transform duration-500">
                </div>
                <div class="text-center px-1">
                    <h3 class="text-sm font-semibold text-gray-800 mb-1 leading-tight h-10 line-clamp-2">${p.product_name}</h3>
                    <p class="text-blue-500 font-bold text-sm mb-3">$${parseFloat(p.selling_price).toFixed(2)}</p>
                    <button class="add-to-cart-btn w-full py-2 btn-green text-white rounded-lg text-xs font-semibold shadow-sm active:scale-95 transition-all"
                        data-id="${p.product_id}" data-name="${p.product_name}" data-price="${p.selling_price}" data-image="${p.image_url || 'https://placehold.co/400x400?text=' + encodeURIComponent(p.product_name)}">
                        Add to Cart
                    </button>
                </div>
            </div>
        `).join('');

        // Add event listeners to add-to-cart buttons
        document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const { id, name, price, image } = e.target.dataset;
                addToCart({ id, name, price, image });
            });
        });
    }

    // 3. Add to Cart (Local for now)
    function addToCart(product) {
        let cart = JSON.parse(localStorage.getItem('cart') || '[]');
        const existing = cart.find(item => item.id === product.id);
        
        if (existing) {
            existing.quantity += 1;
        } else {
            cart.push({ ...product, quantity: 1 });
        }
        
        localStorage.setItem('cart', JSON.stringify(cart));
        updateGlobalCartCount();
        showToast(`Added ${product.name} to cart`, "success");
    }

    // 4. Listeners
    productSearch.addEventListener('input', renderProducts);

    categoryBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            categoryBtns.forEach(b => b.classList.remove('active', 'bg-blue-200'));
            btn.classList.add('active', 'bg-blue-200');
            currentCategory = btn.dataset.category;
            renderProducts();
        });
    });

    logoutBtn.addEventListener('click', () => {
        localStorage.removeItem('user');
        window.location.href = '/login';
    });

    // Initialize
    fetchProducts();
});
