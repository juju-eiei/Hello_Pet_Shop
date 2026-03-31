// Import main styles
import '../css/style.css';

// Common Initialization
document.addEventListener('DOMContentLoaded', () => {
    console.log('Hello Pet Shop - Premium UI Loaded');
    
    // Global User state check (if needed)
    const user = JSON.parse(localStorage.getItem('user'));
    if (user) {
        console.log(`Logged in as: ${user.username} (${user.role_name})`);
    }
});
