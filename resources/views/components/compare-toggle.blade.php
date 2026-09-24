@props(['product'])
@php $on = in_array($product->id, session('compare', []), true); @endphp
<form action="{{ route('compare.toggle', $product) }}" method="POST" {{ $attributes }}>
    @csrf
    <button type="submit" class="inline-flex items-center gap-1.5 text-xs font-medium {{ $on ? 'text-primary' : 'text-gray-600 hover:text-primary' }}" aria-pressed="{{ $on ? 'true' : 'false' }}">
        <span class="w-4 h-4 rounded border {{ $on ? 'bg-primary border-primary text-white' : 'border-gray-400 bg-white' }} flex items-center justify-center">@if($on)<x-icon name="check" class="w-3 h-3" />@endif</span>
        {{ __('Compare') }}
    </button>
</form>
