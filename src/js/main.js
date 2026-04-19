// Import main styles
import '../css/style.css';

export function updateNavProfile() {
    const navProfileImage = document.getElementById('navProfileImage');
    const navDefaultAvatar = document.getElementById('navDefaultAvatar');
    
    if (!navProfileImage || !navDefaultAvatar) return;
    
    const userStr = localStorage.getItem('userProfileData');
    if (userStr) {
        try {
            const userData = JSON.parse(userStr);
            if (userData.profileImage) {
                navProfileImage.src = userData.profileImage;
                navProfileImage.classList.remove('hidden');
                navDefaultAvatar.classList.add('hidden');
            } else {
                navProfileImage.classList.add('hidden');
                navDefaultAvatar.classList.remove('hidden');
            }
        } catch (e) {
            console.error('Error parsing profile data for nav', e);
        }
    }
}

// Common Initialization
document.addEventListener('DOMContentLoaded', () => {
    console.log('Hello Pet Shop - Premium UI Loaded');
    
    // Global User state check (if needed)
    const user = JSON.parse(localStorage.getItem('user'));
    if (user) {
        console.log(`Logged in as: ${user.username} (${user.role_name})`);
    }

    // Dropdown Logic
    const navMenuBtn = document.getElementById('navProfileMenuBtn');
    const navDropdown = document.getElementById('navProfileDropdown');
    const logoutBtn = document.getElementById('logoutBtn');
    
    if (navMenuBtn && navDropdown) {
        navMenuBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            navDropdown.classList.toggle('hidden');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!navMenuBtn.contains(e.target) && !navDropdown.contains(e.target)) {
                navDropdown.classList.add('hidden');
            }
        });
    }
    
    if (logoutBtn) {
        logoutBtn.addEventListener('click', () => {
            localStorage.removeItem('user');
            localStorage.removeItem('userProfileData');
            window.location.href = '/login';
        });
    }

    // Refresh navbar avatar
    updateNavProfile();

    // Refresh cart badge
    updateGlobalCartCount();
});

export function updateGlobalCartCount() {
    const cart = JSON.parse(localStorage.getItem('cart') || '[]');
    const count = cart.reduce((sum, item) => sum + item.quantity, 0);
    const badge = document.getElementById('cartCount');
    if (badge) {
        if (count > 0) {
            badge.textContent = count;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }
}
