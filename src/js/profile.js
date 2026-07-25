import { showToast, getUserProfileData, saveUserProfileData } from './utils.js';

document.addEventListener('DOMContentLoaded', () => {
    // Elements
    const displayName = document.getElementById('displayName');
    const profileImage = document.getElementById('profileImage');
    const defaultAvatar = document.getElementById('defaultAvatar');
    const modalProfileImage = document.getElementById('modalProfileImage');
    const modalDefaultAvatar = document.getElementById('modalDefaultAvatar');
    const profileImageInput = document.getElementById('profileImageInput');

    // Inputs
    const inputName = document.getElementById('inputName');
    const inputAddress = document.getElementById('inputAddress');
    const inputProvince = document.getElementById('inputProvince');
    const inputZipcode = document.getElementById('zipcode');
    const inputPhone = document.getElementById('phone');
    const inputEmail = document.getElementById('inputEmail');

    // Badges
    const badgePendingPayment = document.getElementById('badgePendingPayment');
    const badgePreparing = document.getElementById('badgePreparing');
    const badgeShipping = document.getElementById('badgeShipping');

    // Modal Controls
    const editProfileModal = document.getElementById('editProfileModal');
    const openEditModalBtn = document.getElementById('openEditModalBtn');
    const openEditModalMenuBtn = document.getElementById('openEditModalMenuBtn');
    const closeEditModalBtn = document.getElementById('closeEditModalBtn');
    const cancelEditBtn = document.getElementById('cancelEditBtn');
    const saveProfileModalBtn = document.getElementById('saveProfileModalBtn');
    const mainLogoutBtn = document.getElementById('mainLogoutBtn');

    let tempImageSrc = "";

    // Seed Demo Orders if none exist in localStorage
    function seedDemoOrdersIfEmpty() {
        const stored = localStorage.getItem('myOrders');
        if (!stored || stored === '[]') {
            const demoOrders = [
                {
                    id: 849201,
                    date: new Date(Date.now() - 3600000 * 2).toISOString(),
                    items: [{ name: "แปรงหวีขนสัตว์เลี้ยง สแตนเลส", price: 320, quantity: 1, image: "/image/713815-00-allonline-hg.jpg" }],
                    subtotal: 320,
                    shipping: 2,
                    total: 322,
                    deliveryMethod: "standard",
                    paymentMethod: "transfer",
                    status: "Pending Payment"
                },
                {
                    id: 739102,
                    date: new Date(Date.now() - 3600000 * 24).toISOString(),
                    items: [{ name: "อาหารแมววิสการ์ส 1.2kg", price: 185, quantity: 2, image: "/image/713815-00-allonline-hg.jpg" }],
                    subtotal: 370,
                    shipping: 2,
                    total: 372,
                    deliveryMethod: "standard",
                    paymentMethod: "transfer",
                    status: "Preparing"
                },
                {
                    id: 619283,
                    date: new Date(Date.now() - 3600000 * 48).toISOString(),
                    items: [{ name: "แชมพูสูตรกำจัดเห็บเหา 500ml", price: 250, quantity: 1, image: "/image/713815-00-allonline-hg.jpg" }],
                    subtotal: 250,
                    shipping: 5,
                    total: 255,
                    deliveryMethod: "express",
                    paymentMethod: "transfer",
                    status: "Shipping",
                    trackingNumber: "TH982341092"
                },
                {
                    id: 510928,
                    date: new Date(Date.now() - 3600000 * 96).toISOString(),
                    items: [{ name: "ของเล่นคอนโดแมว 3 ชั้น", price: 890, quantity: 1, image: "/image/713815-00-allonline-hg.jpg" }],
                    subtotal: 890,
                    shipping: 5,
                    total: 895,
                    deliveryMethod: "express",
                    paymentMethod: "transfer",
                    status: "Completed"
                }
            ];
            localStorage.setItem('myOrders', JSON.stringify(demoOrders));
        }
    }

    // 1. Initial Load & Pre-fill
    function loadProfile() {
        seedDemoOrdersIfEmpty();

        const profile = getUserProfileData();
        
        if (profile.name) {
            inputName.value = profile.name;
            if (displayName) displayName.textContent = profile.name;
        }
        if (profile.address) inputAddress.value = profile.address;
        if (profile.province) inputProvince.value = profile.province;
        if (profile.zipcode) inputZipcode.value = profile.zipcode;
        if (profile.phone) inputPhone.value = profile.phone;
        if (profile.email) inputEmail.value = profile.email;

        if (profile.profileImage) {
            tempImageSrc = profile.profileImage;
            updateAvatarDisplays(tempImageSrc);
        }

        updateOrderBadges();
    }

    function updateOrderBadges() {
        const storedOrders = localStorage.getItem('myOrders');
        let orders = [];
        if (storedOrders) {
            try {
                orders = JSON.parse(storedOrders);
            } catch (e) {
                console.error("Error parsing myOrders in profile", e);
            }
        }

        let pendingCount = 0;
        let preparingCount = 0;
        let shippingCount = 0;
        let completedCount = 0;

        orders.forEach(o => {
            const st = o.status;
            if (st === 'Pending Payment' || st === 'ที่ต้องชำระ') {
                pendingCount++;
            } else if (st === 'Preparing' || st === 'กำลังเตรียมสินค้า' || st === 'ที่ต้องจัดส่ง') {
                preparingCount++;
            } else if (st === 'Shipping' || st === 'กำลังจัดส่ง' || st === 'ที่ต้องได้รับ') {
                shippingCount++;
            } else if (st === 'Completed' || st === 'สำเร็จ' || st === 'สำเร็จแล้ว') {
                completedCount++;
            }
        });

        setBadge(badgePendingPayment, pendingCount, 'bg-amber-500');
        setBadge(badgePreparing, preparingCount, 'bg-blue-500');
        setBadge(badgeShipping, shippingCount, 'bg-purple-500');
    }

    function setBadge(element, count, activeBgClass) {
        if (!element) return;
        element.textContent = count > 99 ? '99+' : count;
        
        // Remove old classes
        element.classList.remove('bg-gray-300', 'text-gray-600', 'bg-amber-500', 'bg-blue-500', 'bg-purple-500', 'bg-emerald-500', 'scale-110', 'animate-pulse');
        
        if (count > 0) {
            element.classList.add(activeBgClass, 'text-white', 'scale-100');
        } else {
            element.classList.add('bg-gray-300', 'text-white', 'opacity-80');
        }
    }

    function updateAvatarDisplays(src) {
        if (src) {
            if (profileImage) {
                profileImage.src = src;
                profileImage.classList.remove('hidden');
            }
            if (defaultAvatar) defaultAvatar.classList.add('hidden');

            if (modalProfileImage) {
                modalProfileImage.src = src;
                modalProfileImage.classList.remove('hidden');
            }
            if (modalDefaultAvatar) modalDefaultAvatar.classList.add('hidden');
        } else {
            if (profileImage) profileImage.classList.add('hidden');
            if (defaultAvatar) defaultAvatar.classList.remove('hidden');

            if (modalProfileImage) modalProfileImage.classList.add('hidden');
            if (modalDefaultAvatar) modalDefaultAvatar.classList.remove('hidden');
        }
    }

    // 2. Modal Open/Close Controls
    function openModal() {
        loadProfile(); // ensure inputs match latest saved
        if (editProfileModal) {
            editProfileModal.classList.remove('opacity-0', 'pointer-events-none');
            const card = editProfileModal.querySelector('div');
            if (card) {
                card.classList.remove('scale-95');
                card.classList.add('scale-100');
            }
        }
    }

    function closeModal() {
        if (editProfileModal) {
            const card = editProfileModal.querySelector('div');
            if (card) {
                card.classList.remove('scale-100');
                card.classList.add('scale-95');
            }
            editProfileModal.classList.add('opacity-0', 'pointer-events-none');
        }
    }

    if (openEditModalBtn) openEditModalBtn.addEventListener('click', openModal);
    if (openEditModalMenuBtn) openEditModalMenuBtn.addEventListener('click', openModal);
    if (closeEditModalBtn) closeEditModalBtn.addEventListener('click', closeModal);
    if (cancelEditBtn) cancelEditBtn.addEventListener('click', closeModal);

    if (editProfileModal) {
        editProfileModal.addEventListener('click', (e) => {
            if (e.target === editProfileModal) closeModal();
        });
    }

    // 3. Image Upload Handler
    if (profileImageInput) {
        profileImageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                if (!file.type.startsWith('image/')) {
                    showToast("กรุณาอัปโหลดไฟล์รูปภาพเท่านั้น", "error");
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(event) {
                    tempImageSrc = event.target.result;
                    updateAvatarDisplays(tempImageSrc);
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // 4. Save Profile Action
    if (saveProfileModalBtn) {
        saveProfileModalBtn.addEventListener('click', () => {
            const nameVal = inputName.value.trim();
            const phoneVal = inputPhone.value.trim();
            const addressVal = inputAddress.value.trim();

            if (!nameVal) {
                showToast("กรุณากรอกชื่อ-นามสกุล", "error");
                inputName.focus();
                return;
            }
            if (!phoneVal) {
                showToast("กรุณากรอกเบอร์โทรศัพท์", "error");
                inputPhone.focus();
                return;
            }

            // Save to localStorage
            const userData = {
                name: nameVal,
                address: addressVal,
                province: inputProvince.value.trim(),
                zipcode: inputZipcode.value.trim(),
                phone: phoneVal,
                email: inputEmail.value.trim(),
                profileImage: tempImageSrc
            };

            saveUserProfileData(userData);

            // Also update global user object if needed
            const userObj = JSON.parse(localStorage.getItem('user') || '{}');
            if (userObj.username) {
                userObj.username = nameVal;
                userObj.phone = phoneVal;
                userObj.email = inputEmail.value.trim();
                localStorage.setItem('user', JSON.stringify(userObj));
            }

            // Update Page Header UI
            if (displayName) displayName.textContent = nameVal;
            updateAvatarDisplays(tempImageSrc);

            showToast("บันทึกข้อมูลส่วนตัวสำเร็จ!", "success");
            closeModal();
        });
    }

    // 5. Main Logout Button
    if (mainLogoutBtn) {
        mainLogoutBtn.addEventListener('click', () => {
            localStorage.removeItem('user');
            localStorage.removeItem('cart');
            window.location.href = 'login.html';
        });
    }

    // Initialize
    loadProfile();

    window.addEventListener('storage', updateOrderBadges);
});
