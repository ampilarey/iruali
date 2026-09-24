{{-- Buttons for the statuses this order can move to next. Needs: $action (POST url), $nextStatuses (array). --}}
@php
    $labels = [
        'processing' => 'Mark as processing',
        'shipped' => 'Mark as shipped',
        'delivered' => 'Mark as delivered',
        'cancelled' => 'Cancel order',
    ];
@endphp
@if(count($nextStatuses))
    <div class="flex flex-wrap gap-2">
        @foreach($nextStatuses as $status)
            <form method="POST" action="{{ $action }}"
                  @if($status === 'cancelled') onsubmit="return confirm('Cancel this order? Stock is returned and loyalty points are reversed.')" @endif>
                @csrf
                <input type="hidden" name="status" value="{{ $status }}">
                <button type="submit"
                        class="rounded-lg px-4 py-2 text-sm font-semibold {{ $status === 'cancelled' ? 'border border-danger text-danger hover:bg-danger-50' : 'bg-primary text-white hover:bg-primary-hover' }}">
                    {{ $labels[$status] ?? ucfirst($status) }}
                </button>
            </form>
        @endforeach
    </div>
@else
    <p class="text-sm text-gray-600">This order is {{ $order->status }}. There is nothing more to update.</p>
@endif
