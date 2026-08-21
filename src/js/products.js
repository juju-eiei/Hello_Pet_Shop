import { showToast, escapeHTML } from './utils.js';
import { updateGlobalCartCount } from './main.js';
import { getPersonalizedProducts, trackSearchQuery, trackAddToCart } from './recommendationEngine.js';

export function initProductsPage() {
    const productGrid = document.getElementById('productGrid');
    if (!productGrid) return;

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
        const rawValue = productSearch ? productSearch.value : '';
        const query = rawValue.trim().toLowerCase();
        
        let displayList = [];

        if (currentCategory === 'recommended') {
            // Run AI Recommendation Engine
            displayList = getPersonalizedProducts(allProducts);
            if (query) {
                displayList = displayList.filter(p => (p.product_name || '').toLowerCase().includes(query));
            }
        } else {
            displayList = allProducts.filter(p => {
                const matchesSearch = !query || (p.product_name || '').toLowerCase().includes(query);
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
                            <div class="absolute top-2 left-2 z-10 bg-gradient-to-r from-red-600 to-red-500 text-white text-[10px] sm:text-xs font-extrabold px-2.5 py-1 rounded-full shadow-md backdrop-blur-sm border border-white/30 flex items-center gap-1">
                                <span>${escapeHTML(p.aiReason)}</span>
                            </div>
                        ` : ''}
                        <img src="${escapeHTML(p.image_url || '/image/non-image.png')}" 
                            alt="${escapeHTML(p.product_name)}" 
                            onerror="this.src='/image/non-image.png'"
                            class="w-full h-full object-contain p-4 group-hover:scale-105 transition-transform duration-500">
                    </div>
                    <div class="text-center px-1">
                        <h3 class="text-sm font-semibold text-gray-800 mb-1 leading-tight h-10 line-clamp-2">${escapeHTML(p.product_name)}</h3>
                        <p class="text-secondary-600 font-extrabold text-base mb-3">฿${parseFloat(p.selling_price).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                    </div>
                </div>
                <div>
                    <button class="add-to-cart-btn w-full py-2.5 bg-secondary-600 hover:bg-secondary-700 text-white rounded-xl text-xs font-bold shadow-sm active:scale-95 transition-all flex items-center justify-center gap-1.5"
                        data-id="${escapeHTML(p.product_id)}" 
                        data-name="${escapeHTML(p.product_name)}" 
                        data-price="${escapeHTML(p.selling_price)}" 
                        data-image="${escapeHTML(p.image_url || '/image/non-image.png')}" 
                        data-category="${escapeHTML(p.category_name || '')}"
                        data-weight="${escapeHTML(p.weight || p.weight_value || '0')}"
                        data-weight-unit="${escapeHTML(p.weight_unit || 'kg')}">
                        <i class="fas fa-cart-plus"></i> หยิบใส่ตะกร้า
                    </button>
                </div>
            </div>
        `).join('');

        // Add event listeners to add-to-cart buttons
        document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const { id, name, price, image, category, weight, weightUnit } = e.currentTarget.dataset;
                let parsedWeight = parseFloat(weight) || 0;
                const u = (weightUnit || 'kg').toLowerCase().trim();
                if (u === 'g' || u === 'ml' || u === 'กรัม' || u === 'มิลลิลิตร') {
                    parsedWeight = parsedWeight / 1000.0;
                }
                addToCart({ 
                    id, 
                    name, 
                    price, 
                    image, 
                    category_name: category,
                    weight: parsedWeight,
                    weight_unit: weightUnit || 'kg'
                });
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

    // 4. Promo Banner Carousel (Dynamic from Backend API)
    async function initPromoCarousel() {
        const promoSlides = document.getElementById('promoSlides');
        const promoDotsContainer = document.getElementById('promoDots');
        const prevBtn = document.getElementById('promoPrevBtn');
        const nextBtn = document.getElementById('promoNextBtn');
        const carousel = document.getElementById('promoCarousel');

        if (!promoSlides || !promoDotsContainer) return;

        // Fetch active banners dynamically from backend
        let banners = [];
        try {
            const res = await fetch('/api/banners');
            if (res.ok) {
                const data = await res.json();
                banners = data.data || [];
            }
        } catch (e) {
            console.error('Failed to load dynamic banners:', e);
        }

        // Fallback default banners if none in DB
        if (banners.length === 0) {
            banners = [
                { title: 'โปรโมชั่น New Arrivals', image_url: '/images/promotions/promo1.png', link_url: '' },
                { title: 'ส่วนลดพิเศษประจำเดือน', image_url: '/images/promotions/promo2.png', link_url: '' },
                { title: 'อาหารสัตว์เลี้ยงพรีเมียม', image_url: '/images/promotions/promo3.png', link_url: '' }
            ];
        }

        // Render dynamic slides
        promoSlides.innerHTML = banners.map((b) => `
            <div class="w-full flex-shrink-0 relative ${b.link_url ? 'cursor-pointer' : ''}" ${b.link_url ? `onclick="(window.navigateTo ? window.navigateTo('${escapeHTML(b.link_url)}') : window.location.href='${escapeHTML(b.link_url)}')"` : ''}>
                <div class="w-full h-48 sm:h-64 md:h-80 relative overflow-hidden">
                    <img src="${escapeHTML(b.image_url)}" alt="${escapeHTML(b.title || 'โปรโมชั่น')}" 
                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                        onerror="this.src='/images/promotions/promo1.png'">
                </div>
            </div>
        `).join('');

        // Render dynamic dots
        promoDotsContainer.innerHTML = banners.map((_, idx) => `
            <button class="promo-dot w-2.5 h-2.5 rounded-full bg-white/50 transition-all duration-300 focus:outline-none" data-slide="${idx}" aria-label="Slide ${idx + 1}"></button>
        `).join('');

        const promoDots = document.querySelectorAll('.promo-dot');
        let currentSlide = 0;
        const totalSlides = banners.length;
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
            if (totalSlides > 1) {
                slideInterval = setInterval(() => {
                    goToSlide(currentSlide + 1);
                }, 4000);
            }
        }

        function stopAutoSlide() {
            if (slideInterval) clearInterval(slideInterval);
        }

        if (prevBtn) {
            prevBtn.onclick = () => {
                goToSlide(currentSlide - 1);
                startAutoSlide();
            };
        }

        if (nextBtn) {
            nextBtn.onclick = () => {
                goToSlide(currentSlide + 1);
                startAutoSlide();
            };
        }

        promoDots.forEach((dot, idx) => {
            dot.onclick = () => {
                goToSlide(idx);
                startAutoSlide();
            };
        });

        if (carousel) {
            carousel.onmouseenter = stopAutoSlide;
            carousel.onmouseleave = startAutoSlide;
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

        productSearch.addEventListener('change', () => {
            renderProducts();
        });

        productSearch.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                renderProducts();
            }
        });
    }

    // 6. Category Button Tabs Handler
    categoryBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            categoryBtns.forEach(b => {
                b.classList.remove('active', 'bg-gradient-to-r', 'from-secondary-600', 'to-secondary-500', 'text-white', 'shadow-sm', 'bg-secondary-200');
                if (b.dataset.category !== 'recommended') {
                    b.className = 'category-btn px-4 py-2 search-blue text-gray-800 text-xs sm:text-sm font-medium rounded-xl hover:bg-secondary-100 transition-all shrink-0';
                } else {
                    b.className = 'category-btn px-4 py-2 search-blue text-teal-800 text-xs sm:text-sm font-bold rounded-xl hover:bg-teal-100 transition-all shrink-0';
                }
            });

            currentCategory = btn.dataset.category;

            if (currentCategory === 'recommended') {
                btn.className = 'category-btn active px-4 py-2 bg-gradient-to-r from-secondary-600 to-secondary-500 text-white text-xs sm:text-sm font-bold rounded-xl shadow-sm hover:shadow-md transition-all flex items-center gap-1.5 shrink-0';
            } else {
                btn.classList.add('active', 'bg-secondary-200');
            }

            renderProducts();
        });
    });

    if (logoutBtn) {
        logoutBtn.addEventListener('click', () => {
            localStorage.removeItem('user');
            window.location.href = '/login';
        });
    }

    // Initialize
    fetchProducts();
    initPromoCarousel();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initProductsPage);
} else {
    initProductsPage();
}

