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

    function init() { document.querySelectorAll('[data-island-picker]').forEach(setup); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
