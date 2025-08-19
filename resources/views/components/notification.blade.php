@props(['notification'])

@if($notification)
    <div 
        x-data="notificationComponent(@js($notification))"
        x-init="showNotification()"
        class="fixed top-4 right-4 z-50 max-w-sm w-full"
        style="display: none;"
        x-show="visible"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="transform translate-x-full opacity-0"
        x-transition:enter-end="transform translate-x-0 opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="transform translate-x-0 opacity-100"
        x-transition:leave-end="transform translate-x-full opacity-0"
    >
        <div class="bg-white rounded-lg shadow-lg border-l-4 overflow-hidden"
             :class="{
                'border-green-500': notification.type === 'success',
                'border-red-500': notification.type === 'error',
                'border-yellow-500': notification.type === 'warning',
                'border-blue-500': notification.type === 'info',
                'border-purple-500': notification.type === 'question'
             }">
            <div class="p-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <!-- Success Icon -->
                        <svg x-show="notification.type === 'success'" class="h-6 w-6 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <!-- Error Icon -->
                        <svg x-show="notification.type === 'error'" class="h-6 w-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <!-- Warning Icon -->
                        <svg x-show="notification.type === 'warning'" class="h-6 w-6 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                        <!-- Info Icon -->
                        <svg x-show="notification.type === 'info'" class="h-6 w-6 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <!-- Question Icon -->
                        <svg x-show="notification.type === 'question'" class="h-6 w-6 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-3 flex-1">
                        <p x-text="notification.title" class="text-sm font-medium text-gray-900"></p>
                        <p x-text="notification.message" class="mt-1 text-sm text-gray-500"></p>
                    </div>
                    <div class="ml-4 flex-shrink-0 flex">
                        <button 
                            @click="hideNotification()"
                            class="bg-white rounded-md inline-flex text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        >
                            <span class="sr-only">Close</span>
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

<script>
function notificationComponent(notification) {
    return {
        notification: notification,
        visible: false,
        
        showNotification() {
            this.visible = true;
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                this.hideNotification();
            }, 5000);
        },
        
        hideNotification() {
            this.visible = false;
        }
    }
}
</script> 