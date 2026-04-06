const fs = require('fs');
const files = fs.readdirSync('.').filter(f => f.startsWith('admin_') && f.endsWith('.html'));

const newCode = `        function toggleSubmenu(el) {
            const group = el.closest('.menu-group');
            group.classList.toggle('open');
            localStorage.setItem('productsMenuOpen', group.classList.contains('open'));
        }

        // Restore submenu state
        document.addEventListener('DOMContentLoaded', () => {
            const menuGroup = document.querySelector('.menu-group');
            if (menuGroup) {
                const isOpen = localStorage.getItem('productsMenuOpen');
                if (isOpen === 'true') menuGroup.classList.add('open');
                else if (isOpen === 'false') menuGroup.classList.remove('open');
            }
        });`;

let modifiedCount = 0;
files.forEach(file => {
    let content = fs.readFileSync(file, 'utf8');
    const regex = /function toggleSubmenu\s*\([^)]*\)\s*\{\s*el\.closest\('\.menu-group'\)\.classList\.toggle\('open'\);\s*\}/g;
    
    if (regex.test(content)) {
        content = content.replace(regex, newCode);
        fs.writeFileSync(file, content);
        modifiedCount++;
        console.log('Modified ' + file);
    } else {
        console.log('Not found in ' + file);
    }
});
console.log('Done modifying ' + modifiedCount + ' files.');
