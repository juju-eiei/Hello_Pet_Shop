import { showToast } from './utils.js';

document.addEventListener('DOMContentLoaded', () => {
    const editBtn = document.getElementById('editProfileBtn');
    const displayName = document.getElementById('displayName');
    
    const inputName = document.getElementById('inputName');
    const inputAddress = document.getElementById('inputAddress');
    const inputPhone = document.getElementById('inputPhone');
    const inputEmail = document.getElementById('inputEmail');

    const profileImage = document.getElementById('profileImage');
    const defaultAvatar = document.getElementById('defaultAvatar');
    const uploadOverlay = document.getElementById('uploadOverlay');
    const profileImageInput = document.getElementById('profileImageInput');

    const inputs = [inputName, inputAddress, inputPhone, inputEmail];

    let isEditing = false;
    let tempImageSrc = ""; // Current image src

    // Load from localStorage if available to persist between refreshes
    const userStr = localStorage.getItem('userProfileData');
    if (userStr) {
        try {
            const userData = JSON.parse(userStr);
            if (userData.name) {
                inputName.value = userData.name;
                displayName.textContent = userData.name;
            }
            if (userData.address) inputAddress.value = userData.address;
            if (userData.phone) inputPhone.value = userData.phone;
            if (userData.email) inputEmail.value = userData.email;
            
            if (userData.profileImage) {
                profileImage.src = userData.profileImage;
                tempImageSrc = userData.profileImage;
                profileImage.classList.remove('hidden');
                defaultAvatar.classList.add('hidden');
            }
        } catch (e) {
            console.error('Error parsing profile data', e);
        }
    }

    // Image Upload Preview
    profileImageInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            if (!file.type.startsWith('image/')) {
                showToast("Please upload an image file", "error");
                return;
            }
            const reader = new FileReader();
            reader.onload = function(event) {
                tempImageSrc = event.target.result;
                profileImage.src = tempImageSrc;
                profileImage.classList.remove('hidden');
                defaultAvatar.classList.add('hidden');
            };
            reader.readAsDataURL(file);
        }
    });

    editBtn.addEventListener('click', () => {
        isEditing = !isEditing;

        if (isEditing) {
            // Enable editing
            editBtn.innerHTML = '<span class="px-6 py-2 bg-[#8bb35c] text-white rounded-xl font-medium shadow-sm hover:bg-[#7a9e4f] active:scale-95 transition-all">บันทึก</span>';
            
            inputs.forEach(input => {
                input.removeAttribute('readonly');
                input.classList.remove('text-[#7b9ebb]', 'bg-white', 'border-[#d6e3ec]');
                input.classList.add('text-gray-800', 'bg-blue-50', 'border-blue-300', 'focus:ring-2', 'focus:ring-blue-100');
            });
            
            // Enable image upload
            uploadOverlay.classList.remove('hidden');
            profileImageInput.removeAttribute('disabled');
            
            // Focus the first input (Name)
            inputName.focus();
            
            showToast("You can now edit your profile & picture", "info");
        } else {
            // Save and disable editing
            editBtn.innerHTML = '<i class="fa-regular fa-pen-to-square text-[22px] text-[#1f2937] hover:text-gray-600"></i>';
            
            inputs.forEach(input => {
                input.setAttribute('readonly', 'readonly');
                input.classList.remove('text-gray-800', 'bg-blue-50', 'border-blue-300', 'focus:ring-2', 'focus:ring-blue-100');
                input.classList.add('text-[#7b9ebb]', 'bg-white', 'border-[#d6e3ec]');
            });

            // Disable image upload
            uploadOverlay.classList.add('hidden');
            profileImageInput.setAttribute('disabled', 'disabled');

            // Update display name at the top
            displayName.textContent = inputName.value;

            // Save to localStorage
            const userData = {
                name: inputName.value,
                address: inputAddress.value,
                phone: inputPhone.value,
                email: inputEmail.value,
                profileImage: tempImageSrc
            };
            localStorage.setItem('userProfileData', JSON.stringify(userData));

            showToast("Profile information updated successfully", "success");
        }
    });
});
