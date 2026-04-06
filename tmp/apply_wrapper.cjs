const fs = require('fs');
let c = fs.readFileSync('c:/laragon/www/Hello_Pet_Shop/admin_order_details.html', 'utf8');

c = c.replace('<div style="overflow-x: auto;">', '<div class="table-responsive-wrapper">');

fs.writeFileSync('c:/laragon/www/Hello_Pet_Shop/admin_order_details.html', c);
console.log('Fixed wrapper successfully');
