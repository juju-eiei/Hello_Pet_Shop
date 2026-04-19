const fs = require('fs');

let c = fs.readFileSync('c:/laragon/www/Hello_Pet_Shop/admin_stock.html', 'utf8');

const newCSS = `
    <style>
        /* Modern UI Redesign */
        body { background-color: #f8fafc; font-family: 'Inter', 'Noto Sans Thai', sans-serif; }
        
        /* Product Card Layout */
        .stock-card {
            display: flex !important;
            flex-direction: row !important;
            align-items: center !important;
            padding: 16px !important;
            background: #ffffff !important;
            border-radius: 16px !important;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03) !important;
            margin-bottom: 16px !important;
            border: 1px solid #f1f5f9 !important;
            width: 100% !important;
            box-sizing: border-box !important;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stock-card:active { transform: scale(0.98); }
        .stock-card-left {
            flex: 0 0 60px;
            margin-right: 16px;
        }
        .stock-card-img {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            object-fit: cover;
            background: #f1f5f9;
        }
        .stock-card-middle {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: flex-start;
        }
        .stock-card-name {
            font-size: 15px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .stock-status-pill {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .pill-in-stock { background: #dcfce7; color: #15803d; }
        .pill-low-stock { background: #fef9c3; color: #b45309; }
        .pill-out-of-stock { background: #fee2e2; color: #b91c1c; }

        .stock-card-right {
            flex: 0 0 auto;
            text-align: right;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            justify-content: center;
            padding-left: 12px;
        }
        .stock-qty-text {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 2px;
            font-weight: 500;
        }
        .stock-qty-number {
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
        }

        /* Search Bar */
        .search-container {
            background: #f1f5f9 !important;
            border-radius: 50px !important;
            padding: 4px 20px !important;
            box-shadow: none !important;
            border: 1px solid #e2e8f0;
            margin-bottom: 20px !important;
            display: flex;
            align-items: center;
        }
        .search-container input {
            background: transparent !important;
            font-size: 15px !important;
        }
        .search-container i { color: #64748b !important; font-size: 16px; margin-right: 12px; }

        /* Filter Tabs */
        .filter-tabs {
            display: flex;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 12px;
            gap: 4px;
            margin-bottom: 24px;
            border: none !important;
        }
        .filter-btn {
            flex: 1;
            padding: 8px 12px !important;
            border-radius: 10px !important;
            background: transparent !important;
            border: none !important;
            color: #64748b !important;
            font-weight: 600 !important;
            font-size: 13px !important;
            transition: all 0.2s;
            box-shadow: none !important;
        }
        .filter-btn.active {
            background: #2f5d3a !important;
            color: #ffffff !important;
            box-shadow: 0 2px 8px rgba(47, 93, 58, 0.3) !important;
        }

        /* Hide desktop table header on mobile */
        @media (max-width: 768px) {
            .table-header { display: none !important; }
            .stock-table { background: transparent !important; box-shadow: none !important; padding: 0 !important; }
        }
        
        /* Bottom Nav highlight */
        .bottom-nav { border-top: 1px solid #e2e8f0; box-shadow: 0 -4px 20px rgba(0,0,0,0.03); }
        .bottom-nav .nav-item.active { color: #2f5d3a !important; font-weight: 700; }
        .bottom-nav .nav-item { color: #94a3b8; }
    </style>
`;

c = c.replace(/<style>[\s\S]*?<\/style>/, newCSS);

const newRender = `
        function renderProducts(products) {
            const container = document.getElementById('productContainer');
            if (products.length === 0) {
                container.innerHTML = '<div style="padding: 50px; text-align: center; color: #64748b; font-weight: 500;">ไม่พบสินค้า</div>';
                return;
            }

            container.innerHTML = products.map(p => {
                let pillClass = 'pill-in-stock';
                let statusText = 'In Stock';
                const qty = parseInt(p.stock_qty) || 0;

                if (qty === 0) {
                    pillClass = 'pill-out-of-stock';
                    statusText = 'Out of Stock';
                } else if (qty < 5) {
                    pillClass = 'pill-low-stock';
                    statusText = 'Low Stock';
                }

                /* Mobile Read-only Stock Card Layout */
                return \`
                    <div class="product-row stock-card" onclick="void(0)">
                        <div class="stock-card-left">
                            <img src="\${p.image_url || 'assets/img/pet_product_placeholder.png'}" 
                                 onerror="this.src='https://placehold.co/100x100/f1f5f9/94a3b8?text=Image'" 
                                 class="stock-card-img">
                        </div>
                        <div class="stock-card-middle">
                            <div class="stock-card-name">\${p.product_name}</div>
                            <div class="stock-status-pill \${pillClass}">\${statusText}</div>
                        </div>
                        <div class="stock-card-right">
                            <div class="stock-qty-text">Qty</div>
                            <div class="stock-qty-number">\${qty}</div>
                        </div>
                    </div>
                \`;
            }).join('');
        }
`;

c = c.replace(/function renderProducts\(products\) {[\s\S]*?}\n\n\s*async function updateQty/, newRender.trim() + '\\n\\n        async function updateQty');

fs.writeFileSync('c:/laragon/www/Hello_Pet_Shop/admin_stock.html', c);
console.log('Mobile UI perfectly applied');
