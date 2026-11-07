(() => {
    'use strict';
    const cents = value => {
        if (!/^\d+(?:\.\d{0,2})?$/.test(value)) return 0n;
        const [whole, fraction = ''] = value.split('.');
        return BigInt(whole) * 100n + BigInt(fraction.padEnd(2, '0'));
    };
    const decimal = value => `${value / 100n}.${(value % 100n).toString().padStart(2, '0')}`;
    document.querySelectorAll('[data-payment-form]').forEach(form => {
        const refresh = () => {
            const total = [...form.querySelectorAll('[data-allocation-amount]')].reduce((sum, field) => sum + cents(field.value || '0'), 0n);
            const output = form.querySelector('[data-allocation-total]');
            if (output) output.value = decimal(total);
            const single = form.querySelector('[data-single-allocation]');
            if (single) single.value = form.querySelector('[data-payment-amount]').value;
        };
        form.addEventListener('input', refresh);
        refresh();
    });
})();
