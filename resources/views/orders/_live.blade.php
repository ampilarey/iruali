{{-- Live order page: asks every 20 seconds (while the tab is visible) whether anything changed, and
     reloads, or offers to when the customer is typing. Stops once the order is delivered or cancelled. --}}
@unless($order->isSettled())
    <div id="order-live" class="hidden fixed inset-x-0 bottom-20 z-40 mx-auto w-fit max-w-[90vw] rounded-full bg-dark px-4 py-2 text-sm text-white shadow-lg lg:bottom-6" role="status" data-live-url="{{ $liveUrl }}" data-state="{{ $order->liveState() }}">
        {{ __('This order was just updated.') }}
        <button type="button" class="ms-2 font-semibold underline" onclick="location.reload()">{{ __('Refresh') }}</button>
    </div>
    <script>
        (function () {
            var bar = document.getElementById('order-live');
            var state = bar.dataset.state;
            var timer = setInterval(check, 20000);
            function typing() {
                var el = document.activeElement;
                return el && (el.tagName === 'TEXTAREA' || el.tagName === 'INPUT' || el.tagName === 'SELECT') && el.value !== '';
            }
            function check() {
                if (document.visibilityState !== 'visible') return;
                fetch(bar.dataset.liveUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (data) {
                        if (! data) return;
                        if (data.state !== state) {
                            clearInterval(timer);
                            if (typing()) { bar.classList.remove('hidden'); } else { location.reload(); }
                        } else if (data.settled) {
                            clearInterval(timer);
                        }
                    })
                    .catch(function () {});
            }
        })();
    </script>
@endunless
