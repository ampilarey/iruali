<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Refunds</title></head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h2>Refunds on {{ $summary['date'] }}</h2>
    @if($summary['refunded'])
        <table style="border-collapse: collapse; font-size: 14px;">
            @foreach($summary['refunded'] as $row)
                <tr><td style="padding: 4px 12px 4px 0;">{{ $row['what'] }}</td><td style="padding: 4px 12px 4px 0;">MVR {{ number_format($row['amount'], 2) }}</td><td style="padding: 4px 12px 4px 0; color: #6b7280;">{{ $row['why'] }}</td><td style="padding: 4px 0; color: #6b7280;">ref {{ $row['ref'] }}</td></tr>
            @endforeach
            <tr><td style="padding-top: 6px; font-weight: bold;">Total</td><td style="padding-top: 6px; font-weight: bold;">MVR {{ number_format(collect($summary['refunded'])->sum('amount'), 2) }}</td></tr>
        </table>
    @else
        <p>No refunds were recorded.</p>
    @endif
    <p>Still due: {{ $summary['due_count'] }} (MVR {{ number_format($summary['due_total'], 2) }})@if($summary['waiting_approval']), of which {{ $summary['waiting_approval'] }} wait for a second person's approval @endif.</p>
    <p><a href="{{ route('admin.inbox') }}">Admin → Inbox</a></p>
</body>
</html>
