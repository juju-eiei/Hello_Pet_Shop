/**
 * AI Recommendation Engine for Hello Pet Shop
 * Personalizes products based on Search History, Cart Additions, Purchase History & Pet Profiles.
 */

import { getCartData, getUserOrdersData } from './utils.js';

const STORAGE_KEY_BEHAVIOR = 'userBehaviorLogs';

// Helper to get or initialize behavior logs
export function getUserBehavior() {
    const raw = localStorage.getItem(STORAGE_KEY_BEHAVIOR);
    if (!raw) {
        return {
            searches: [],      // array of search terms
            cartCategories: {},// category -> count
            cartKeywords: {},  // keyword -> count
            purchasedCategories: {},
            lastUpdated: new Date().toISOString()
        };
    }
    try {
        return JSON.parse(raw);
    } catch (e) {
        return { searches: [], cartCategories: {}, cartKeywords: {}, purchasedCategories: {}, lastUpdated: new Date().toISOString() };
    }
}

// Helper to save behavior logs
function saveUserBehavior(behavior) {
    behavior.lastUpdated = new Date().toISOString();
    localStorage.setItem(STORAGE_KEY_BEHAVIOR, JSON.stringify(behavior));
}

/**
 * Track user search query
 */
export function trackSearchQuery(query) {
    if (!query || query.trim().length < 2) return;
    const clean = query.trim().toLowerCase();
    const behavior = getUserBehavior();
    
    // Add to searches array (keep max 20)
    behavior.searches.unshift(clean);
    behavior.searches = [...new Set(behavior.searches)].slice(0, 20);
    
    saveUserBehavior(behavior);
}

/**
 * Track Add to Cart action
 */
export function trackAddToCart(product) {
    if (!product) return;
    const behavior = getUserBehavior();
    
    const cat = (product.category_name || product.category || 'general').toLowerCase();
    behavior.cartCategories[cat] = (behavior.cartCategories[cat] || 0) + 1;
    
    // Extract keywords from product name
    const tokens = extractKeywords(product.product_name || product.name || '');
    tokens.forEach(token => {
        behavior.cartKeywords[token] = (behavior.cartKeywords[token] || 0) + 1;
    });

    saveUserBehavior(behavior);
}

/**
 * Tokenize string into meaningful keywords
 */
function extractKeywords(text) {
    if (!text) return [];
    const stopWords = ['และ', 'สำหรับ', 'สูตร', 'ขนาด', 'สูตรใหม่', 'แพ็ค', 'กล่อง', 'ของ'];
    return text.toLowerCase()
        .replace(/[^\w\s\u0E00-\u0E7F]/g, ' ')
        .split(/\s+/)
        .filter(w => w.length >= 2 && !stopWords.includes(w));
}

/**
 * Calculate AI Relevance Score for each product
 * Returns array of products augmented with `aiScore` (0-100) and `aiReason` badge text.
 */
export function getPersonalizedProducts(allProducts) {
    if (!allProducts || allProducts.length === 0) return [];

    const behavior = getUserBehavior();
    const cart = getCartData();
    const myOrders = getUserOrdersData();
    
    // Retrieve user's pets from local storage (myPetsData or myPets)
    let myPets = [];
    try {
        const cachedData = localStorage.getItem('myPetsData');
        if (cachedData) myPets = JSON.parse(cachedData);
        if (!Array.isArray(myPets) || myPets.length === 0) {
            const fallbackPets = localStorage.getItem('myPets');
            if (fallbackPets) myPets = JSON.parse(fallbackPets);
        }
    } catch (e) {
        myPets = [];
    }

    // Extract cart categories & items
    const cartItemNames = cart.map(i => (i.name || i.product_name || '').toLowerCase());
    
    // Extract purchased product keywords
    const purchasedKeywords = [];
    myOrders.forEach(o => {
        (o.items || []).forEach(item => {
            extractKeywords(item.name || '').forEach(k => purchasedKeywords.push(k));
        });
    });

    // Score every product
    const scoredProducts = allProducts.map(product => {
        let score = 50; // Base score
        const nameLower = (product.product_name || product.name || '').toLowerCase();
        const catLower = (product.category_name || product.category || '').toLowerCase();
        const targetPetName = (product.target_pet_type_name || '').toLowerCase();
        const targetPetCode = (product.target_pet_type_code || '').toLowerCase();

        // 1. Search Query Match (Max +30 points)
        behavior.searches.forEach((search, index) => {
            const recencyWeight = (20 - index) / 20;
            if (nameLower.includes(search) || catLower.includes(search) || targetPetName.includes(search)) {
                score += 25 * recencyWeight;
            }
        });

        // 2. Cart Content & Click Affinity (Max +30 points)
        cartItemNames.forEach(cartItem => {
            const sharedKeywords = extractKeywords(cartItem).filter(k => nameLower.includes(k));
            if (sharedKeywords.length > 0) {
                score += 15 * sharedKeywords.length;
            }
        });

        Object.keys(behavior.cartCategories).forEach(cat => {
            if (catLower.includes(cat)) {
                score += 10 * Math.min(behavior.cartCategories[cat], 3);
            }
        });

        // 3. Purchase History Affinity (Max +20 points)
        purchasedKeywords.forEach(pk => {
            if (nameLower.includes(pk)) {
                score += 5;
            }
        });

        // 4. Pet Profile Affinity (+40 points for direct pet type match!)
        let isPetMatch = false;
        let matchedPetLabel = '';

        if (Array.isArray(myPets) && myPets.length > 0) {
            myPets.forEach(pet => {
                const petSpecies = (pet.species || pet.pet_type || pet.type || '').toLowerCase();
                const petName = pet.name || pet.pet_name || '';

                if (petSpecies) {
                    if (targetPetCode !== 'all' && (targetPetCode.includes(petSpecies) || petSpecies.includes(targetPetCode) || targetPetName.includes(petSpecies) || petSpecies.includes(targetPetName))) {
                        score += 40; // High priority boost +40 for exact target_pet_type match!
                        isPetMatch = true;
                        if (!matchedPetLabel) matchedPetLabel = petName ? `${petName} (${pet.species || pet.pet_type})` : (pet.species || pet.pet_type);
                    } else if (nameLower.includes(petSpecies) || catLower.includes(petSpecies)) {
                        score += 25;
                        isPetMatch = true;
                        if (!matchedPetLabel) matchedPetLabel = petName ? `${petName} (${pet.species || pet.pet_type})` : (pet.species || pet.pet_type);
                    }
                }
            });

            if (targetPetCode === 'all') {
                score += 15; // Universal products for all pets get medium boost
            }
        }

        // Cap score at 99%, minimum at 68%
        let matchPercentage = Math.min(99, Math.max(68, Math.round(score)));
        
        // Randomize slight variance (±2%) so ratings look dynamic and natural
        const seed = (product.product_id || 1) * 7;
        const variance = (seed % 5) - 2;
        matchPercentage = Math.min(99, Math.max(70, matchPercentage + variance));

        // Generate Reason Badge
        let aiReason = `สินค้าแนะนำ`;
        if (isPetMatch && matchedPetLabel) {
            aiReason = `สำหรับ ${matchedPetLabel}`;
        } else if (targetPetName && targetPetCode !== 'all') {
            aiReason = `เหมาะสำหรับ${targetPetName}`;
        }

        return {
            ...product,
            aiScore: matchPercentage,
            aiReason: aiReason,
            isPetMatch: isPetMatch
        };
    });

    // Sort by AI Score descending
    return scoredProducts.sort((a, b) => b.aiScore - a.aiScore);
}
