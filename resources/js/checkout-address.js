// Checkout: saved-address cards vs. the "new address" form, and the delivery area that follows the island.
// When a saved address is chosen the new-address fields are disabled (not submitted, not required);
// the chosen address or island ticks the matching delivery zone radio so the fee shown is right.
(function () {
    function init() {
        var form = document.getElementById('checkout-form');
        if (!form) return;
        var cards = form.querySelectorAll('input[name="address_id"]');
        var panel = form.querySelector('[data-new-address]');
        var zones = form.querySelectorAll('input[name="delivery_zone"]');

        function pickZone(zone) {
            if (!zone) return;
            zones.forEach(function (r) {
                if (r.value === zone && !r.checked) { r.checked = true; r.dispatchEvent(new Event('change', { bubbles: true })); }
            });
        }

        function syncPanel() {
            if (!panel || !cards.length) return;
            var chosen = Array.prototype.find.call(cards, function (c) { return c.checked; });
            var useNew = !chosen || chosen.value === '';
            panel.classList.toggle('hidden', !useNew);
            panel.querySelectorAll('input, select, textarea').forEach(function (el) {
                el.disabled = !useNew;
            });
            if (!useNew && chosen) pickZone(chosen.dataset.zone);
            if (useNew) {
                var select = panel.querySelector('[data-island-select]');
                var opt = select && select.options[select.selectedIndex];
                if (opt) pickZone(opt.dataset.zone);
            }
        }

        cards.forEach(function (c) { c.addEventListener('change', syncPanel); });
        form.addEventListener('island:change', function (e) { pickZone(e.detail && e.detail.zone); });
        syncPanel();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
