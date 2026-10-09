{{-- Seller Centre header: a member of the shop's staff sees who they are signed in as and their role --}}
@if(\App\Support\CurrentShop::isStaff())
    <p class="mt-0.5 text-xs text-gray-500" data-shop-staff-role>{{ __('Signed in as :name · :role', ['name' => auth()->user()->name, 'role' => \App\Support\ShopStaffAccess::roleLabel(\App\Support\CurrentShop::role())]) }}</p>
@endif
