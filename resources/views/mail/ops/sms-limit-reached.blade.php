<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Monthly SMS limit reached</title></head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h2 style="color: #b45309;">Monthly SMS limit reached</h2>
    <p>{{ config('app.name') }} has sent {{ number_format($sent) }} text messages this month, the limit of {{ number_format($limit) }} set in Admin → SMS.</p>
    <p>Order updates by SMS have stopped until the 1st of next month. Verification codes still go, and customers still get every email.</p>
    <p><a href="{{ route('admin.sms') }}">Raise or remove the limit in Admin → SMS</a></p>
</body>
</html>
