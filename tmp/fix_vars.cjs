const fs = require('fs');

let c = fs.readFileSync('c:/laragon/www/Hello_Pet_Shop/admin_stock.html', 'utf8');

c = c.replace(/\\\$\{/g, '${');

fs.writeFileSync('c:/laragon/www/Hello_Pet_Shop/admin_stock.html', c);
console.log('Fixed double escaping on template literals');
