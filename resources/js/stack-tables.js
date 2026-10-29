// Mobile first (resources/css/app.css): on phones each data table row is shown as a card. This gives
// every cell its column name (data-label) from the table's header, also for rows added later
// (e.g. the variants editor). Plain DOM, no library.
(function () {
    function headers(table) {
        var row = table.tHead && table.tHead.rows[table.tHead.rows.length - 1];
        var names = [];
        if (!row) return names;
        Array.prototype.forEach.call(row.cells, function (th) {
            var text = (th.getAttribute('data-label') || th.textContent || '').replace(/\s+/g, ' ').trim();
            for (var i = 0; i < (th.colSpan || 1); i++) names.push(text);
        });
        return names;
    }

    function label(table) {
        if (table.hasAttribute('data-no-stack')) return;
        var names = headers(table);
        if (!names.length) return;
        Array.prototype.forEach.call(table.tBodies, function (body) {
            Array.prototype.forEach.call(body.rows, function (tr) {
                var col = 0;
                Array.prototype.forEach.call(tr.cells, function (cell) {
                    var name = names[col];
                    if (name && cell.colSpan === 1 && !cell.hasAttribute('data-label')) cell.setAttribute('data-label', name);
                    col += cell.colSpan || 1;
                });
            });
        });
    }

    function init() {
        document.querySelectorAll('main table').forEach(function (table) {
            label(table);
            if (window.MutationObserver) {
                new MutationObserver(function () { label(table); }).observe(table, { childList: true, subtree: false });
                Array.prototype.forEach.call(table.tBodies, function (body) {
                    new MutationObserver(function () { label(table); }).observe(body, { childList: true });
                });
            }
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
