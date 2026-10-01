<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'dv' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('Something went wrong') }} - iruali</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <style>
        body { margin: 0; font-family: Figtree, ui-sans-serif, system-ui, sans-serif; background: #F5F8F7; color: #0F2A3A; }
        main { max-width: 32rem; margin: 0 auto; padding: 4rem 1rem; text-align: center; }
        .code { font-size: .8rem; font-weight: 700; letter-spacing: .1em; color: #0B7A70; }
        h1 { font-size: 1.75rem; margin: .5rem 0; }
        p { color: #4B5B66; line-height: 1.6; }
        a { display: inline-block; margin-top: 1.5rem; padding: .7rem 1.25rem; border-radius: .5rem; background: #0B7A70; color: #fff; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <main>
        <img src="/images/brand/iruali-mark.svg" alt="" width="96" height="92" style="height:5rem;width:auto;opacity:.85">
        <p class="code">500</p>
        <h1>{{ __('Something went wrong') }}</h1>
        <p>{{ __('We have been notified. Please try again in a moment.') }}</p>
        <a href="/">{{ __('Go to the home page') }}</a>
    </main>
</body>
</html>
