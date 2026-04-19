const fs = require('fs');

let c = fs.readFileSync('c:/laragon/www/Hello_Pet_Shop/admin_stock.html', 'utf8');

c = c.replace('.stock-card {', '.stock-card {\\n            pointer-events: auto !important;');

fs.writeFileSync('c:/laragon/www/Hello_Pet_Shop/admin_stock.html', c);
console.log('Successfully enabled pointer-events');
