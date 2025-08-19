import './bootstrap';

// Handle favicon and touch icon sizing
document.addEventListener('DOMContentLoaded', function() {
    // Ensure favicon and touch icons are properly sized
    const icons = document.querySelectorAll('img[src*="favicon"], img[src*="apple-touch-icon"], img[src*="precomposed"]');
    icons.forEach(icon => {
        icon.style.maxWidth = '32px';
        icon.style.maxHeight = '32px';
        icon.style.width = 'auto';
        icon.style.height = 'auto';
    });
    
    // Hide any apple-touch-icon images that might still be requested
    const touchIcons = document.querySelectorAll('img[src*="apple-touch-icon"], img[src*="precomposed"]');
    touchIcons.forEach(icon => {
        icon.style.display = 'none';
    });
});

import './notifications';
