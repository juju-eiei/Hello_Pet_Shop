/**
 * Show a professional toast notification
 * @param {string} message - The message to display
 * @param {'success' | 'error' | 'info'} type - The type of toast
 */
export function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    if (!toast) return;

    // Reset classes
    toast.className = 'fixed bottom-8 left-1/2 -translate-x-1/2 px-6 py-3 rounded-xl shadow-xl transition-all duration-500 z-50 font-semibold';
    
    // Set type styles
    if (type === 'success') {
        toast.classList.add('bg-green-600', 'text-white');
    } else if (type === 'error') {
        toast.classList.add('bg-red-600', 'text-white');
    } else {
        toast.classList.add('bg-white', 'text-gray-800', 'border', 'border-gray-200');
    }

    toast.textContent = message;
    
    // Animation: Fade In/Up
    toast.classList.remove('opacity-0', 'translate-y-4', 'pointer-events-none');
    toast.classList.add('opacity-100', 'translate-y-0');

    // Auto-hide after 3 seconds
    setTimeout(() => {
        toast.classList.add('opacity-0', 'translate-y-4', 'pointer-events-none');
        toast.classList.remove('opacity-100', 'translate-y-0');
    }, 3000);
}

/**
 * Get current user profile data with strict per-user isolation
 */
export function getUserProfileData() {
    const userObj = JSON.parse(localStorage.getItem('user') || '{}');
    if (!userObj.user_id && !userObj.username) {
        return {
            name: 'ผู้ใช้ทั่วไป',
            phone: '',
            address: '',
            province: '',
            zipcode: '',
            email: '',
            profileImage: ''
        };
    }

    const userId = userObj.user_id ? String(userObj.user_id) : String(userObj.username);
    const userKey = `userProfileData_${userId}`;

    const accountProfiles = JSON.parse(localStorage.getItem('savedAccountProfiles') || '{}');
    const accountSaved = accountProfiles[userId] || (userObj.username ? accountProfiles[userObj.username] : {}) || {};
    
    let userStr = localStorage.getItem(userKey);
    let saved = {};
    if (userStr) {
        try { saved = JSON.parse(userStr); } catch(e) {}
    }

    const merged = { ...accountSaved, ...saved };

    let profile = {
        name: merged.name || userObj.first_name || userObj.username || 'ผู้ใช้ทั่วไป',
        phone: merged.phone || userObj.phone || '',
        address: merged.address || userObj.address || '',
        province: merged.province || userObj.province || '',
        zipcode: merged.zipcode || userObj.zipcode || '',
        email: merged.email || userObj.email || '',
        profileImage: merged.profileImage || userObj.profile_image || userObj.profileImage || ''
    };

    return profile;
}

/**
 * Save user profile data with strict per-user isolation
 */
export function saveUserProfileData(data) {
    const userObj = JSON.parse(localStorage.getItem('user') || '{}');
    if (!userObj.user_id && !userObj.username) return;

    const userId = userObj.user_id ? String(userObj.user_id) : String(userObj.username);
    const userKey = `userProfileData_${userId}`;

    // Save strictly to user-specific key
    localStorage.setItem(userKey, JSON.stringify(data));

    // Save to persistent account profile store strictly for this account
    const accountProfiles = JSON.parse(localStorage.getItem('savedAccountProfiles') || '{}');
    accountProfiles[userId] = { ...(accountProfiles[userId] || {}), ...data };
    if (userObj.username) {
        accountProfiles[userObj.username] = { ...(accountProfiles[userObj.username] || {}), ...data };
    }
    localStorage.setItem('savedAccountProfiles', JSON.stringify(accountProfiles));

    // Update active user object in localStorage
    if (userObj) {
        if (data.name) {
            userObj.username = data.name;
            userObj.first_name = data.name;
        }
        if (data.phone) userObj.phone = data.phone;
        if (data.email) userObj.email = data.email;
        if (data.profileImage) {
            userObj.profile_image = data.profileImage;
            userObj.profileImage = data.profileImage;
        }
        if (data.address) userObj.address = data.address;
        localStorage.setItem('user', JSON.stringify(userObj));
    }
}

/**
 * Get current user cart data with strict per-user account persistence
 */
export function getCartData() {
    const userObj = JSON.parse(localStorage.getItem('user') || '{}');
    if (!userObj.user_id && !userObj.username) return [];

    const userId = userObj.user_id ? String(userObj.user_id) : String(userObj.username);
    const userCartKey = `cart_${userId}`;

    const savedCarts = JSON.parse(localStorage.getItem('savedUserCarts') || '{}');
    const userSaved = savedCarts[userId] || (userObj.username ? savedCarts[userObj.username] : null);

    if (userSaved && Array.isArray(userSaved)) {
        return userSaved;
    }

    let localCartStr = localStorage.getItem(userCartKey);
    if (localCartStr) {
        try { return JSON.parse(localCartStr); } catch(e) {}
    }

    return [];
}

/**
 * Save user cart data with strict per-user account persistence
 */
export function saveCartData(cart) {
    const userObj = JSON.parse(localStorage.getItem('user') || '{}');
    if (!userObj.user_id && !userObj.username) return;

    const userId = userObj.user_id ? String(userObj.user_id) : String(userObj.username);
    const userCartKey = `cart_${userId}`;

    const cartArray = Array.isArray(cart) ? cart : [];

    localStorage.setItem('cart', JSON.stringify(cartArray));
    localStorage.setItem(userCartKey, JSON.stringify(cartArray));

    const savedCarts = JSON.parse(localStorage.getItem('savedUserCarts') || '{}');
    savedCarts[userId] = cartArray;
    if (userObj.username) {
        savedCarts[userObj.username] = cartArray;
    }
    localStorage.setItem('savedUserCarts', JSON.stringify(savedCarts));
}

/**
 * Get current user orders with strict per-user account persistence
 */
export function getUserOrdersData() {
    const userObj = JSON.parse(localStorage.getItem('user') || '{}');
    if (!userObj.user_id && !userObj.username) return [];

    const userId = userObj.user_id ? String(userObj.user_id) : String(userObj.username);
    const userOrdersKey = `myOrders_${userId}`;

    const savedOrders = JSON.parse(localStorage.getItem('savedUserOrders') || '{}');
    const userSaved = savedOrders[userId] || (userObj.username ? savedOrders[userObj.username] : null);

    if (userSaved && Array.isArray(userSaved)) {
        return userSaved;
    }

    let localStr = localStorage.getItem(userOrdersKey);
    if (localStr) {
        try { return JSON.parse(localStr); } catch(e) {}
    }

    return [];
}

/**
 * Save user orders with strict per-user account persistence
 */
export function saveUserOrdersData(orders) {
    const userObj = JSON.parse(localStorage.getItem('user') || '{}');
    if (!userObj.user_id && !userObj.username) return;

    const userId = userObj.user_id ? String(userObj.user_id) : String(userObj.username);
    const userOrdersKey = `myOrders_${userId}`;

    const ordersArray = Array.isArray(orders) ? orders : [];

    localStorage.setItem('myOrders', JSON.stringify(ordersArray));
    localStorage.setItem(userOrdersKey, JSON.stringify(ordersArray));

    const savedOrders = JSON.parse(localStorage.getItem('savedUserOrders') || '{}');
    savedOrders[userId] = ordersArray;
    if (userObj.username) {
        savedOrders[userObj.username] = ordersArray;
    }
    localStorage.setItem('savedUserOrders', JSON.stringify(savedOrders));
}

/**
 * Escapes HTML characters to prevent XSS attacks
 * @param {string} str - The string to escape
 * @returns {string} The escaped string
 */
export function escapeHTML(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/**
 * Prompts a guest user to register or login when attempting member-restricted actions
 * @param {string} customMessage - Message to display
 */
export function showRegisterPrompt(customMessage = 'กรุณาสมัครสมาชิกเพื่อสั่งซื้อสินค้า') {
    if (window.Swal) {
        window.Swal.fire({
            title: 'กรุณาสมัครสมาชิก',
            text: customMessage,
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-user-plus mr-1.5"></i> สมัครสมาชิก',
            cancelButtonText: 'ยกเลิก',
            showDenyButton: true,
            denyButtonText: '<i class="fas fa-sign-in-alt mr-1.5"></i> เข้าสู่ระบบ',
            confirmButtonColor: '#16a34a',
            denyButtonColor: '#1b4332',
            cancelButtonColor: '#9ca3af',
            customClass: {
                popup: 'rounded-2xl shadow-2xl font-sans',
                confirmButton: 'rounded-xl font-bold px-4 py-2.5',
                denyButton: 'rounded-xl font-bold px-4 py-2.5',
                cancelButton: 'rounded-xl font-medium px-4 py-2.5'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'register.html';
            } else if (result.isDenied) {
                window.location.href = 'login.html';
            }
        });
    } else {
        alert(customMessage);
    }
}

