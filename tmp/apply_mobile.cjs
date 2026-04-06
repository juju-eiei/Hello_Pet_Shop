const fs = require('fs');

const files = fs.readdirSync('.').filter(f => f.startsWith('admin_') && f.endsWith('.html'));

const uaScript = `
    <!-- Mobile User-Agent Check -->
    <script>
        if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
            document.documentElement.classList.add('mobile-device');
        }
    </script>
</head>`;

let modifiedCount = 0;
files.forEach(file => {
    let content = fs.readFileSync(file, 'utf8');
    
    // 1. Inject UA script in head
    if (!content.includes('mobile-device') && content.includes('</head>')) {
        content = content.replace('</head>', uaScript);
    }

    // 2. Add 'desktop-only-menu' to Dashboard
    content = content.replace(/<a href="admin_dashboard.html" class="menu-item(?:\s+active)?">/g, (match) => {
        return match.replace('class="', 'class="desktop-only-menu ');
    });

    // 3. Add 'desktop-only-menu' to Product Management
    content = content.replace(/<a href="admin_product_management.html" class="(?:\s*active\s*)?">Product Management<\/a>/g, (match) => {
        return match.replace('class="', 'class="desktop-only-menu ');
    });

    // Note: Staff Management is KEPT because user said owner can view staff data.
    
    fs.writeFileSync(file, content);
    modifiedCount++;
    console.log('Modified ' + file);
});

console.log('Done modifying ' + modifiedCount + ' files.');
