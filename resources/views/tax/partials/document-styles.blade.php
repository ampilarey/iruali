{{-- Print styles shared by the invoices (tax/invoice, tax/commission-invoice). English documents; on Dhivehi pages each label carries its Dhivehi under it. --}}
@if(app()->getLocale() === 'dv')
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thaana:wght@400;600&display=swap" rel="stylesheet">
@endif
<style>
    body { font-family: Figtree, ui-sans-serif, system-ui, sans-serif; color: #0F2A3A; margin: 0; background: #F5F8F7; }
    .sheet { max-width: 800px; margin: 16px auto 24px; background: #fff; border: 1px solid #D5E1DF; border-radius: 12px; padding: 32px; }
    h1 { font-size: 24px; margin: 0; letter-spacing: .01em; }
    h2 { font-size: 12px; text-transform: uppercase; letter-spacing: .06em; color: #5F7680; margin: 24px 0 8px; }
    p { margin: 0; }
    .muted { color: #5F7680; font-size: 13px; }
    .strong { font-weight: 700; font-size: 16px; }
    .row { display: flex; justify-content: space-between; gap: 24px; flex-wrap: wrap; }
    .end { text-align: end; }
    table { width: 100%; border-collapse: collapse; font-size: 14px; }
    th, td { text-align: start; padding: 8px 6px; border-bottom: 1px solid #EAF0EF; vertical-align: top; }
    th { font-size: 12px; color: #5F7680; font-weight: 600; }
    .num { text-align: end; white-space: nowrap; }
    .meta { width: auto; margin-inline-start: auto; margin-top: 8px; }
    .meta th, .meta td { border: 0; padding-block: 2px; padding-inline: 16px 0; }
    .meta td { font-weight: 600; text-align: end; }
    .totals { max-width: 380px; margin-inline-start: auto; margin-top: 8px; }
    .totals td { border: 0; padding: 4px 6px; }
    .grand td { font-weight: 700; font-size: 16px; border-top: 2px solid #0F2A3A; padding-top: 8px; }
    .badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; background: #EAF0EF; }
    .note { margin-top: 20px; padding: 12px 14px; background: #F5F8F7; border-radius: 8px; font-size: 13px; }
    .warn { background: #FDECEA; color: #8A1C12; }
    .dv { display: block; font-family: "Noto Sans Thaana", "MV Boli", sans-serif; font-weight: 400; font-size: 12px; color: #5F7680; text-transform: none; letter-spacing: 0; }
    h1 .dv { font-size: 15px; }
    .actions { max-width: 800px; margin: 16px auto 0; display: flex; gap: 8px; justify-content: flex-end; padding: 0 4px; }
    .btn { font: inherit; font-size: 14px; font-weight: 600; padding: 8px 14px; border-radius: 8px; border: 1px solid #0B7A70; background: #0B7A70; color: #fff; cursor: pointer; text-decoration: none; }
    .btn.alt { background: #fff; color: #0B7A70; }
    @media (max-width: 600px) { .sheet { padding: 20px; margin: 8px; } .end { text-align: start; } .meta { margin-inline-start: 0; } }
    @media print { body { background: #fff; } .sheet { border: 0; margin: 0; padding: 0; } .actions { display: none; } }
</style>
