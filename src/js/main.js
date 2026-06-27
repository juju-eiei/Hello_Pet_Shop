// Import main styles & core services
import '../css/style.css';
import './i18n.js';
import { initLayout } from './components/layout.js';
import { initRouter } from './router.js';

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

// Common Initialization
document.addEventListener('DOMContentLoaded', () => {
    console.log('Hello Pet Shop - Centralized Layout & SPA Router Initialized');
    
    // Initialize Centralized Layout & Router
    initLayout();
    initRouter();

    // Global User state check
    const user = JSON.parse(localStorage.getItem('user'));
    if (user) {
        console.log(`Logged in as: ${user.username} (${user.role_name})`);
    }

    // Refresh navbar avatar & cart
    updateNavProfile();
    updateGlobalCartCount();
});
