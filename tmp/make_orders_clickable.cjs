const fs = require('fs');
let c = fs.readFileSync('c:/laragon/www/Hello_Pet_Shop/admin_orders.html', 'utf8');

const regex = /<div class="order-row">/g;
if (regex.test(c)) {
    c = c.replace(regex, '<div class="order-row" onclick="window.location.href=\'admin_order_details.html?id=${o.id}\'" style="cursor: pointer;">');
    fs.writeFileSync('c:/laragon/www/Hello_Pet_Shop/admin_orders.html', c);
    console.log("Updated order-row successfully");
} else {
    console.log("order-row not found");
}

let styles = fs.readFileSync('c:/laragon/www/Hello_Pet_Shop/public/css/admin_styles.css', 'utf8');
const pointerEventsRegex = /html\.mobile-device \.product-row \{\s*pointer-events:\s*none;\s*\}/m;
if (pointerEventsRegex.test(styles)) {
    // Make sure we only disable pointer on product rows with edit buttons, not order-row or customer rows
    console.log("Pointer events restriction on product-row is active");
}
