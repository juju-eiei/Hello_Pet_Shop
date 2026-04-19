import { showToast } from './utils.js';

document.addEventListener('DOMContentLoaded', () => {
    // DOM Elements
    const petsGrid = document.getElementById('petsGrid');
    const emptyState = document.getElementById('emptyState');
    const addPetBtn = document.getElementById('addPetBtn');
    
    // Modal Elements
    const petFormModal = document.getElementById('petFormModal');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const cancelPetBtn = document.getElementById('cancelPetBtn');
    const savePetBtn = document.getElementById('savePetBtn');
    const modalTitle = document.getElementById('modalTitle');
    const petForm = document.getElementById('petForm');
    
    // Form Inputs
    const petIdInput = document.getElementById('petId');
    const petNameInput = document.getElementById('petName');
    const petSpeciesInput = document.getElementById('petSpecies');
    const petBreedInput = document.getElementById('petBreed');
    const petBirthDateInput = document.getElementById('petBirthDate');
    const petWeightInput = document.getElementById('petWeight');
    const petImageInput = document.getElementById('petImageInput');
    const petImagePreview = document.getElementById('petImagePreview');
    const petImagePlaceholder = document.getElementById('petImagePlaceholder');

    // Delete Modal Elements
    const deleteModal = document.getElementById('deleteModal');
    const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const deletePetNameSpan = document.getElementById('deletePetName');

    // State
    let pets = [];
    let currentTempImageSrc = "";
    let petToDeleteId = null;

    // Initialize
    loadPets();

    // =============== Core Functions ===============

    function loadPets() {
        const petsStr = localStorage.getItem('myPetsData');
        if (petsStr) {
            try {
                pets = JSON.parse(petsStr);
            } catch (e) {
                console.error("Error parsing pets data", e);
                pets = [];
            }
        } else {
            pets = [];
        }
        renderPets();
    }

    function savePetsToLocal() {
        localStorage.setItem('myPetsData', JSON.stringify(pets));
    }

    function renderPets() {
        petsGrid.innerHTML = '';
        
        if (pets.length === 0) {
            petsGrid.classList.add('hidden');
            emptyState.classList.remove('hidden');
            return;
        }

        petsGrid.classList.remove('hidden');
        emptyState.classList.add('hidden');

        pets.forEach(pet => {
            const ageDisplay = calculateAge(pet.birthDate);
            const imageHtml = pet.image 
                ? `<img src="${pet.image}" alt="${pet.name}" class="w-full h-full object-cover">`
                : `<i class="fas fa-paw text-gray-300 text-4xl"></i>`;

            const card = document.createElement('div');
            card.className = "bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow relative group";
            card.innerHTML = `
                <!-- Action Buttons (Hidden by default, shown on hover/focus) -->
                <div class="absolute top-4 right-4 flex space-x-2 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button class="edit-pet-btn w-8 h-8 rounded-full bg-blue-50 text-blue-500 hover:bg-blue-100 flex items-center justify-center transition-colors" data-id="${pet.id}" title="Edit Pet">
                        <i class="fas fa-pen text-xs"></i>
                    </button>
                    <button class="delete-pet-btn w-8 h-8 rounded-full bg-red-50 text-red-500 hover:bg-red-100 flex items-center justify-center transition-colors" data-id="${pet.id}" title="Delete Pet">
                        <i class="fas fa-trash text-xs"></i>
                    </button>
                </div>

                <div class="flex items-center space-x-5 mb-5">
                    <div class="w-20 h-20 bg-gray-50 border-2 border-gray-100 rounded-full flex items-center justify-center overflow-hidden shrink-0">
                        ${imageHtml}
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-800 leading-tight">${pet.name}</h3>
                        <p class="text-sm font-medium text-[#8bb35c] mt-0.5">${pet.species} ${pet.breed ? `<span class="text-gray-400 font-normal ml-1">· ${pet.breed}</span>` : ''}</p>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-y-3 gap-x-4 bg-gray-50/50 rounded-xl p-4 border border-gray-50">
                    <div>
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Age</div>
                        <div class="text-sm text-gray-700 font-medium">${ageDisplay || '-'}</div>
                    </div>
                    <div>
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Weight</div>
                        <div class="text-sm text-gray-700 font-medium">${pet.weight ? pet.weight + ' kg' : '-'}</div>
                    </div>
                </div>
            `;
            petsGrid.appendChild(card);
        });

        // Add event listeners tracking
        document.querySelectorAll('.edit-pet-btn').forEach(btn => {
            btn.addEventListener('click', (e) => openModal(e.currentTarget.dataset.id));
        });
        document.querySelectorAll('.delete-pet-btn').forEach(btn => {
            btn.addEventListener('click', (e) => openDeleteModal(e.currentTarget.dataset.id));
        });
    }

    function calculateAge(birthDateStr) {
        if (!birthDateStr) return '';
        const birthDate = new Date(birthDateStr);
        const today = new Date();
        let years = today.getFullYear() - birthDate.getFullYear();
        let months = today.getMonth() - birthDate.getMonth();
        
        if (months < 0 || (months === 0 && today.getDate() < birthDate.getDate())) {
            years--;
            months += 12;
        }

        if (years > 0) {
            return `${years} yr ${months > 0 ? months + ' mo' : ''}`;
        } else if (months > 0) {
            return `${months} mo`;
        } else {
            return `< 1 mo`;
        }
    }

    // =============== Modal Operations ===============

    function openModal(petId = null) {
        // Reset form
        petForm.reset();
        currentTempImageSrc = "";
        petImagePreview.src = "";
        petImagePreview.classList.add('hidden');
        petImagePlaceholder.classList.remove('hidden');

        if (petId) {
            // Edit Mode
            const pet = pets.find(p => p.id === petId);
            if (pet) {
                modalTitle.textContent = "Edit Pet";
                petIdInput.value = pet.id;
                petNameInput.value = pet.name;
                petSpeciesInput.value = pet.species;
                if (pet.breed) petBreedInput.value = pet.breed;
                if (pet.birthDate) petBirthDateInput.value = pet.birthDate;
                if (pet.weight) petWeightInput.value = pet.weight;
                
                if (pet.image) {
                    currentTempImageSrc = pet.image;
                    petImagePreview.src = pet.image;
                    petImagePreview.classList.remove('hidden');
                    petImagePlaceholder.classList.add('hidden');
                }
            }
        } else {
            // Add Mode
            modalTitle.textContent = "Add New Pet";
            petIdInput.value = "";
        }

        petFormModal.classList.remove('opacity-0', 'pointer-events-none');
        const innerDiv = petFormModal.querySelector('div');
        innerDiv.classList.remove('scale-95');
        innerDiv.classList.add('scale-100');
    }

    function closeModal() {
        petFormModal.classList.add('opacity-0', 'pointer-events-none');
        const innerDiv = petFormModal.querySelector('div');
        innerDiv.classList.remove('scale-100');
        innerDiv.classList.add('scale-95');
    }

    function openDeleteModal(petId) {
        petToDeleteId = petId;
        const pet = pets.find(p => p.id === petId);
        if (pet) {
            deletePetNameSpan.textContent = pet.name;
            deleteModal.classList.remove('opacity-0', 'pointer-events-none');
            const innerDiv = deleteModal.querySelector('div');
            innerDiv.classList.remove('scale-95');
            innerDiv.classList.add('scale-100');
        }
    }

    function closeDeleteModal() {
        petToDeleteId = null;
        deleteModal.classList.add('opacity-0', 'pointer-events-none');
        const innerDiv = deleteModal.querySelector('div');
        innerDiv.classList.remove('scale-100');
        innerDiv.classList.add('scale-95');
    }

    // =============== Event Listeners ===============

    addPetBtn.addEventListener('click', () => openModal());
    
    closeModalBtn.addEventListener('click', closeModal);
    cancelPetBtn.addEventListener('click', closeModal);

    // Image Upload Preview in Modal
    petImageInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            if (!file.type.startsWith('image/')) {
                showToast("Please upload an image file", "error");
                return;
            }
            const reader = new FileReader();
            reader.onload = function(event) {
                currentTempImageSrc = event.target.result;
                petImagePreview.src = currentTempImageSrc;
                petImagePreview.classList.remove('hidden');
                petImagePlaceholder.classList.add('hidden');
            };
            reader.readAsDataURL(file);
        }
    });

    // Save Pet Details
    savePetBtn.addEventListener('click', () => {
        // Validation
        if (!petNameInput.value.trim()) {
            showToast("Pet Name is required", "error");
            petNameInput.focus();
            return;
        }
        if (!petSpeciesInput.value) {
            showToast("Species is required", "error");
            petSpeciesInput.focus();
            return;
        }

        const petData = {
            id: petIdInput.value || Date.now().toString(), // Generate simple ID
            name: petNameInput.value.trim(),
            species: petSpeciesInput.value,
            breed: petBreedInput.value.trim(),
            birthDate: petBirthDateInput.value,
            weight: petWeightInput.value,
            image: currentTempImageSrc,
            createdAt: petIdInput.value ? pets.find(p => p.id === petIdInput.value).createdAt : new Date().toISOString()
        };

        if (petIdInput.value) {
            // Update
            const index = pets.findIndex(p => p.id === petIdInput.value);
            if (index !== -1) {
                pets[index] = petData;
                showToast(`Updated ${petData.name}'s profile`, "success");
            }
        } else {
            // Add new
            pets.unshift(petData);
            showToast(`Added ${petData.name} successfully`, "success");
        }

        savePetsToLocal();
        renderPets();
        closeModal();
    });

    // Delete Operations
    cancelDeleteBtn.addEventListener('click', closeDeleteModal);
    
    confirmDeleteBtn.addEventListener('click', () => {
        if (petToDeleteId) {
            pets = pets.filter(p => p.id !== petToDeleteId);
            savePetsToLocal();
            renderPets();
            showToast("Pet removed from your profile", "success");
            closeDeleteModal();
        }
    });

});
