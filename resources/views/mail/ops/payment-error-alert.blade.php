<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Payment error</title></head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h2 style="color: #b91c1c;">Payment error on {{ config('app.name') }}</h2>
    <p>Something in the card payment code failed. Customers may be unable to pay until this is fixed.</p>
    <table cellpadding="6" style="border-collapse: collapse;">
        <tr><td style="color:#6b7280">Exception</td><td><strong>{{ $event->exception_class }}</strong></td></tr>
        <tr><td style="color:#6b7280">Message</td><td>{{ $event->message }}</td></tr>
        <tr><td style="color:#6b7280">Where</td><td>{{ $event->shortFile() }}:{{ $event->line }}</td></tr>
        <tr><td style="color:#6b7280">URL</td><td>{{ $event->url ?: '(console)' }}</td></tr>
        <tr><td style="color:#6b7280">Seen</td><td>{{ $event->count }} time(s), first {{ $event->first_seen_at?->format('d M Y H:i') }}, last {{ $event->last_seen_at?->format('d M Y H:i') }}</td></tr>
    </table>
    <p><a href="{{ route('admin.errors.show', $event) }}">Open in the admin error list</a></p>
    <p style="color:#6b7280; font-size: 12px;">You get at most one email per hour for the same error. Mark it resolved in the admin once it is fixed; it reopens by itself if it happens again.</p>
</body>
</html>
