const fs = require('fs');
let c = fs.readFileSync('c:/laragon/www/Hello_Pet_Shop/admin_order_details.html', 'utf8');

c = c.replace(/grid-template-columns:\s*1fr;/, 'grid-template-columns: minmax(0, 1fr);\n            }\n            .left-col, .right-col {\n                min-width: 0;');

fs.writeFileSync('c:/laragon/www/Hello_Pet_Shop/admin_order_details.html', c);
console.log('Fixed grid css stretching');
