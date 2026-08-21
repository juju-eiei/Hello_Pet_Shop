import { showToast } from './utils.js';

export function initContactPage() {
    const contactForm = document.getElementById('contactForm');
    if (!contactForm) return;

    const successMessage = document.getElementById('successMessage');
    const resetFormBtn = document.getElementById('resetFormBtn');
    
    const contactName = document.getElementById('contactName');
    const contactEmail = document.getElementById('contactEmail');

    // Pre-fill user data if available
    function prefillUser() {
        const userStr = localStorage.getItem('userProfileData');
        if (userStr) {
            try {
                const userData = JSON.parse(userStr);
                if (userData.name && contactName) contactName.value = userData.name;
                const loginUser = JSON.parse(localStorage.getItem('user'));
                if (loginUser && loginUser.email && contactEmail) contactEmail.value = loginUser.email;
            } catch (e) {
                console.error("Error prefilling contact form", e);
            }
        }
    }

    contactForm.onsubmit = (e) => {
        e.preventDefault();
        
        // Form simulation
        const submitBtn = contactForm.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = `<i class="fas fa-circle-notch fa-spin mr-2"></i> กำลังส่ง...`;
        
        // Simulate API call
        setTimeout(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
            
            // Show success state
            if (successMessage) {
                successMessage.classList.remove('hidden', 'opacity-0');
                successMessage.classList.add('opacity-100');
            }
            
            showToast("ส่งข้อความสำเร็จแล้ว!", "success");
        }, 1500);
    };

    if (resetFormBtn) {
        resetFormBtn.onclick = () => {
            contactForm.reset();
            if (successMessage) {
                successMessage.classList.add('hidden', 'opacity-0');
                successMessage.classList.remove('opacity-100');
            }
            prefillUser();
        };
    }

    prefillUser();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initContactPage);
} else {
    initContactPage();
}
