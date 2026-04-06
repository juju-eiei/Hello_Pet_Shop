const fs = require('fs');

let c = fs.readFileSync('c:/laragon/www/Hello_Pet_Shop/admin_stock.html', 'utf8');

const replacement = `return \`
                    <div class="product-row stock-card" onclick="window.location.href='admin_product_edit.html?id=\\$\\{p.product_id\\}'; return false;" style="cursor: pointer;">
                        <div class="stock-card-left">
                            <img src="\\$\\{p.image_url || 'assets/img/pet_product_placeholder.png'\\}" 
                                 onerror="this.src='https://placehold.co/100x100/f1f5f9/94a3b8?text=Image'" 
                                 class="stock-card-img">
                        </div>
                        <div class="stock-card-middle">
                            <div class="stock-card-name">\\$\\{p.product_name\\}</div>
                            <div class="stock-status-pill \\$\\{statusClass\\}">\\$\\{statusText\\}</div>
                        </div>
                        <div class="stock-card-right">
                            <div class="stock-qty-text">Qty</div>
                            <div class="stock-qty-number">\\$\\{qty\\}</div>
                        </div>
                    </div>
                \`;`;

// Find everything from `return \`` up to `        }).join('');`
c = c.replace(/return `[\s\S]*?`\s*;/m, replacement);

fs.writeFileSync('c:/laragon/www/Hello_Pet_Shop/admin_stock.html', c);
console.log('Successfully injected exact HTML string replacing old row structure');
