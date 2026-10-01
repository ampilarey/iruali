<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Error digest</title></head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h2>{{ config('app.name') }} error digest — {{ now()->format('d M Y') }}</h2>
    <p>{{ $unresolved }} unresolved error(s) in total. <a href="{{ route('admin.errors') }}">Open the admin error list</a>.</p>

    @if($new->isNotEmpty())
        <h3>New in the last 24 hours ({{ $new->count() }})</h3>
        <table cellpadding="6" style="border-collapse: collapse; font-size: 14px;">
            <tr style="color:#6b7280; text-align: left;"><th>Exception</th><th>Where</th><th>Count</th></tr>
            @foreach($new as $event)
                <tr>
                    <td><a href="{{ route('admin.errors.show', $event) }}">{{ $event->shortClass() }}</a><br><span style="color:#6b7280">{{ \Illuminate\Support\Str::limit($event->message, 140) }}</span></td>
                    <td>{{ $event->shortFile() }}:{{ $event->line }}</td>
                    <td>{{ $event->count }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if($top->isNotEmpty())
        <h3>Most frequent in the last 24 hours</h3>
        <table cellpadding="6" style="border-collapse: collapse; font-size: 14px;">
            <tr style="color:#6b7280; text-align: left;"><th>Exception</th><th>Where</th><th>Count</th><th>Last seen</th></tr>
            @foreach($top as $event)
                <tr>
                    <td><a href="{{ route('admin.errors.show', $event) }}">{{ $event->shortClass() }}</a><br><span style="color:#6b7280">{{ \Illuminate\Support\Str::limit($event->message, 140) }}</span></td>
                    <td>{{ $event->shortFile() }}:{{ $event->line }}</td>
                    <td>{{ $event->count }}</td>
                    <td>{{ $event->last_seen_at?->format('d M H:i') }}</td>
                </tr>
            @endforeach
        </table>
    @endif
</body>
</html>
