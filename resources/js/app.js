import './bootstrap';

// Prevent large icons from being displayed
document.addEventListener('DOMContentLoaded', function() {
    // Find and resize any large icons
    const icons = document.querySelectorAll('img[src*="favicon"], img[src*="touch-icon"], img[src*="og-image"]');
    icons.forEach(icon => {
        icon.style.maxWidth = '32px';
        icon.style.maxHeight = '32px';
        icon.style.width = 'auto';
        icon.style.height = 'auto';
    });
    
    // Hide any apple-touch-icon images
    const touchIcons = document.querySelectorAll('img[src*="apple-touch-icon"], img[src*="precomposed"]');
    touchIcons.forEach(icon => {
        icon.style.display = 'none';
    });
    
    // Force resize any large images
    const largeImages = document.querySelectorAll('img[width="180"], img[height="180"], img[width="152"], img[height="152"]');
    largeImages.forEach(img => {
        img.style.maxWidth = '32px';
        img.style.maxHeight = '32px';
        img.style.width = 'auto';
        img.style.height = 'auto';
    });
});

// Also run immediately in case DOM is already loaded
if (document.readyState === 'loading') {
    // DOM is still loading
} else {
    // DOM is already loaded
    const icons = document.querySelectorAll('img[src*="favicon"], img[src*="touch-icon"], img[src*="og-image"]');
    icons.forEach(icon => {
        icon.style.maxWidth = '32px';
        icon.style.maxHeight = '32px';
        icon.style.width = 'auto';
        icon.style.height = 'auto';
    });
}

// Aggressive icon hiding - run every 100ms for the first 5 seconds
let iconCheckCount = 0;
const iconCheckInterval = setInterval(() => {
    const largeImages = document.querySelectorAll('img[width="180"], img[height="180"], img[width="152"], img[height="152"], img[width="144"], img[height="144"]');
    largeImages.forEach(img => {
        img.style.display = 'none';
        img.style.maxWidth = '32px';
        img.style.maxHeight = '32px';
    });
    
    iconCheckCount++;
    if (iconCheckCount >= 50) { // Stop after 5 seconds
        clearInterval(iconCheckInterval);
    }
}, 100);
import './notifications';
