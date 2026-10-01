// Product page: option buttons choose a variant; price, stock, SKU, image and the form follow.
// Markup: the buy form in resources/views/products/show.blade.php ([data-variant-picker]).
// Without JS the <select name="product_variant_id"> does the same job.

function initPicker(form) {
    let variants;
    try {
        variants = JSON.parse(form.dataset.variants || '[]');
    } catch (e) {
        return;
    }
    if (!variants.length) return;

    const select = form.querySelector('[data-variant-select]');
    const selectLabel = form.querySelector('[data-variant-select-label]');
    const groups = Array.from(form.querySelectorAll('[data-option-group]'));
    const unavailable = form.querySelector('[data-variant-unavailable]');
    const submit = form.querySelector('[data-add-to-cart]');
    const qty = form.querySelector('input[name="quantity"]');
    const priceBox = document.querySelector('[data-variant-price] .price');
    const stockBox = document.querySelector('[data-variant-stock]');
    const skuBox = document.querySelector('[data-variant-sku]');
    const mainImage = document.querySelector('[data-gallery-main]');
    const alertSelect = document.querySelector('[data-alert-variant]');
    const chosen = {};

    // Buttons take over from the select
    if (select) select.classList.add('hidden');
    if (selectLabel) selectLabel.classList.add('hidden');
    groups.forEach((g) => g.querySelector('[data-option-buttons]').classList.remove('hidden'));

    function matches(variant, selection) {
        return Object.keys(selection).every((k) => String(variant.attributes[k]) === String(selection[k]));
    }

    function current() {
        if (Object.keys(chosen).length < groups.length) return null;
        return variants.find((v) => matches(v, chosen)) || null;
    }

    function refreshButtons() {
        groups.forEach((group) => {
            const option = group.dataset.optionGroup;
            group.querySelectorAll('[data-option-value]').forEach((button) => {
                const value = button.dataset.optionValue;
                // Is there an in-stock variant with this value and everything else already chosen?
                const others = Object.assign({}, chosen);
                delete others[option];
                others[option] = value;
                const possible = variants.some((v) => v.stock > 0 && matches(v, others));
                button.disabled = !possible;
                button.setAttribute('aria-pressed', chosen[option] === value ? 'true' : 'false');
            });
            const label = group.querySelector('[data-option-chosen]');
            if (label) label.textContent = chosen[option] || '';
        });
    }

    function setStock(stock) {
        if (!stockBox) return;
        const p = stockBox.querySelector('p');
        if (!p) return;
        p.classList.remove('text-danger', 'text-sun-ink', 'text-success');
        if (stock <= 0) {
            p.textContent = stockBox.dataset.textOut;
            p.classList.add('text-danger');
        } else if (stock <= 5) {
            p.textContent = stockBox.dataset.textLow.replace('#', stock);
            p.classList.add('text-sun-ink');
        } else {
            p.textContent = stockBox.dataset.textIn;
            p.classList.add('text-success');
        }
    }

    function apply() {
        refreshButtons();
        const variant = current();
        const complete = Object.keys(chosen).length === groups.length;
        if (select) select.value = variant ? String(variant.id) : '';
        if (unavailable) unavailable.classList.toggle('hidden', !(complete && !variant));
        if (submit) submit.disabled = !variant || variant.stock <= 0;
        if (!variant) return;

        if (priceBox) {
            priceBox.textContent = variant.price_text;
        }
        setStock(variant.stock);
        if (qty) {
            qty.max = Math.max(1, variant.stock);
            if (parseInt(qty.value, 10) > variant.stock) qty.value = Math.max(1, variant.stock);
        }
        if (skuBox && variant.sku) skuBox.textContent = variant.sku;
        if (mainImage && variant.image) {
            mainImage.src = variant.image;
            if (variant.srcset) mainImage.srcset = variant.srcset; else mainImage.removeAttribute('srcset');
        }
        if (alertSelect) alertSelect.value = String(variant.id);
    }

    groups.forEach((group) => {
        group.addEventListener('click', (e) => {
            const button = e.target.closest('[data-option-value]');
            if (!button || button.disabled) return;
            const option = group.dataset.optionGroup;
            if (chosen[option] === button.dataset.optionValue) delete chosen[option];
            else chosen[option] = button.dataset.optionValue;
            apply();
        });
    });

    // One option type with a single in-stock value: pick it for the shopper
    if (groups.length === 1) {
        const inStock = variants.filter((v) => v.stock > 0);
        if (inStock.length === 1) {
            chosen[groups[0].dataset.optionGroup] = inStock[0].attributes[groups[0].dataset.optionGroup];
        }
    }
    apply();
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-variant-picker]').forEach(initPicker);
});
