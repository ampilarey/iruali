// "Where should we deliver?": the island dialog. The list comes as JSON when the dialog opens
// (GET /delivery/island); picking an island, "Use my location" (nearest island, worked out on the
// server) and "Not now" post to the server, which keeps the choice in the session, a cookie and
// the account. Plain DOM, no library.
(function () {
    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    function post(url, data) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
            credentials: 'same-origin',
            body: JSON.stringify(data || {})
        }).then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); });
    }

    function el(tag, cls, text) {
        var e = document.createElement(tag);
        if (cls) e.className = cls;
        if (text) e.textContent = text;
        return e;
    }

    function setup(dialog) {
        var list = dialog.querySelector('[data-island-dialog-list]');
        var search = dialog.querySelector('[data-island-dialog-search]');
        var status = dialog.querySelector('[data-island-status]');
        var dv = document.documentElement.lang === 'dv';
        var loaded = false;
        var choosing = false;

        function say(text) {
            status.textContent = text;
            status.classList.toggle('hidden', !text);
        }

        function choose(id) {
            choosing = true;
            post(dialog.dataset.storeUrl, { island_id: id }).then(function () { window.location.reload(); })
                .catch(function () { choosing = false; });
        }

        function render(data) {
            list.innerHTML = '';
            (data.atolls || []).forEach(function (group) {
                var box = el('div', 'mb-3');
                box.dataset.group = '1';
                box.appendChild(el('p', 'mb-1 text-xs font-bold uppercase tracking-wide text-gray-500', dv && group.atoll_dv ? group.atoll_dv + ' · ' + group.atoll : group.atoll));
                var grid = el('div', 'grid grid-cols-2 gap-1');
                group.islands.forEach(function (island) {
                    var b = el('button', 'rounded-lg border border-gray-200 px-2 py-1.5 text-start hover:border-primary hover:bg-primary-50');
                    b.type = 'button';
                    b.dataset.search = [island.name_en, island.name_dv, group.atoll, group.atoll_dv].join(' ').toLowerCase();
                    b.appendChild(el('span', 'block font-medium text-dark', island.name));
                    if (island.greater_male) b.appendChild(el('span', 'block text-[11px] text-gray-500', dialog.dataset.textGreaterMale));
                    if (data.island && data.island.id === island.id) b.classList.add('border-primary', 'bg-primary-50');
                    b.addEventListener('click', function () { choose(island.id); });
                    grid.appendChild(b);
                });
                box.appendChild(grid);
                list.appendChild(box);
            });
            filter();
        }

        function filter() {
            var q = (search.value || '').trim().toLowerCase();
            var any = false;
            list.querySelectorAll('[data-group]').forEach(function (group) {
                var visible = 0;
                group.querySelectorAll('button').forEach(function (b) {
                    var hit = !q || b.dataset.search.indexOf(q) !== -1;
                    b.hidden = !hit;
                    if (hit) visible++;
                });
                group.hidden = visible === 0;
                if (visible) any = true;
            });
            var none = list.querySelector('[data-none]');
            if (!any && !none) { none = el('p', 'text-gray-500', dialog.dataset.textNoMatch); none.dataset.none = '1'; list.appendChild(none); }
            if (none) none.hidden = any;
        }

        function open() {
            if (typeof dialog.showModal === 'function') dialog.showModal(); else dialog.setAttribute('open', '');
            if (!loaded) {
                loaded = true;
                fetch(dialog.dataset.listUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); }).then(render).catch(function () { loaded = false; });
            }
            setTimeout(function () { search.focus(); }, 50);
        }

        // Closing without choosing: everything stays shown, and the dialog does not open by itself again
        function dismiss() {
            if (dialog.open) dialog.close();
        }
        dialog.addEventListener('close', function () {
            if (!choosing && dialog.hasAttribute('data-auto-open')) {
                dialog.removeAttribute('data-auto-open');
                post(dialog.dataset.dismissUrl).catch(function () {});
            }
        });

        search.addEventListener('input', filter);
        dialog.querySelectorAll('[data-island-dialog-close], [data-island-dialog-skip]').forEach(function (b) { b.addEventListener('click', dismiss); });
        document.querySelectorAll('[data-island-dialog-open]').forEach(function (b) { b.addEventListener('click', open); });

        var locate = dialog.querySelector('[data-island-locate]');
        if (!navigator.geolocation) locate.hidden = true;
        locate.addEventListener('click', function () {
            say(dialog.dataset.textLocating);
            navigator.geolocation.getCurrentPosition(function (pos) {
                choosing = true;
                post(dialog.dataset.locateUrl, { lat: pos.coords.latitude, lng: pos.coords.longitude })
                    .then(function () { window.location.reload(); })
                    .catch(function () { choosing = false; say(dialog.dataset.textLocatedFailed); });
            }, function () { say(dialog.dataset.textLocatedFailed); }, { timeout: 10000, maximumAge: 600000 });
        });

        if (dialog.hasAttribute('data-auto-open')) open();
    }

    function init() { document.querySelectorAll('[data-island-dialog]').forEach(setup); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
