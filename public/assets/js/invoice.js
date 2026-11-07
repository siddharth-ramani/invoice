(() => {
    const t = window.bizT || ((key, values = {}) => key.replace(/:([a-z_]+)/g, (_, name) => values[name] ?? ':' + name));
    'use strict';
    const form = document.getElementById('invoiceForm');
    if (!form) return;
    const items = document.getElementById('invoiceItems');
    const feedback = document.getElementById('invoiceItemFeedback');
    const units = input => {
        const value = String(input?.value || '0');
        if (!/^\d+(?:\.\d{0,2})?$/.test(value)) return 0n;
        const [whole, fraction = ''] = value.split('.');
        return BigInt(whole) * 100n + BigInt(fraction.padEnd(2, '0'));
    };
    const decimal = scaled => {
        const absolute = scaled < 0n ? -scaled : scaled;
        const cents = (absolute + 500000n) / 1000000n;
        return (scaled < 0n && cents !== 0n ? '-' : '') + (cents / 100n).toString() + '.' + (cents % 100n).toString().padStart(2, '0');
    };
    const field = name => form.elements.namedItem(name);
    const rows = () => [...items.querySelectorAll('[data-invoice-item]')];
    const reindex = () => rows().forEach((row, index) => {
        row.querySelector('legend').textContent = t('Invoice item :count', {count: index + 1});
        row.querySelectorAll('[aria-describedby]').forEach(input => { input.setAttribute('aria-describedby', input.getAttribute('aria-describedby').replaceAll(/items-[^-]+-/g, `items-${index}-`)); });
        row.querySelector('[data-item-number]').textContent = String(index + 1);
        row.querySelector('[data-item-serial]').value = String(index + 1);
        row.querySelectorAll('[name]').forEach(input => { input.name = input.name.replace(/items\[[^\]]+\]/, `items[${index}]`); });
        row.querySelectorAll('[id]').forEach(input => { input.id = input.id.replace(/^items-[^-]+-/, `items-${index}-`).replace(/^product-(search|result)-.+$/, `product-$1-${index}`); });
        row.querySelectorAll('[for]').forEach(label => { label.htmlFor = label.htmlFor.replace(/^items-[^-]+-/, `items-${index}-`).replace(/^product-(search|result)-.+$/, `product-$1-${index}`); });
        row.querySelector('[data-remove-item]').setAttribute('aria-label', t('Remove item :count', {count: index + 1}));
    });
    const recalculate = () => {
        let taxable = 0n, cgst = 0n, sgst = 0n;
        rows().forEach(row => {
            const input = key => row.querySelector(`[name$="[${key}]"]`);
            const base4 = units(input('qty')) * units(input('rate'));
            const base = base4 * 10000n;
            const c = base4 * units(input('cgst_percent'));
            const s = base4 * units(input('sgst_percent'));
            taxable += base; cgst += c; sgst += s;
            input('line_total').value = decimal(base + c + s);
        });
        // Same full-base GST, percentage discount and rounding as the server.
        const discount = (taxable / 10000n) * units(field('discount_percent'));
        const beforeRound = taxable - discount + cgst + sgst;
        const total = ((beforeRound + 50000000n) / 100000000n) * 100000000n;
        const values = {taxable_amount: taxable, discount, cgst_total: cgst, sgst_total: sgst, round_off: total - beforeRound, grand_total: total};
        Object.entries(values).forEach(([key, value]) => { field(key).value = decimal(value); });
    };
    document.getElementById('addItem').addEventListener('click', () => {
        const markup = document.getElementById('invoiceItemTemplate').innerHTML.replaceAll('__INDEX__', String(rows().length));
        items.insertAdjacentHTML('beforeend', markup);
        reindex(); recalculate();
        rows().at(-1).querySelector('input:not([type="hidden"])').focus();
        feedback.textContent = t('Item added. :count items in this invoice.', {count: rows().length});
    });
    items.addEventListener('click', event => {
        const button = event.target.closest('[data-remove-item]');
        if (!button) return;
        if (rows().length === 1) { feedback.textContent = t('Keep at least one item in the invoice.'); return; }
        button.closest('[data-invoice-item]').remove();
        reindex(); recalculate();
        feedback.textContent = t('Item removed. :count items remain.', {count: rows().length});
        document.getElementById('addItem').focus();
    });
    form.addEventListener('input', event => {
        if (/\[(qty|rate|cgst_percent|sgst_percent)\]$/.test(event.target.name) || event.target.name === 'discount_percent') recalculate();
    });
    reindex();
    if (form.dataset.recalculate === 'true') recalculate();
})();
