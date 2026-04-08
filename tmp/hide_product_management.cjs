const fs = require('fs');

const files = fs.readdirSync('.').filter(f => f.endsWith('.html'));
let count = 0;

for (const f of files) {
    let content = fs.readFileSync(f, 'utf8');
    const original = content;

    content = content.replace(
        /<a href="admin_product_management\.html">Product Management<\/a>/g, 
        '<a href="admin_product_management.html" class="desktop-only-menu">Product Management</a>'
    );
    
    content = content.replace(
        /<a href="admin_product_management\.html">จัดการสินค้า<\/a>/g, 
        '<a href="admin_product_management.html" class="desktop-only-menu">จัดการสินค้า</a>'
    );

    if (content !== original) {
        fs.writeFileSync(f, content, 'utf8');
        count++;
    }
}
console.log(`Updated ${count} files.`);
