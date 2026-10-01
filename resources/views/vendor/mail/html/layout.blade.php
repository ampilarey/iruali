@php
    // Dhivehi mail reads right-to-left; everything else left-to-right.
    $rtl = app()->getLocale() === 'dv';
    $dir = $rtl ? 'rtl' : 'ltr';
    $align = $rtl ? 'right' : 'left';
@endphp
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $dir }}">
<head>
<title>{{ config('app.name') }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<style>
@media only screen and (max-width: 600px) {
.inner-body {
width: 100% !important;
}

.footer {
width: 100% !important;
}
}

@media only screen and (max-width: 500px) {
.button {
width: 100% !important;
}
}
@if ($rtl)
[dir="rtl"] p, [dir="rtl"] h1, [dir="rtl"] h2, [dir="rtl"] h3, [dir="rtl"] ul, [dir="rtl"] ol, [dir="rtl"] blockquote, [dir="rtl"] td.content-cell, [dir="rtl"] th, [dir="rtl"] .table td {
text-align: right !important;
direction: rtl;
}
@endif
</style>
{!! $head ?? '' !!}
</head>
<body dir="{{ $dir }}" style="direction: {{ $dir }};">

<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation" dir="{{ $dir }}">
<tr>
<td align="center">
<table class="content" width="100%" cellpadding="0" cellspacing="0" role="presentation">
{!! $header ?? '' !!}

<!-- Email Body -->
<tr>
<td class="body" width="100%" cellpadding="0" cellspacing="0" style="border: hidden !important;">
<table class="inner-body" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation" dir="{{ $dir }}">
<!-- Body content -->
<tr>
<td class="content-cell" dir="{{ $dir }}" style="direction: {{ $dir }}; text-align: {{ $align }};">
{!! Illuminate\Mail\Markdown::parse($slot) !!}

{!! $subcopy ?? '' !!}
</td>
</tr>
</table>
</td>
</tr>

{!! $footer ?? '' !!}
</table>
</td>
</tr>
</table>
</body>
</html>
