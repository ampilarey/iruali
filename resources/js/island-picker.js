// Island picker (saved addresses, checkout): a search box filters the grouped <select>; picking
// "not in the list" reveals the free-text island/atoll fields. Each option carries its delivery
// zone, which the picker announces as an "island:change" event so checkout can tick the matching
// delivery area. Plain DOM, no library.
(function () {
    function setup(picker) {
        var search = picker.querySelector('[data-island-search]');
        var select = picker.querySelector('[data-island-select]');
        var other = picker.querySelector('[data-island-other]');
        var islandText = picker.querySelector('[data-island-text]');
        if (!select) return;

        function sync() {
            var opt = select.options[select.selectedIndex];
            var isOther = !select.value;
            if (other) other.classList.toggle('hidden', !isOther);
            if (islandText) {
                if (isOther) islandText.setAttribute('required', 'required');
                else islandText.removeAttribute('required');
            }
            picker.dispatchEvent(new CustomEvent('island:change', {
                bubbles: true,
                detail: { id: select.value, zone: opt ? (opt.dataset.zone || '') : '', atoll: opt ? (opt.dataset.atoll || '') : '' }
            }));
        }

        function filter() {
            var q = (search.value || '').trim().toLowerCase();
            Array.prototype.forEach.call(select.querySelectorAll('optgroup'), function (group) {
                var visible = 0;
                Array.prototype.forEach.call(group.querySelectorAll('option'), function (opt) {
                    var hit = !q || (opt.dataset.search || opt.textContent.toLowerCase()).indexOf(q) !== -1;
                    opt.hidden = !hit;
                    if (hit) visible++;
                });
                group.hidden = visible === 0;
            });
            // One match left: pick it, so Enter/Tab moves on quickly
            var hits = Array.prototype.filter.call(select.querySelectorAll('option'), function (o) { return o.value && !o.hidden; });
            if (q && hits.length === 1) { select.value = hits[0].value; sync(); }
        }

        select.addEventListener('change', sync);
        if (search) search.addEventListener('input', filter);
        sync();
    }

    // A list of island checkboxes (a shop's extra islands): the search box hides the ones that don't match
    function setupChecklist(list) {
        var search = list.querySelector('[data-checklist-search]');
        if (!search) return;
        search.addEventListener('input', function () {
            var q = (search.value || '').trim().toLowerCase();
            list.querySelectorAll('[data-checklist-group]').forEach(function (group) {
                var visible = 0;
                group.querySelectorAll('[data-search]').forEach(function (row) {
                    var hit = !q || row.dataset.search.indexOf(q) !== -1;
                    row.hidden = !hit;
                    if (hit) visible++;
                });
                group.hidden = visible === 0;
            });
        });
    }

    // Shop coverage: extra islands and the whole atoll only apply when the shop doesn't deliver everywhere
    function setupCoverage(box) {
        var extra = box.querySelector('[data-coverage-extra]');
        function sync() {
            var checked = box.querySelector('[data-coverage-radio]:checked');
            if (extra) extra.classList.toggle('hidden', !checked || checked.value === 'all');
        }
        box.querySelectorAll('[data-coverage-radio]').forEach(function (r) { r.addEventListener('change', sync); });
        sync();
    }

    function init() {
        document.querySelectorAll('[data-island-picker]').forEach(setup);
        document.querySelectorAll('[data-island-checklist]').forEach(setupChecklist);
        document.querySelectorAll('[data-coverage-fields]').forEach(setupCoverage);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
