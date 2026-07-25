import { showToast } from './utils.js';
import { updateGlobalCartCount } from './main.js';
import { getPersonalizedProducts, trackSearchQuery, trackAddToCart } from './recommendationEngine.js';

document.addEventListener('DOMContentLoaded', () => {
    const productGrid = document.getElementById('productGrid');
    const productSearch = document.getElementById('productSearch');
    const categoryBtns = document.querySelectorAll('.category-btn');
    const logoutBtn = document.getElementById('logoutBtn');
    
    let allProducts = [];
    let currentCategory = 'recommended';

    // 1. Fetch Products
    async function fetchProducts() {
        try {
            const response = await fetch('/api/products');
            const result = await response.json();
            
            if (response.ok) {
                allProducts = result.data || [];
                renderProducts();
            } else {
                showToast("โหลดสินค้าไม่สำเร็จ", "error");
            }
        } catch (error) {
            console.error("Error fetching products:", error);
            showToast("การเชื่อมต่อมีปัญหา", "error");
        }
    }

    // 2. Render Products
    function renderProducts() {
        const query = productSearch ? productSearch.value.toLowerCase().trim() : '';
        
        let displayList = [];

        if (currentCategory === 'recommended') {
            // Run AI Recommendation Engine
            displayList = getPersonalizedProducts(allProducts);
            if (query) {
                displayList = displayList.filter(p => p.product_name.toLowerCase().includes(query));
            }
        } else {
            displayList = allProducts.filter(p => {
                const matchesSearch = !query || p.product_name.toLowerCase().includes(query);
                const matchesCategory = currentCategory === 'all' || 
                                       (p.category_name && p.category_name.toLowerCase().includes(currentCategory.toLowerCase()));
                return matchesSearch && matchesCategory;
            });
        }

        if (displayList.length === 0) {
            productGrid.innerHTML = `
                <div class="col-span-full py-20 text-center text-gray-500">
                    <i class="fas fa-box-open text-4xl mb-4 block text-gray-300"></i>
                    ไม่พบสินค้าที่ตรงกับการค้นหา
                </div>
            `;
            return;
        }

        productGrid.innerHTML = displayList.map(p => `
            <div class="product-card group cursor-pointer relative flex flex-col justify-between">
                <div>
                    <div class="relative aspect-square bg-[#f8f9fa] rounded-3xl overflow-hidden mb-4 shadow-sm group-hover:shadow-md transition-all">
                        ${currentCategory === 'recommended' && p.aiReason ? `
                            <div class="absolute top-2 left-2 z-10 bg-gradient-to-r from-emerald-600 to-teal-600 text-white text-[10px] sm:text-xs font-extrabold px-2.5 py-1 rounded-full shadow-md backdrop-blur-sm border border-white/30 flex items-center gap-1">
                                <span>${p.aiReason}</span>
                            </div>
                        ` : ''}
                        <img src="${p.image_url || '/image/non-image.png'}" 
                            alt="${p.product_name}" 
                            onerror="this.src='/image/non-image.png'"
                            class="w-full h-full object-contain p-4 group-hover:scale-105 transition-transform duration-500">
                    </div>
                    <div class="text-center px-1">
                        <h3 class="text-sm font-semibold text-gray-800 mb-1 leading-tight h-10 line-clamp-2">${p.product_name}</h3>
                        <p class="text-blue-600 font-extrabold text-base mb-3">฿${parseFloat(p.selling_price).toFixed(2)}</p>
                    </div>
                </div>
                <div>
                    <button class="add-to-cart-btn w-full py-2.5 bg-[#8bb35c] hover:bg-[#7a9e4f] text-white rounded-xl text-xs font-bold shadow-sm active:scale-95 transition-all flex items-center justify-center gap-1.5"
                        data-id="${p.product_id}" data-name="${p.product_name}" data-price="${p.selling_price}" data-image="${p.image_url || '/image/non-image.png'}" data-category="${p.category_name || ''}">
                        <i class="fas fa-cart-plus"></i> หยิบใส่ตะกร้า
                    </button>
                </div>
            </div>
        `).join('');

        // Add event listeners to add-to-cart buttons
        document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const { id, name, price, image, category } = e.currentTarget.dataset;
                addToCart({ id, name, price, image, category_name: category });
            });
        });
    }

    // 3. Add to Cart with Behavior Tracking
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
        
        // Track Add to Cart for AI Model
        trackAddToCart(product);

        showToast(`เพิ่ม ${product.name} ลงในตะกร้าแล้ว`, "success");
    }

    // 4. Promo Banner Carousel
    function initPromoCarousel() {
        const promoSlides = document.getElementById('promoSlides');
        const promoDots = document.querySelectorAll('.promo-dot');
        const prevBtn = document.getElementById('promoPrevBtn');
        const nextBtn = document.getElementById('promoNextBtn');
        const carousel = document.getElementById('promoCarousel');

        if (!promoSlides || promoDots.length === 0) return;

        let currentSlide = 0;
        const totalSlides = promoDots.length;
        let slideInterval;

        function goToSlide(index) {
            currentSlide = (index + totalSlides) % totalSlides;
            promoSlides.style.transform = `translateX(-${currentSlide * 100}%)`;

            promoDots.forEach((dot, idx) => {
                if (idx === currentSlide) {
                    dot.classList.remove('bg-white/50', 'w-2.5');
                    dot.classList.add('bg-white', 'w-6');
                } else {
                    dot.classList.remove('bg-white', 'w-6');
                    dot.classList.add('bg-white/50', 'w-2.5');
                }
            });
        }

        function startAutoSlide() {
            stopAutoSlide();
            slideInterval = setInterval(() => {
                goToSlide(currentSlide + 1);
            }, 4000);
        }

        function stopAutoSlide() {
            if (slideInterval) clearInterval(slideInterval);
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                goToSlide(currentSlide - 1);
                startAutoSlide();
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                goToSlide(currentSlide + 1);
                startAutoSlide();
            });
        }

        promoDots.forEach((dot, idx) => {
            dot.addEventListener('click', () => {
                goToSlide(idx);
                startAutoSlide();
            });
        });

        if (carousel) {
            carousel.addEventListener('mouseenter', stopAutoSlide);
            carousel.addEventListener('mouseleave', startAutoSlide);
        }

        goToSlide(0);
        startAutoSlide();
    }

    // 5. Search Input Handler with Behavior Tracking
    let searchDebounce = null;
    if (productSearch) {
        productSearch.addEventListener('input', (e) => {
            renderProducts();
            
            clearTimeout(searchDebounce);
            searchDebounce = setTimeout(() => {
                const query = e.target.value.trim();
                if (query.length >= 2) {
                    trackSearchQuery(query);
                }
            }, 600);
        });
    }

    // 6. Category Button Tabs Handler
    categoryBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            categoryBtns.forEach(b => {
                b.classList.remove('active', 'bg-gradient-to-r', 'from-emerald-600', 'to-teal-600', 'text-white', 'shadow-sm', 'bg-blue-200');
                if (b.dataset.category !== 'recommended') {
                    b.className = 'category-btn px-4 py-2 search-blue text-gray-800 text-xs sm:text-sm font-medium rounded-xl hover:bg-blue-200 transition-all shrink-0';
                } else {
                    b.className = 'category-btn px-4 py-2 search-blue text-teal-800 text-xs sm:text-sm font-bold rounded-xl hover:bg-teal-100 transition-all shrink-0';
                }
            });

            currentCategory = btn.dataset.category;

            if (currentCategory === 'recommended') {
                btn.className = 'category-btn active px-4 py-2 bg-gradient-to-r from-emerald-600 to-teal-600 text-white text-xs sm:text-sm font-bold rounded-xl shadow-sm hover:shadow-md transition-all flex items-center gap-1.5 shrink-0';
            } else {
                btn.classList.add('active', 'bg-blue-200');
            }

            renderProducts();
        });
    });

    if (logoutBtn) {
        logoutBtn.addEventListener('click', () => {
            localStorage.removeItem('user');
            window.location.href = 'login.html';
        });
    }

    // Initialize
    fetchProducts();
    initPromoCarousel();
});
