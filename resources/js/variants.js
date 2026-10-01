// Seller product form: option types -> generated variant rows (plain JS, no framework).
// Markup: see resources/views/seller/products/_variants.blade.php

function slug(text) {
    return String(text).trim().toUpperCase().replace(/[^A-Z0-9]+/g, '-').replace(/^-|-$/g, '');
}

function cartesian(lists) {
    return lists.reduce((acc, list) => acc.flatMap((combo) => list.map((v) => combo.concat([v]))), [[]]);
}

function initEditor(editor) {
    const toggle = editor.querySelector('[data-has-variants]');
    const section = editor.querySelector('[data-variants-section]');
    const rowsBody = editor.querySelector('[data-variant-rows]');
    const template = editor.querySelector('[data-variant-template]');
    const empty = editor.querySelector('[data-no-variants]');
    const generate = editor.querySelector('[data-generate-variants]');
    const productStock = document.querySelector('[data-product-stock]');
    const stockInput = productStock ? productStock.querySelector('input') : null;
    const stockNote = productStock ? productStock.querySelector('[data-stock-derived]') : null;
    const productSku = document.getElementById(rowsBody.dataset.productSkuInput || 'sku');

    function applyToggle() {
        const on = toggle.checked;
        section.classList.toggle('hidden', !on);
        if (productStock) {
            productStock.classList.toggle('hidden', on);
            if (stockInput) stockInput.disabled = on;
            if (stockNote) stockNote.classList.toggle('hidden', !on);
        }
    }

    function refreshEmpty() {
        const count = rowsBody.querySelectorAll('[data-variant-row]').length;
        if (empty) empty.classList.toggle('hidden', count > 0);
    }

    function nextIndex() {
        let max = -1;
        rowsBody.querySelectorAll('[name^="variants["]').forEach((input) => {
            const m = input.name.match(/^variants\[(\d+)\]/);
            if (m) max = Math.max(max, parseInt(m[1], 10));
        });
        return max + 1;
    }

    function existingCombos() {
        const combos = new Set();
        rowsBody.querySelectorAll('[data-variant-row]').forEach((row) => {
            combos.add(normalise(row.dataset.attributes));
        });
        return combos;
    }

    function normalise(json) {
        try {
            const obj = JSON.parse(json || '{}');
            return JSON.stringify(Object.keys(obj).map((k) => [k.trim().toLowerCase(), String(obj[k]).trim().toLowerCase()]));
        } catch (e) {
            return '';
        }
    }

    function readOptions() {
        const options = [];
        editor.querySelectorAll('[data-option-types] > div').forEach((box) => {
            const name = box.querySelector('[data-option-name]').value.trim();
            const values = box.querySelector('[data-option-values]').value.split(',').map((v) => v.trim()).filter(Boolean);
            if (name && values.length) options.push({ name, values: [...new Set(values)] });
        });
        return options.slice(0, 3);
    }

    function addRow(attributes) {
        const index = nextIndex();
        const html = template.innerHTML.replace(/__I__/g, index);
        const wrapper = document.createElement('tbody');
        wrapper.innerHTML = html.trim();
        const row = wrapper.querySelector('tr');
        const json = JSON.stringify(attributes);
        row.dataset.attributes = json;
        row.querySelector('[data-variant-attributes]').value = json;
        row.querySelector('[data-variant-label]').textContent = Object.keys(attributes).map((k) => k + ': ' + attributes[k]).join(' / ');
        const base = productSku && productSku.value ? slug(productSku.value) : 'VAR';
        row.querySelector('input[name$="[sku]"]').value = base + '-' + Object.values(attributes).map(slug).join('-');
        rowsBody.appendChild(row);
    }

    generate.addEventListener('click', () => {
        const options = readOptions();
        if (!options.length) return;
        const combos = cartesian(options.map((o) => o.values));
        const have = existingCombos();
        combos.forEach((values) => {
            const attributes = {};
            options.forEach((o, i) => { attributes[o.name] = values[i]; });
            if (!have.has(normalise(JSON.stringify(attributes)))) addRow(attributes);
        });
        refreshEmpty();
    });

    rowsBody.addEventListener('click', (e) => {
        const del = e.target.closest('[data-variant-delete]');
        if (del) {
            del.closest('[data-variant-row]').remove();
            refreshEmpty();
        }
    });

    rowsBody.addEventListener('change', (e) => {
        if (e.target.matches('[data-variant-remove]')) {
            e.target.closest('[data-variant-row]').classList.toggle('opacity-50', e.target.checked);
        }
    });

    toggle.addEventListener('change', applyToggle);
    applyToggle();
    refreshEmpty();
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-variant-editor]').forEach(initEditor);
});
