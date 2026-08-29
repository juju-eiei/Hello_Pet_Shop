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
 * Get current user profile data with per-user isolation and fallback defaults
 */
export function getUserProfileData() {
    const userObj = JSON.parse(localStorage.getItem('user') || '{}');
    const userKey = userObj.user_id ? `userProfileData_${userObj.user_id}` : (userObj.username ? `userProfileData_${userObj.username}` : 'userProfileData');
    
    let userStr = localStorage.getItem(userKey) || localStorage.getItem('userProfileData');
    
    let profile = {
        name: userObj.username || userObj.first_name || 'Sophia Clark',
        phone: userObj.phone || '0631234567',
        address: '123 Green Paw Street',
        province: 'เชียงใหม่',
        zipcode: '50200',
        email: userObj.email || 'user@example.com',
        profileImage: ''
    };

    if (userStr) {
        try {
            const saved = JSON.parse(userStr);
            if (saved.name) profile.name = saved.name;
            if (saved.phone) profile.phone = saved.phone;
            if (saved.address) profile.address = saved.address;
            if (saved.province) profile.province = saved.province;
            if (saved.zipcode) profile.zipcode = saved.zipcode;
            if (saved.email) profile.email = saved.email;
            if (saved.profileImage) profile.profileImage = saved.profileImage;
        } catch(e) {}
    }

    return profile;
}

/**
 * Save user profile data with per-user isolation
 */
export function saveUserProfileData(data) {
    const userObj = JSON.parse(localStorage.getItem('user') || '{}');
    const userKey = userObj.user_id ? `userProfileData_${userObj.user_id}` : (userObj.username ? `userProfileData_${userObj.username}` : 'userProfileData');
    
    localStorage.setItem(userKey, JSON.stringify(data));
    localStorage.setItem('userProfileData', JSON.stringify(data));
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

