import Swal from 'sweetalert2';

// Notification utility class
class NotificationManager {
    constructor() {
        this.defaultConfig = {
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        };
    }

    // Success notification
    success(message, title = 'Success!') {
        return Swal.fire({
            ...this.defaultConfig,
            icon: 'success',
            title: title,
            text: message,
            background: '#f0fdf4',
            color: '#166534',
            iconColor: '#16a34a'
        });
    }

    // Error notification
    error(message, title = 'Error!') {
        return Swal.fire({
            ...this.defaultConfig,
            icon: 'error',
            title: title,
            text: message,
            background: '#fef2f2',
            color: '#991b1b',
            iconColor: '#dc2626'
        });
    }

    // Warning notification
    warning(message, title = 'Warning!') {
        return Swal.fire({
            ...this.defaultConfig,
            icon: 'warning',
            title: title,
            text: message,
            background: '#fffbeb',
            color: '#92400e',
            iconColor: '#f59e0b'
        });
    }

    // Info notification
    info(message, title = 'Information') {
        return Swal.fire({
            ...this.defaultConfig,
            icon: 'info',
            title: title,
            text: message,
            background: '#eff6ff',
            color: '#1e40af',
            iconColor: '#3b82f6'
        });
    }

    // Question/Confirmation dialog
    question(message, title = 'Confirm') {
        return Swal.fire({
            title: title,
            text: message,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes',
            cancelButtonText: 'No'
        });
    }

    // Confirmation dialog with custom buttons
    confirm(message, title = 'Confirm', confirmText = 'Yes', cancelText = 'No') {
        return Swal.fire({
            title: title,
            text: message,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: confirmText,
            cancelButtonText: cancelText
        });
    }

    // Delete confirmation
    deleteConfirm(itemName = 'this item') {
        return this.confirm(
            `Are you sure you want to delete ${itemName}? This action cannot be undone.`,
            'Delete Confirmation',
            'Delete',
            'Cancel'
        );
    }

    // Loading state
    loading(message = 'Loading...') {
        return Swal.fire({
            title: message,
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    }

    // Close loading
    close() {
        Swal.close();
    }

    // Show notification from server response
    showFromResponse(response) {
        if (response.success) {
            this.success(response.message || 'Operation completed successfully');
        } else {
            this.error(response.message || 'An error occurred');
        }
    }

    // Show notification from session flash
    showFromSession(notification) {
        if (!notification) return;

        switch (notification.type) {
            case 'success':
                this.success(notification.message, notification.title);
                break;
            case 'error':
                this.error(notification.message, notification.title);
                break;
            case 'warning':
                this.warning(notification.message, notification.title);
                break;
            case 'info':
                this.info(notification.message, notification.title);
                break;
            case 'question':
                this.question(notification.message, notification.title);
                break;
        }
    }

    // Show multiple notifications
    showMultiple(notifications) {
        if (!Array.isArray(notifications)) return;

        notifications.forEach((notification, index) => {
            setTimeout(() => {
                this.showFromSession(notification);
            }, index * 1000); // Show each notification 1 second apart
        });
    }
}

// Create global instance
window.NotificationManager = new NotificationManager();

// Auto-initialize notifications from session
document.addEventListener('DOMContentLoaded', function() {
    // Check for session notifications
    const notificationElement = document.getElementById('session-notification');
    if (notificationElement) {
        const notification = JSON.parse(notificationElement.dataset.notification);
        window.NotificationManager.showFromSession(notification);
    }

    // Check for multiple notifications
    const notificationsElement = document.getElementById('session-notifications');
    if (notificationsElement) {
        const notifications = JSON.parse(notificationsElement.dataset.notifications);
        window.NotificationManager.showMultiple(notifications);
    }

    // Handle legacy session messages
    const legacyNotifications = document.getElementById('legacy-notifications');
    if (legacyNotifications) {
        const legacyDivs = legacyNotifications.querySelectorAll('div[data-type]');
        legacyDivs.forEach((div, index) => {
            setTimeout(() => {
                const type = div.dataset.type;
                const message = div.dataset.message;
                
                switch (type) {
                    case 'success':
                        window.NotificationManager.success(message);
                        break;
                    case 'error':
                        window.NotificationManager.error(message);
                        break;
                    case 'warning':
                        window.NotificationManager.warning(message);
                        break;
                    case 'info':
                        window.NotificationManager.info(message);
                        break;
                }
            }, index * 500); // Show each notification 0.5 seconds apart
        });
    }
});

// Export for module usage
export default window.NotificationManager; 