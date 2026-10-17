{{-- Checkout: keeps the delivery fee, the estimates, the address section and the Malé time slots in
     step with the customer's choices. The fee is written into the delivery-area radios' data-fee and
     a change event is sent, so the order summary (and the wallet split) update as before. --}}
@php
    $deliveryConfig = [
        'prefix' => \App\Support\Money::prefix(),
        'free' => __('Free'),
        'areaNote' => __('Delivery to :area: :fee'),
        'surchargeNote' => __('Bulky items add :amount to delivery. Free delivery does not cover this charge.'),
        'areaFees' => $deliveryCheckout['areaFees'],
        'areaLabels' => $deliveryCheckout['areas'],
        'atollAreas' => (object) $deliveryCheckout['atollAreas'],
        'addressAreas' => (object) $deliveryCheckout['addressAreas'],
        'shops' => $deliveryCheckout['shops']->map(fn ($shop) => [
            'key' => $shop['key'],
            'pickup' => $shop['pickup'] !== null,
            'surcharge' => $shop['surcharge'],
            'itemsTotal' => $shop['items_total'],
            'own' => $shop['own'],
            'estimates' => $shop['estimates'],
            'reach' => $shop['reach'],
        ])->values(),
        'addressIslands' => (object) $deliveryCheckout['addressIslands'],
    ];
@endphp
<script type="application/json" id="delivery-config">@json($deliveryConfig)</script>
<script>
    (function () {
        function init() {
            var form = document.getElementById('checkout-form');
            var configEl = document.getElementById('delivery-config');
            if (!form || !configEl) return;
            var cfg = JSON.parse(configEl.textContent);
            var fmt = function (n) { return n > 0 ? cfg.prefix + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : cfg.free; };
            var zoneRadios = form.querySelectorAll('input[name="delivery_zone"]');
            var newAddress = form.querySelector('[data-new-address]');
            var addressSection = newAddress ? newAddress.closest('section') : null;
            var pickupContact = form.querySelector('[data-pickup-contact]');
            var slots = form.querySelector('[data-delivery-slots]');
            var busy = false;

            // The atoll of the address being typed or picked from the island list
            var atoll = '';
            var islandSelect = form.querySelector('[data-island-select]');
            var atollText = form.querySelector('[data-atoll-text]');
            var readAtoll = function () {
                var opt = islandSelect && islandSelect.value ? islandSelect.options[islandSelect.selectedIndex] : null;
                atoll = opt ? (opt.dataset.atoll || '') : (atollText ? atollText.value : '');
            };
            readAtoll();

            var checked = function (name) { var el = form.querySelector('input[name="' + name + '"]:checked'); return el ? el.value : null; };
            var methodOf = function (shop) { return shop.pickup ? (checked('fulfilment[' + shop.key + ']') || 'deliver') : 'deliver'; };
            var atollArea = function (name) { return cfg.atollAreas[(name || '').trim().toLowerCase()] || 'islands'; };
            var area = function () {
                var card = form.querySelector('input[name="address_id"]:checked');
                if (card && card.value && cfg.addressAreas[card.value]) return cfg.addressAreas[card.value];
                return checked('delivery_zone') === 'greater_male' ? 'greater_male' : atollArea(atoll);
            };

            // The delivery island: the saved address card's, else the island picked (or typed) for a new address
            function destination() {
                var card = form.querySelector('input[name="address_id"]:checked');
                if (card && card.value && cfg.addressIslands[card.value]) {
                    var a = cfg.addressIslands[card.value];
                    return { id: a.id ? parseInt(a.id, 10) : null, name: a.name || '', known: !!(a.id || a.name) };
                }
                if (islandSelect && islandSelect.value) {
                    return { id: parseInt(islandSelect.value, 10), name: islandSelect.options[islandSelect.selectedIndex].textContent.trim(), known: true };
                }
                var typed = form.querySelector('[data-island-text]');
                var name = typed ? typed.value.trim() : '';
                return { id: null, name: name, known: name !== '' };
            }

            function toggleAddress(allPickup) {
                if (pickupContact) {
                    pickupContact.classList.toggle('hidden', !allPickup);
                    pickupContact.querySelectorAll('input').forEach(function (el) { el.disabled = !allPickup; });
                }
                if (!addressSection) return;
                var hidden = addressSection.hasAttribute('data-pickup-hidden');
                if (allPickup && !hidden) {
                    addressSection.setAttribute('data-pickup-hidden', '');
                    addressSection.classList.add('hidden');
                    addressSection.querySelectorAll('input, select, textarea').forEach(function (el) { el.dataset.wasDisabled = el.disabled ? '1' : '0'; el.disabled = true; });
                } else if (!allPickup && hidden) {
                    addressSection.removeAttribute('data-pickup-hidden');
                    addressSection.classList.remove('hidden');
                    addressSection.querySelectorAll('input, select, textarea').forEach(function (el) { el.disabled = el.dataset.wasDisabled === '1'; });
                }
            }

            // A shop's own fee to the area and island (null: iruali's fee applies), as DeliveryService::shopFee()
            function shopFee(shop, areaKey, dest) {
                var own = shop.own;
                if (!own) return null;
                if (own.free_over && shop.itemsTotal >= own.free_over) return 0;
                if (own.local !== null && own.home && dest.id === own.home) return own.local;
                return areaKey === 'greater_male' ? own.greater_male : own.islands;
            }

            // The delivery fees for an area, before bulky-item charges: each delivering shop's own fee,
            // plus iruali's area fee once when any other shop delivers
            function feesFor(delivered, areaKey, dest) {
                var total = 0, iruali = false;
                delivered.forEach(function (shop) {
                    var own = shopFee(shop, areaKey, dest);
                    if (own === null) iruali = true; else total += own;
                });
                return total + (iruali ? (cfg.areaFees[areaKey] || 0) : 0);
            }

            function update() {
                var delivered = cfg.shops.filter(function (shop) { return methodOf(shop) === 'deliver'; });
                var anyDelivered = delivered.length > 0;
                var surcharge = delivered.reduce(function (sum, shop) { return sum + shop.surcharge; }, 0);
                var current = area();
                var islandsSide = current === 'greater_male' ? atollArea(atoll) : current;
                var dest = destination();
                var fees = {
                    greater_male: anyDelivered ? feesFor(delivered, 'greater_male', dest) + surcharge : 0,
                    islands: anyDelivered ? feesFor(delivered, islandsSide, dest) + surcharge : 0
                };

                zoneRadios.forEach(function (radio) {
                    radio.dataset.fee = (fees[radio.value] || 0).toFixed(2);
                    var label = form.querySelector('[data-zone-fee="' + radio.value + '"]');
                    if (label) label.textContent = fmt(fees[radio.value] || 0);
                });
                var islandsLabel = form.querySelector('[data-islands-area]');
                if (islandsLabel) islandsLabel.textContent = islandsSide !== 'islands' ? (cfg.areaLabels[islandsSide] || '') : '';

                cfg.shops.forEach(function (shop) {
                    form.querySelectorAll('[data-shop="' + shop.key + '"] [data-shop-estimate]').forEach(function (el) { el.textContent = shop.estimates[current] || ''; });
                });

                cfg.shops.forEach(function (shop) {
                    var feeEl = form.querySelector('[data-shop="' + shop.key + '"] [data-shop-fee]');
                    if (feeEl) feeEl.textContent = fmt(shopFee(shop, current, dest) || 0);
                    var feeLine = form.querySelector('[data-shop="' + shop.key + '"] [data-shop-fee-line]');
                    if (feeLine) feeLine.classList.toggle('hidden', methodOf(shop) !== 'deliver');
                });

                // Shops that don't reach the delivery island: show their notice (the server refuses the order too)
                cfg.shops.forEach(function (shop) {
                    var box = form.querySelector('[data-shop="' + shop.key + '"] [data-shop-blocked]');
                    if (!box || !shop.reach) return;
                    var blocked = methodOf(shop) === 'deliver' && dest.known && (dest.id ? shop.reach.indexOf(dest.id) === -1 : true);
                    box.classList.toggle('hidden', !blocked);
                    var text = box.querySelector('[data-shop-blocked-text]');
                    if (text && blocked) text.textContent = box.dataset.template.split(':island').join(dest.name);
                });

                var toggle = function (selector, show) { var el = form.querySelector(selector); if (el) el.classList.toggle('hidden', !show); return el; };
                toggle('[data-delivery-zones]', anyDelivered);
                var note = toggle('[data-area-note]', anyDelivered);
                if (note) note.textContent = cfg.areaNote.replace(':area', cfg.areaLabels[current] || current).replace(':fee', fmt(anyDelivered ? feesFor(delivered, current, dest) : 0));
                var surchargeNote = toggle('[data-surcharge-note]', anyDelivered && surcharge > 0);
                if (surchargeNote) surchargeNote.textContent = cfg.surchargeNote.replace(':amount', fmt(surcharge));
                toggle('[data-all-pickup-note]', !anyDelivered);
                toggleAddress(!anyDelivered);

                if (slots) {
                    var showSlots = anyDelivered && current === 'greater_male';
                    slots.classList.toggle('hidden', !showSlots);
                    slots.querySelectorAll('input[name="delivery_slot"]').forEach(function (el) { el.disabled = !showSlots || el.dataset.unavailable === '1'; });
                }

                // Let the order summary pick up the new fee
                var zone = form.querySelector('input[name="delivery_zone"]:checked');
                if (zone) { busy = true; zone.dispatchEvent(new Event('change', { bubbles: true })); busy = false; }
            }

            form.addEventListener('change', function (e) {
                if (busy) return;
                var name = e.target && e.target.name ? e.target.name : '';
                if (name === 'delivery_zone' || name === 'address_id' || name.indexOf('fulfilment[') === 0) update();
            });
            form.addEventListener('island:change', function (e) { readAtoll(); if (e.detail && e.detail.id) atoll = e.detail.atoll || atoll; update(); });
            if (atollText) atollText.addEventListener('input', function () { readAtoll(); update(); });
            var islandText = form.querySelector('[data-island-text]');
            if (islandText) islandText.addEventListener('input', update);
            update();
        }
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
    })();
</script>
