const fs = require('fs');
let c = fs.readFileSync('c:/laragon/www/Hello_Pet_Shop/admin_stock.html', 'utf8');

const newCSS = `
        /* Mobile Layout Structure Fixes for Stock */
        html.mobile-device .product-row {
            display: flex !important;
            flex-direction: column !important;
            align-items: flex-start !important;
            padding: 16px !important;
            position: relative;
        }
        html.mobile-device .product-row > div:first-child {
            width: 100% !important;
            margin-right: 0 !important;
            margin-bottom: 8px !important;
        }
        html.mobile-device .stock-status {
            position: absolute;
            top: 20px;
            right: 16px;
            font-size: 12px !important;
        }
        html.mobile-device .stock-actions {
            width: 100% !important;
            justify-content: flex-end !important;
        }
        html.mobile-device .product-name {
            font-size: 15px;
            line-height: 1.4;
            max-width: 70%;
        }
    </style>
`;

c = c.replace('    </style>', newCSS);
fs.writeFileSync('c:/laragon/www/Hello_Pet_Shop/admin_stock.html', c);
console.log('Mobile layout injected');
