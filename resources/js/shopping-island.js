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

        function islandButton(island, group, data, extra) {
            var b = el('button', 'rounded-lg border border-gray-200 px-2 py-1.5 text-start hover:border-primary hover:bg-primary-50' + (extra ? ' ' + extra : ''));
            b.type = 'button';
            b.dataset.search = [island.name_en, island.name_dv, group.atoll, group.atoll_dv].join(' ').toLowerCase();
            b.appendChild(el('span', 'block font-medium text-dark', island.name));
            if (data.island && data.island.id === island.id) b.classList.add('border-primary', 'bg-primary-50');
            b.addEventListener('click', function () { choose(island.id); });
            return b;
        }

        // Greater Malé first, as quick picks (most shops are there); then every atoll as a row that
        // opens to show its islands. The atoll of the current island starts open; searching opens
        // every atoll with a match.
        function render(data) {
            list.innerHTML = '';
            var groups = data.atolls || [];
            var male = [];
            groups.forEach(function (group) {
                group.islands.forEach(function (island) { if (island.greater_male) male.push({ island: island, group: group }); });
            });
            if (male.length) {
                var quick = el('div', 'mb-3');
                quick.dataset.quick = '1';
                quick.appendChild(el('p', 'mb-1 text-xs font-bold uppercase tracking-wide text-gray-500', dialog.dataset.textGreaterMale));
                var row = el('div', 'grid grid-cols-3 gap-1');
                male.forEach(function (m) { row.appendChild(islandButton(m.island, m.group, data, 'text-center')); });
                quick.appendChild(row);
                list.appendChild(quick);
            }

            var atolls = el('div', 'divide-y divide-gray-100 rounded-xl border border-gray-200');
            groups.forEach(function (group) {
                var box = el('details', 'group');
                box.dataset.group = '1';
                var summary = el('summary', 'flex cursor-pointer list-none items-center justify-between gap-2 px-3 py-2.5 hover:bg-gray-50 [&::-webkit-details-marker]:hidden');
                var name = el('span', 'min-w-0');
                name.appendChild(el('span', 'font-semibold text-dark', dv && group.atoll_dv ? group.atoll_dv : group.atoll));
                if (dv && group.atoll_dv) name.appendChild(el('span', 'ms-1 text-xs text-gray-500', group.atoll));
                summary.appendChild(name);
                var meta = el('span', 'flex shrink-0 items-center gap-2 text-xs text-gray-500');
                meta.appendChild(el('span', '', String(group.islands.length)));
                // Points along the reading direction while closed, down when open
                meta.appendChild(el('span', 'inline-block transition-transform group-open:rotate-90 rtl:group-open:-rotate-90', document.documentElement.dir === 'rtl' ? '‹' : '›'));
                summary.appendChild(meta);
                box.appendChild(summary);
                var grid = el('div', 'grid grid-cols-2 gap-1 px-3 pb-3');
                var current = false;
                group.islands.forEach(function (island) {
                    grid.appendChild(islandButton(island, group, data));
                    if (data.island && data.island.id === island.id) current = true;
                });
                box.appendChild(grid);
                if (current) { box.open = true; box.dataset.current = '1'; }
                // One atoll open at a time (unless searching), brought into view when opened
                box.addEventListener('toggle', function () {
                    if (!box.open || (search.value || '').trim()) return;
                    atolls.querySelectorAll('details[open]').forEach(function (other) { if (other !== box) other.open = false; });
                    box.scrollIntoView({ block: 'start', behavior: 'smooth' });
                });
                atolls.appendChild(box);
            });
            list.appendChild(atolls);
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
                // Searching opens the atolls with a match; clearing it closes them again
                group.open = q ? visible > 0 : group.dataset.current === '1';
                if (visible) any = true;
            });
            var quick = list.querySelector('[data-quick]');
            if (quick) quick.hidden = !!q;
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
            // On a phone the keyboard would cover the list: focus the search only with a mouse
            if (window.matchMedia && window.matchMedia('(pointer: fine)').matches) setTimeout(function () { search.focus(); }, 50);
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
