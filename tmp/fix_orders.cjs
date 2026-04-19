const fs = require('fs');

let content = fs.readFileSync('c:/laragon/www/Hello_Pet_Shop/admin_orders.html', 'utf8');

const regex = /<div class="product-row"[^>]*>[\s\S]*?<div style="flex: 1; display: flex; justify-content: flex-end;">/m;

const replacementStr = `<div class="order-row">
                    <div class="order-date">\${o.date}</div>
                    <div class="order-number">\${o.number}</div>
                    <div class="order-amount">$\${o.amount.toFixed(2)}</div>
                    <div class="order-status">
                        <span class="status-pill \${getStatusClass(o.status)}">\${o.status}</span>
                    </div>
                    <div class="order-actions desktop-action">`;

if (regex.test(content)) {
    content = content.replace(regex, replacementStr);
    fs.writeFileSync('c:/laragon/www/Hello_Pet_Shop/admin_orders.html', content);
    console.log('Successfully replaced order row markup');
} else {
    console.log('Regex did not match.');
}
