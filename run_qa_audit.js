import puppeteer from 'puppeteer';

(async () => {
    console.log("=== Hello Pet Shop - QA & Security Automation Audit ===");
    const browser = await puppeteer.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const page = await browser.newPage();
    const logs = [];
    const errors = [];
    const pageErrors = [];

    page.on('console', msg => {
        const text = msg.text();
        const type = msg.type();
        logs.push(`[${type.toUpperCase()}] ${text}`);
        if (type === 'error') {
            errors.push(`[Console Error] ${text}`);
        }
    });

    page.on('pageerror', err => {
        pageErrors.push(err.toString());
        errors.push(`[Page Error at ${page.url()}] ${err.toString()}`);
    });

    try {
        // Step 1: Open Home Page and verify redirect
        console.log("1. Navigating to root / ...");
        await page.goto('http://localhost:5173/');
        await new Promise(resolve => setTimeout(resolve, 1000));
        let currentUrl = page.url();
        console.log(`Current URL after root redirect: ${currentUrl}`);
        if (currentUrl.includes('login.html')) {
            console.log("SUCCESS: Correctly redirected to login.html by default.");
        } else {
            console.log("WARNING: Did not redirect to login.html");
        }

        // Step 2: Go to registration page
        console.log("2. Navigating to register.html ...");
        await page.goto('http://localhost:5173/register.html');
        await new Promise(resolve => setTimeout(resolve, 1000));

        // Test empty submit (validation check)
        console.log("Testing empty registration submission...");
        await page.click('button[type="submit"]');
        await new Promise(resolve => setTimeout(resolve, 500));
        console.log(`URL after empty submit: ${page.url()}`); // Should still be register.html

        // Test password mismatch
        console.log("Testing password mismatch registration...");
        await page.type('input[name="full_name"]', 'QA Tester');
        await page.type('input[name="username"]', `qa_user_${Date.now()}`);
        await page.type('input[name="email"]', `qa_${Date.now()}@example.com`);
        await page.type('input[name="password"]', 'password123');
        await page.type('#confirm_password', 'password456');
        await page.click('button[type="submit"]');
        await new Promise(resolve => setTimeout(resolve, 1000));
        
        // Check if toast message is visible and matches "รหัสผ่านไม่ตรงกัน"
        const toastText = await page.evaluate(() => {
            const toast = document.getElementById('toast');
            return toast ? toast.textContent : null;
        });
        console.log(`Toast message for password mismatch: ${toastText}`);

        // Complete a valid registration
        console.log("Filling in valid registration details...");
        const uniqueUsername = `qa_user_${Date.now()}`;
        const uniqueEmail = `qa_${Date.now()}@example.com`;
        
        // Reload page to clear
        await page.goto('http://localhost:5173/register.html');
        await page.waitForSelector('input[name="username"]');
        await page.type('input[name="full_name"]', 'Automated QA Tester');
        await page.type('input[name="username"]', uniqueUsername);
        await page.type('input[name="email"]', uniqueEmail);
        await page.type('input[name="phone"]', '0899999999');
        await page.type('input[name="password"]', 'password');
        await page.type('#confirm_password', 'password');
        await page.click('button[type="submit"]');
        
        await new Promise(resolve => setTimeout(resolve, 2000));
        console.log(`URL after valid register submit: ${page.url()}`);

        // Step 3: Login as the newly created customer user
        console.log("3. Logging in as new customer...");
        if (!page.url().includes('login.html')) {
            await page.goto('http://localhost:5173/login.html');
        }
        await page.waitForSelector('input[name="username"]');
        await page.type('input[name="username"]', uniqueUsername);
        await page.type('input[name="password"]', 'password');
        await page.click('button[type="submit"]');
        await new Promise(resolve => setTimeout(resolve, 2000));
        console.log(`URL after login: ${page.url()}`);

        // Step 4: Verify Landing and Search Workflow
        console.log("4. Testing products.html and search...");
        if (!page.url().includes('products.html')) {
            await page.goto('http://localhost:5173/products.html');
        }
        await page.waitForSelector('#productGrid');

        // Check if products exist
        const productsCountBefore = await page.evaluate(() => {
            return document.querySelectorAll('.product-card').length;
        });
        console.log(`Number of products initially rendered: ${productsCountBefore}`);

        // Search for 'Royal'
        console.log("Searching for 'Royal'...");
        await page.type('#productSearch', 'Royal');
        await new Promise(resolve => setTimeout(resolve, 1000)); // wait for search render
        
        const productsCountAfter = await page.evaluate(() => {
            return document.querySelectorAll('.product-card').length;
        });
        console.log(`Number of products after searching 'Royal': ${productsCountAfter}`);

        // Add to Cart
        console.log("Adding product to cart...");
        await page.click('.add-to-cart-btn'); // click the first product add-to-cart button
        await new Promise(resolve => setTimeout(resolve, 1000));

        // Get Cart Count
        const cartBadgeText = await page.evaluate(() => {
            const badge = document.getElementById('cartCount');
            return badge ? badge.textContent : '0';
        });
        console.log(`Cart Count Badge in navbar: ${cartBadgeText}`);

        // Step 5: Go to Cart and edit quantities
        console.log("5. Navigating to cart.html...");
        await page.goto('http://localhost:5173/cart.html');
        await new Promise(resolve => setTimeout(resolve, 1500));

        // Get initial item count in cart
        const cartItemsCount = await page.evaluate(() => {
            return document.querySelectorAll('.cart-item-row, [id^="cartItem"], .flex.items-center.justify-between.p-4').length; 
        });
        console.log(`Cart items visible: ${cartItemsCount}`);

        // Step 6: Proceed to Checkout
        console.log("6. Clicking Checkout button...");
        // Click the checkout link/button
        const checkoutBtnExists = await page.evaluate(() => {
            const btn = document.getElementById('checkoutBtn');
            if (btn) {
                btn.click();
                return true;
            }
            return false;
        });
        console.log(`Checkout button found and clicked: ${checkoutBtnExists}`);
        await new Promise(resolve => setTimeout(resolve, 2000));
        console.log(`Current URL: ${page.url()}`);

        // Step 7: Checkout Page Validation and Submission
        console.log("7. Testing checkout.html...");
        if (!page.url().includes('checkout.html')) {
            await page.goto('http://localhost:5173/checkout.html');
        }
        await page.waitForSelector('#fullName');

        // Test empty fields validation
        console.log("Clicking confirm order with empty fields...");
        await page.click('#confirmOrderBtn');
        await new Promise(resolve => setTimeout(resolve, 1000));

        // Fill shipping details
        console.log("Filling shipping details...");
        await page.type('#fullName', 'QA Automation Tester');
        await page.type('#phone', '0899999999');
        await page.type('#address', '456 QA Tester Way');
        await page.type('#province', 'Bangkok');
        await page.type('#zipcode', '10110');
        
        console.log("Clicking confirm order again...");
        await page.click('#confirmOrderBtn');
        await new Promise(resolve => setTimeout(resolve, 1500));

        // QR modal should be open. Check modal visibility and click Pay Later
        const qrModalState = await page.evaluate(() => {
            const modal = document.getElementById('paymentQrModal');
            return modal ? {
                classes: modal.className,
                opacity: window.getComputedStyle(modal).opacity,
                pointerEvents: window.getComputedStyle(modal).pointerEvents
            } : null;
        });
        console.log("QR Modal state:", qrModalState);

        console.log("Clicking Pay Later (จ่ายเงินภายหลัง)...");
        const clickedPayLater = await page.evaluate(() => {
            const btn = document.getElementById('payLaterBtn');
            if (btn) {
                btn.click();
                return true;
            }
            return false;
        });
        console.log(`Clicked Pay Later button: ${clickedPayLater}`);
        await new Promise(resolve => setTimeout(resolve, 2000));

        // Check if Success Modal is displayed
        const successModalVisible = await page.evaluate(() => {
            const modal = document.getElementById('successModal');
            return modal ? window.getComputedStyle(modal).opacity === '1' : false;
        });
        console.log(`Success Modal visible: ${successModalVisible}`);

        // Redirect to order history
        console.log("Redirecting to order-history.html...");
        await page.goto('http://localhost:5173/order-history.html');
        await new Promise(resolve => setTimeout(resolve, 1500));
        console.log(`Current URL: ${page.url()}`);

        // Step 8: My Pets page CRUD test
        console.log("8. Testing my-pets.html CRUD...");
        await page.goto('http://localhost:5173/my-pets.html');
        await page.waitForSelector('#addPetBtn');
        
        console.log("Clicking Add Pet button...");
        await page.click('#addPetBtn');
        await new Promise(resolve => setTimeout(resolve, 500));

        console.log("Filling pet details...");
        await page.type('#petName', 'Buddy');
        await page.select('#petSpecies', 'Dog');
        await page.type('#petBreed', 'Golden Retriever');
        await page.evaluate(() => {
            document.getElementById('petBirthDate').value = '2024-01-01';
        });
        await page.type('#petWeight', '15.5');
        await page.type('#petNotes', 'Healthy and active puppy.');
        
        console.log("Saving pet...");
        await page.click('#savePetBtn');
        await new Promise(resolve => setTimeout(resolve, 1000));

        // Check if Buddy is in grid
        const petsCount = await page.evaluate(() => {
            return document.querySelectorAll('#petsGrid > div').length;
        });
        console.log(`Number of pets in grid: ${petsCount}`);

        // Step 9: Log out and log in as admin
        console.log("9. Logging out customer...");
        await page.goto('http://localhost:5173/profile.html');
        await page.waitForSelector('#mainLogoutBtn');
        await page.click('#mainLogoutBtn');
        await new Promise(resolve => setTimeout(resolve, 1500));
        console.log(`URL after logout: ${page.url()}`);

        console.log("Logging in as Admin...");
        await page.goto('http://localhost:5173/login.html');
        await page.waitForSelector('input[name="username"]');
        await page.type('input[name="username"]', 'admin');
        await page.type('input[name="password"]', 'password');
        await page.click('button[type="submit"]');
        await new Promise(resolve => setTimeout(resolve, 2000));
        console.log(`URL after Admin login: ${page.url()}`);

        // Step 10: Admin stock and product management test
        console.log("10. Testing Admin panel...");
        if (!page.url().includes('admin_stock.html')) {
            await page.goto('http://localhost:5173/admin/stock');
        }
        await new Promise(resolve => setTimeout(resolve, 1500));
        console.log(`Admin Current URL: ${page.url()}`);

        // Click around Admin sidebar/navbar menu items to verify if they load without error
        console.log("Verifying sidebar link URLs...");
        const sidebarLinks = await page.evaluate(() => {
            return Array.from(document.querySelectorAll('aside.sidebar a, .sidebar a')).map(a => ({
                text: a.textContent.trim(),
                href: a.getAttribute('href')
            }));
        });
        console.log("Found sidebar links:", sidebarLinks);

        // Try adding a new product
        console.log("Navigating to admin_product_edit.html to add a product...");
        await page.goto('http://localhost:5173/admin_product_edit.html');
        await page.waitForSelector('#productForm');

        console.log("Filling new product details...");
        const newProdName = `QA Auto Toy ${Date.now()}`;
        await page.type('#pName', newProdName);
        await page.select('#pCategory', '3'); // Accessories
        await page.type('#pCostPrice', '45.00');
        await page.type('#pPrice', '99.00');
        await page.type('#pStock', '20');
        await page.type('#pWeightValue', '0.25');
        await page.type('#pImageUrl', 'https://images.unsplash.com/photo-1576201836106-db1758fd1c97?w=300');
        await page.type('#pBarcode', `BARCODE${Date.now()}`);
        await page.type('#pDesc', 'This is a product added by automated QA test script.');

        console.log("Submitting new product...");
        await page.click('button[type="submit"]');
        await new Promise(resolve => setTimeout(resolve, 2000));
        console.log(`URL after saving product: ${page.url()}`); // Should redirect back to admin_product_management.html

        // Verify if newly added product exists in management list
        const productExists = await page.evaluate((name) => {
            return document.body.textContent.includes(name);
        }, newProdName);
        console.log(`Does newly created product show in listing? ${productExists}`);

        // Check if customer order is visible in Admin Orders page (which it should NOT be, due to the localStorage bug!)
        console.log("Checking Admin Orders page for customer order...");
        await page.goto('http://localhost:5173/admin_orders.html');
        await new Promise(resolve => setTimeout(resolve, 2000));

        const orderBodyText = await page.evaluate(() => {
            return document.body.textContent;
        });
        console.log(`Does the admin page contain 'QA Automation Tester' or our checkout mock names? ${orderBodyText.includes('QA Automation Tester')}`);

    } catch (e) {
        console.error("QA automation run threw an exception:", e);
    } finally {
        await browser.close();
        console.log("=== QA & Security Audit Automation Completed ===");
        console.log("=== Page Console Logs ===");
        logs.forEach(l => console.log(` ${l}`));
        console.log(`Total console errors captured: ${errors.length}`);
        if (errors.length > 0) {
            console.log("Console Errors list:");
            errors.forEach(e => console.log(` - ${e}`));
        }
    }
})();
