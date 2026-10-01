{{-- Shop performance numbers and charts; used by Seller Centre → Performance and Admin → seller performance. --}}
@php
    use App\Support\Money;
    $weekly = $report['weekly'];
    $maxWeek = max(collect($weekly)->max('revenue'), 1);
    $late = $report['late'];
    $returns = $report['return_rate'];
    $ratings = $report['ratings'];
    $repeat = $report['repeat'];
    $pct = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format($v, 1), '0'), '.').'%';
@endphp

<div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
    <div class="rounded-lg bg-white p-5 shadow">
        <p class="text-sm font-medium text-gray-500">{{ __('Late shipments') }}</p>
        <p class="mt-1 text-2xl font-semibold {{ ($late['rate'] ?? 0) > 10 ? 'text-red-700' : 'text-gray-900' }}">{{ $pct($late['rate']) }}</p>
        <p class="mt-1 text-xs text-gray-500">{{ __(':late of :total paid orders in the last :days days, not shipped within :n days of payment.', ['late' => $late['count'], 'total' => $late['total'], 'days' => $report['window_days'], 'n' => $report['late_days']]) }}</p>
    </div>
    <div class="rounded-lg bg-white p-5 shadow">
        <p class="text-sm font-medium text-gray-500">{{ __('Return rate') }}</p>
        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $pct($returns['rate']) }}</p>
        <p class="mt-1 text-xs text-gray-500">{{ __(':returned of :sold items sold in the last :days days were returned.', ['returned' => $returns['returned'], 'sold' => $returns['sold'], 'days' => $report['window_days']]) }}</p>
    </div>
    <div class="rounded-lg bg-white p-5 shadow">
        <p class="text-sm font-medium text-gray-500">{{ __('Average rating') }}</p>
        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $ratings['overall'] === null ? '—' : number_format($ratings['overall'], 1).' ★' }}</p>
        <p class="mt-1 text-xs text-gray-500">{{ trans_choice(':count review|:count reviews', $ratings['count'], ['count' => $ratings['count']]) }}</p>
    </div>
    <div class="rounded-lg bg-white p-5 shadow">
        <p class="text-sm font-medium text-gray-500">{{ __('Repeat customers') }}</p>
        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $pct($repeat['rate']) }}</p>
        <p class="mt-1 text-xs text-gray-500">{{ __(':repeat of :customers customers ordered more than once.', ['repeat' => $repeat['repeat'], 'customers' => $repeat['customers']]) }}</p>
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-2">
    <div class="rounded-lg bg-white p-5 shadow">
        <h2 class="text-lg font-semibold text-gray-900">{{ __('Sales by week, last 12 weeks') }}</h2>
        <div class="mt-6 flex h-40 items-end gap-1" data-chart="weekly">
            @foreach($weekly as $week)
                <div class="flex h-full flex-1 flex-col items-center justify-end" title="{{ $week['label'] }}: {{ Money::format($week['revenue']) }} · {{ trans_choice(':count order|:count orders', $week['orders'], ['count' => $week['orders']]) }}">
                    <div class="w-full rounded-t bg-primary-500" style="height: {{ max($week['revenue'] / $maxWeek * 100, $week['revenue'] > 0 ? 2 : 0) }}%"></div>
                </div>
            @endforeach
        </div>
        <div class="mt-2 flex gap-1">
            @foreach($weekly as $week)
                <span class="flex-1 text-center text-[10px] text-gray-500">{{ $loop->index % 2 === 0 ? $week['label'] : '' }}</span>
            @endforeach
        </div>
    </div>

    <div class="rounded-lg bg-white p-5 shadow">
        <h2 class="text-lg font-semibold text-gray-900">{{ __('Average rating by month') }}</h2>
        @php
            $points = collect($ratings['months'])->values();
            $w = 300; $h = 120; $pad = 10;
            $x = fn ($i) => $pad + $i * (($w - 2 * $pad) / max(count($points) - 1, 1));
            $y = fn ($avg) => $h - $pad - (($avg - 1) / 4) * ($h - 2 * $pad);
            $path = $points->filter(fn ($p) => $p['avg'] !== null)->map(fn ($p, $i) => round($x($i), 1).','.round($y($p['avg']), 1))->values();
        @endphp
        <svg viewBox="0 0 {{ $w }} {{ $h }}" class="mt-4 h-40 w-full" role="img" aria-label="{{ __('Average rating by month') }}" data-chart="ratings">
            @foreach([1, 2, 3, 4, 5] as $star)
                <line x1="{{ $pad }}" y1="{{ $y($star) }}" x2="{{ $w - $pad }}" y2="{{ $y($star) }}" stroke="#e5e7eb" stroke-width="1" />
                <text x="0" y="{{ $y($star) + 3 }}" font-size="8" fill="#9ca3af">{{ $star }}</text>
            @endforeach
            @if($path->count() > 1)
                <polyline points="{{ $path->join(' ') }}" fill="none" stroke="#0B7A70" stroke-width="2" stroke-linejoin="round" />
            @endif
            @foreach($points as $i => $p)
                @if($p['avg'] !== null)
                    <circle cx="{{ round($x($i), 1) }}" cy="{{ round($y($p['avg']), 1) }}" r="3" fill="#0B7A70"><title>{{ $p['label'] }}: {{ number_format($p['avg'], 1) }} ★ ({{ $p['count'] }})</title></circle>
                @endif
            @endforeach
        </svg>
        <div class="mt-1 flex">
            @foreach($points as $p)
                <span class="flex-1 text-center text-[10px] text-gray-500">{{ $p['label'] }}</span>
            @endforeach
        </div>
    </div>
</div>

<div class="overflow-hidden rounded-lg bg-white shadow">
    <div class="border-b border-gray-100 px-5 py-4"><h2 class="text-lg font-semibold text-gray-900">{{ __('Best sellers') }}</h2><p class="text-xs text-gray-500">{{ __('Top 10 by revenue, all time.') }}</p></div>
    <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
            <tr><th class="px-5 py-3 text-start">#</th><th class="px-5 py-3 text-start">{{ __('Product') }}</th><th class="px-5 py-3 text-end">{{ __('Units') }}</th><th class="px-5 py-3 text-end">{{ __('Revenue') }}</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($report['best_sellers'] as $row)
                <tr>
                    <td class="px-5 py-3 text-gray-500">{{ $loop->iteration }}</td>
                    <td class="px-5 py-3 text-gray-900">{{ $row['name'] }}</td>
                    <td class="px-5 py-3 text-end text-gray-700">{{ $row['units'] }}</td>
                    <td class="px-5 py-3 text-end text-gray-900">{{ Money::format($row['revenue']) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-5 py-8 text-center text-gray-500">{{ __('No sales yet.') }}</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>
