<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex">
    <title>Discount cards {{ $batch }}</title>
    <style>
        body { font-family: Figtree, ui-sans-serif, system-ui, sans-serif; margin: 0; color: #0F2A3A; }
        .bar { padding: 12px; text-align: end; } .bar button { font: inherit; padding: 8px 14px; border-radius: 8px; border: 0; background: #0B7A70; color: #fff; cursor: pointer; }
        .sheet { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8mm; padding: 10mm; }
        .card { border: 1px dashed #5F7680; border-radius: 10px; padding: 8mm; break-inside: avoid; }
        .card h2 { margin: 0; font-size: 20px; } .value { font-size: 28px; font-weight: 700; margin: 4mm 0; }
        .code { font-family: ui-monospace, monospace; font-size: 18px; letter-spacing: .08em; background: #F5F8F7; padding: 2mm 3mm; border-radius: 6px; display: inline-block; }
        .small { font-size: 11px; color: #5F7680; margin-top: 3mm; }
        @media print { .bar { display: none; } .sheet { padding: 0; } }
    </style>
</head>
<body>
    <div class="bar"><button onclick="window.print()">Print {{ $cards->count() }} cards</button></div>
    <div class="sheet">
        @foreach($cards as $card)
            <div class="card">
                <h2>iruali</h2>
                <div class="value">{{ $card->type === 'percent' ? (float) $card->amount.'% off' : 'MVR '.number_format((float) $card->amount, 2).' off' }}</div>
                <div class="code">{{ $card->code }}</div>
                <div class="small">Enter the code in your cart at iruali.mv. One use.@if($card->min_order) Orders over MVR {{ number_format((float) $card->min_order, 2) }}.@endif Valid until {{ $card->valid_until?->format('j M Y') }}.</div>
            </div>
        @endforeach
    </div>
</body>
</html>
