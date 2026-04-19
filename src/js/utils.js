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
