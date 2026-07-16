import puppeteer from 'puppeteer';

(async () => {
    try {
        const browser = await puppeteer.launch({ headless: true });
        const page = await browser.newPage();
        
        page.on('console', msg => console.log('BROWSER CONSOLE:', msg.text()));
        
        console.log('1. Navigating to login...');
        await page.goto('http://localhost:5173/login.html');
        
        await page.type('input[name="username"]', 'admin');
        await page.type('input[name="password"]', 'password');
        await page.click('button[type="submit"]');
        
        await page.waitForNavigation();
        console.log('Logged in successfully!');

        // --- Verify Admin Stock Pagination ---
        console.log('\n2. Verifying Admin Stock Pagination...');
        await page.goto('http://localhost:5173/admin_stock.html');
        await page.waitForSelector('#productContainer .product-row');
        
        let initialStockProducts = await page.evaluate(() => {
            return Array.from(document.querySelectorAll('#productContainer .product-name')).map(el => el.textContent.trim());
        });
        console.log('Initial page stock products count:', initialStockProducts.length);
        console.log('First 3 stock products:', initialStockProducts.slice(0, 3));

        let paginationVisible = await page.evaluate(() => {
            const container = document.getElementById('paginationContainer');
            return container && container.children.length > 0;
        });
        console.log('Pagination container visible:', paginationVisible);

        if (paginationVisible) {
            // Click page 2
            await page.evaluate(() => {
                const buttons = document.querySelectorAll('#paginationContainer .pagination-btn');
                const btn2 = Array.from(buttons).find(btn => btn.textContent.trim() === '2');
                if (btn2) btn2.click();
            });

            await new Promise(resolve => setTimeout(resolve, 500));

            let page2StockProducts = await page.evaluate(() => {
                return Array.from(document.querySelectorAll('#productContainer .product-name')).map(el => el.textContent.trim());
            });
            console.log('Page 2 stock products count:', page2StockProducts.length);
            console.log('First 3 products on page 2:', page2StockProducts.slice(0, 3));
            console.log('Products changed on page 2:', JSON.stringify(initialStockProducts) !== JSON.stringify(page2StockProducts));
        }

        // --- Verify Staff Stock Pagination ---
        console.log('\n3. Verifying Staff Stock Pagination...');
        await page.goto('http://localhost:5173/staff_stock.html');
        await page.waitForSelector('#productContainer .product-row');

        let initialStaffStock = await page.evaluate(() => {
            return Array.from(document.querySelectorAll('#productContainer .product-name')).map(el => el.textContent.trim());
        });
        console.log('Initial page staff stock count:', initialStaffStock.length);

        paginationVisible = await page.evaluate(() => {
            const container = document.getElementById('paginationContainer');
            return container && container.children.length > 0;
        });
        console.log('Staff stock pagination container visible:', paginationVisible);

        if (paginationVisible) {
            await page.evaluate(() => {
                const buttons = document.querySelectorAll('#paginationContainer .pagination-btn');
                const btn2 = Array.from(buttons).find(btn => btn.textContent.trim() === '2');
                if (btn2) btn2.click();
            });

            await new Promise(resolve => setTimeout(resolve, 500));

            let page2StaffStock = await page.evaluate(() => {
                return Array.from(document.querySelectorAll('#productContainer .product-name')).map(el => el.textContent.trim());
            });
            console.log('Page 2 staff stock count:', page2StaffStock.length);
            console.log('Staff stock changed on page 2:', JSON.stringify(initialStaffStock) !== JSON.stringify(page2StaffStock));
        }

        console.log('\nAll tests complete!');
        await browser.close();
    } catch (err) {
        console.error('Error during stock pagination test:', err);
    }
})();
