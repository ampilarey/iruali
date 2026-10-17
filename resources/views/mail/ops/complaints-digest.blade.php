<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Complaints</title></head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    @if($kind === 'weekly')
        <h2>Complaints and ratings, last 7 days</h2>
        <ul>
            <li>New complaints: {{ $summary['new'] }}</li>
            <li>Resolved or closed: {{ $summary['resolved'] }}</li>
            <li>Still open: {{ $summary['open'] }}</li>
            <li>Order ratings: {{ $summary['ratings'] }}@if($summary['ratings']), average {{ $summary['average'] }} / 5, {{ $summary['low'] }} rated 1 or 2 @endif</li>
        </ul>
        @if(! empty($summary['categories']))
            <p>By subject:</p>
            <ul>@foreach($summary['categories'] as $label => $count)<li>{{ $label }}: {{ $count }}</li>@endforeach</ul>
        @endif
    @else
        <h2>Complaints waiting for an answer</h2>
        <p>These have not been updated for {{ \App\Console\Commands\RemindComplaints::DAYS }} days or more:</p>
        <ul>
            @foreach($summary['complaints'] as $row)
                <li><a href="{{ $row['url'] }}">{{ $row['reference'] }}</a>: {{ $row['category'] }}, from {{ $row['name'] }}, sent {{ $row['sent'] }}</li>
            @endforeach
        </ul>
    @endif
    <p><a href="{{ route('admin.complaints') }}">Admin → Complaints</a></p>
</body>
</html>
