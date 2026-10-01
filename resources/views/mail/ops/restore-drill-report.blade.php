<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Restore drill</title></head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h2 style="color: {{ $report['ok'] ? '#15803d' : '#b91c1c' }};">Backup restore drill {{ $report['ok'] ? 'passed' : 'FAILED' }}</h2>
    <p>
        {{ config('app.name') }} restored the newest backup
        @if($report['backup'])<strong>{{ $report['backup'] }}</strong> from disk <strong>{{ $report['disk'] }}</strong>@endif
        into the scratch database <strong>{{ $report['drill_database'] ?: '(not set)' }}</strong>
        @if($report['ok']) and the data came back: {{ $report['tables'] }} tables. @else and something went wrong. @endif
    </p>

    @if($report['error'])
        <p style="padding: 10px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b;"><strong>Problem:</strong> {{ $report['error'] }}</p>
    @endif

    @if(! empty($report['counts']))
        <table cellpadding="6" style="border-collapse: collapse; font-size: 14px;">
            <tr style="color:#6b7280; text-align: left;"><th>Table</th><th>Rows in backup</th><th>Rows live now</th></tr>
            @foreach($report['counts'] as $table => $c)
                <tr><td>{{ $table }}</td><td>{{ $c['backup'] ?? 'missing' }}</td><td>{{ $c['live'] }}</td></tr>
            @endforeach
        </table>
        <p style="color:#6b7280; font-size: 12px;">The backup is from last night, so live counts can be a little higher.</p>
    @endif

    <h3>Steps</h3>
    <ul>
        @foreach($report['steps'] as $step)<li>{{ $step }}</li>@endforeach
    </ul>

    <p style="color:#6b7280; font-size: 12px;">
        This runs on the 1st of every month at 04:00 (and by hand with <code>php artisan backup:restore-drill</code>).
        @if(! $report['ok']) Until it passes, assume the backups cannot be restored and fix it first. @endif
    </p>
</body>
</html>
