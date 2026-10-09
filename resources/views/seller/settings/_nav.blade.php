@php
    $settingsTabs = [
        'seller.settings.bank' => __('Bank account'),
    ];
    if (\Illuminate\Support\Facades\Route::has('seller.settings.notifications')) {
        $settingsTabs['seller.settings.notifications'] = __('Notifications');
    }
    if (\Illuminate\Support\Facades\Route::has('seller.settings.delivery')) {
        $settingsTabs['seller.settings.delivery'] = __('Delivery & pickup');
    }
    if (\Illuminate\Support\Facades\Route::has('seller.settings.verification')) {
        $settingsTabs['seller.settings.verification'] = __('Business verification');
    }
    if (\Illuminate\Support\Facades\Route::has('seller.settings.holiday')) {
        $settingsTabs['seller.settings.holiday'] = __('Holiday mode');
    }
    if (\Illuminate\Support\Facades\Route::has('seller.settings.tax')) {
        $settingsTabs['seller.settings.tax'] = __('Tax');
    }
@endphp
<nav class="flex flex-wrap gap-2 text-sm" aria-label="{{ __('Settings') }}">
    @foreach($settingsTabs as $route => $label)
        <a href="{{ route($route) }}" class="rounded-full px-3 py-1.5 font-medium {{ request()->routeIs($route) ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 shadow hover:bg-gray-50' }}">{{ $label }}</a>
    @endforeach
</nav>
