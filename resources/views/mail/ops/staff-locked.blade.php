<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Staff account locked</title></head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h2 style="color: #b91c1c;">Staff account locked</h2>
    <p>The staff account of {{ $name }} ({{ $email }}) had {{ \App\Support\StaffLockout::ATTEMPTS }} wrong passwords or two-step codes in a row, from IP {{ $ip ?: 'unknown' }}. It is locked until {{ $until->format('j M Y H:i') }}.</p>
    <p>If this was not them, someone may be guessing the password: ask them to change it and turn on two-step sign-in. If it was them, an admin can unlock it now.</p>
    <p><a href="{{ route('admin.users') }}">Admin → Users</a></p>
</body>
</html>
