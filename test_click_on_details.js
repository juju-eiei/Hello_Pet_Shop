import puppeteer from 'puppeteer';

(async () => {
    try {
        const browser = await puppeteer.launch({ headless: true });
        const page = await browser.newPage();
        
        page.on('console', msg => console.log('BROWSER CONSOLE:', msg.text()));
        
        console.log('Navigating to login...');
        await page.goto('http://localhost:5173/login.html');
        
        await page.type('input[name="username"]', 'admin');
        await page.type('input[name="password"]', 'password');
        await page.click('button[type="submit"]');
        
        await page.waitForNavigation();
        await page.goto('http://localhost:5173/admin_staff.html');
        await page.waitForSelector('.staff-tab');
        
        // Click Details tab
        await page.evaluate(() => {
            const tab = document.querySelector('.staff-tab[data-tab="salary"]');
            if (tab) tab.click();
        });
        
        // Wait for rendering
        await new Promise(resolve => setTimeout(resolve, 500));
        
        console.log('Clicking the calendar button on Details tab...');
        await page.click('.action-link[title="Attendance"]');
        
        // Wait 1 second
        await new Promise(resolve => setTimeout(resolve, 1000));
        
        const overlayState = await page.evaluate(() => {
            const overlay = document.getElementById('attendanceOverlay');
            return overlay ? {
                display: window.getComputedStyle(overlay).display,
                opacity: window.getComputedStyle(overlay).opacity,
                classes: overlay.className
            } : null;
        });
        console.log('Overlay state:', overlayState);

        await browser.close();
    } catch (err) {
        console.error('Error during verification:', err);
    }
})();
