import { showToast } from './utils.js';

document.addEventListener('DOMContentLoaded', () => {
    const contactForm = document.getElementById('contactForm');
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
                if (userData.name) contactName.value = userData.name;
                // email might be different in profile, use login user email if available
                const loginUser = JSON.parse(localStorage.getItem('user'));
                if (loginUser && loginUser.email) contactEmail.value = loginUser.email;
            } catch (e) {
                console.error("Error prefilling contact form", e);
            }
        }
    }

    contactForm.addEventListener('submit', (e) => {
        e.preventDefault();
        
        // Form simulation
        const submitBtn = contactForm.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = `<i class="fas fa-circle-notch fa-spin mr-2"></i> Sending...`;
        
        // Simulate API call
        setTimeout(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
            
            // Show success state
            successMessage.classList.remove('hidden', 'opacity-0');
            successMessage.classList.add('opacity-100');
            
            showToast("Message sent successfully!", "success");
        }, 1500);
    });

    resetFormBtn.addEventListener('click', () => {
        contactForm.reset();
        successMessage.classList.add('hidden', 'opacity-0');
        successMessage.classList.remove('opacity-100');
        prefillUser();
    });

    prefillUser();
});
