(() => {
    const t = window.bizT || ((key, values = {}) => key.replace(/:([a-z_]+)/g, (_, name) => values[name] ?? ':' + name));
    'use strict';
    document.addEventListener('click', async event => {
        const button = event.target.closest('[data-product-load], [data-product-clear]');
        if (!button) return;
        const picker = button.closest('[data-product-picker]');
        if (button.hasAttribute('data-product-clear')) {
            picker.querySelector('[data-product-id]').value = '';
            picker.querySelector('[data-product-result]').value = '';
            picker.querySelector('[data-product-feedback]').textContent = t('Manual item selected. Current description and amounts are retained.');
            picker.querySelector('[data-product-id]').dispatchEvent(new Event('input', {bubbles:true}));
            return;
        }
        button.disabled = true;
        const feedback = picker.querySelector('[data-product-feedback]');
        try {
            const url = new URL(picker.dataset.lookup, location.href);
            url.searchParams.set('q', picker.querySelector('[data-product-search]').value);
            const response = await fetch(url, {headers:{Accept:'application/json'},credentials:'same-origin'});
            if (!response.ok) throw new Error(t('Catalog could not be loaded. Try again.'));
            const data = await response.json();
            const select = picker.querySelector('[data-product-result]');
            select.replaceChildren(new Option(t('Choose a result to fill this item'), ''));
            data.items.forEach(item => {
                const option = new Option(`${item.name}${item.sku ? ' · ' + item.sku : ''} · ₹${item.selling_price} / ${item.unit}`, item.id);
                option.dataset.item = JSON.stringify(item);
                select.append(option);
            });
            feedback.textContent = data.items.length ? t('Choose a result. Search more specifically if needed (up to 50 results).') : t('No active catalog items match.');
        } catch (error) { feedback.textContent = error.message; }
        finally { button.disabled = false; }
    });
    document.addEventListener('change', event => {
        if (!event.target.matches('[data-product-result]')) return;
        const option = event.target.selectedOptions[0];
        if (!option?.dataset.item) return;
        const product = JSON.parse(option.dataset.item);
        const row = event.target.closest('[data-invoice-item]');
        const set = (name,value) => { const field=row.querySelector(`[name$="[${name}]"]`);if(field){field.value=value??'';field.dispatchEvent(new Event('input',{bubbles:true}));} };
        set('product_id',product.id);set('item_name',product.name);set('hsn_sac',product.hsn_sac);set('rate',product.selling_price);set('cgst_percent',product.cgst_percent);set('sgst_percent',product.sgst_percent);
        row.querySelector('[data-product-feedback]').textContent = t('Selected :name. Unit: :unit. Current rate and tax copied; you can review the invoice draft.',{name:product.name,unit:product.unit});
    });
})();
