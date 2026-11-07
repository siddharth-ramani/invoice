(() => {
    const t = window.bizT || ((key, values = {}) => key.replace(/:([a-z_]+)/g, (_, name) => values[name] ?? ':' + name));
    'use strict';
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const toggle = document.getElementById('toggleBtn');
    const closeButton = document.getElementById('sidebarClose');
    const main = document.querySelector('.app-main');
    const mobile = window.matchMedia('(max-width: 991px)');
    let previousFocus;
    const focusables = () => [...sidebar.querySelectorAll('a[href],button:not([disabled])')].filter(el => el.getClientRects().length);
    const closeMenu = (restoreFocus = true) => {
        sidebar.classList.remove('is-open');
        document.body.classList.remove('app-nav-open');
        overlay.hidden = true;
        main.inert = false;
        sidebar.inert = mobile.matches;
        sidebar.removeAttribute('role');
        sidebar.removeAttribute('aria-modal');
        toggle.setAttribute('aria-expanded', String(!mobile.matches && !document.documentElement.classList.contains('app-sidebar-collapsed')));
        if (restoreFocus && mobile.matches) (previousFocus || toggle).focus();
    };
    const openMenu = () => {
        previousFocus = toggle;
        sidebar.inert = false;
        sidebar.classList.add('is-open');
        sidebar.setAttribute('role', 'dialog');
        sidebar.setAttribute('aria-modal', 'true');
        overlay.hidden = false;
        document.body.classList.add('app-nav-open');
        main.inert = true;
        toggle.setAttribute('aria-expanded', 'true');
        closeButton.focus();
    };
    if (sidebar && toggle) {
        toggle.addEventListener('click', () => {
            if (mobile.matches) {
                sidebar.classList.contains('is-open') ? closeMenu() : openMenu();
            } else {
                const collapsed = document.documentElement.classList.toggle('app-sidebar-collapsed');
                toggle.setAttribute('aria-expanded', String(!collapsed));
                try { localStorage.setItem('bizpilot.sidebar.collapsed', String(collapsed)); } catch (_) { /* Optional persistence. */ }
            }
        });
        overlay.addEventListener('click', () => closeMenu());
        closeButton.addEventListener('click', () => closeMenu());
        mobile.addEventListener('change', () => closeMenu(false));
        document.addEventListener('keydown', event => {
            if (!mobile.matches || !sidebar.classList.contains('is-open')) return;
            if (event.key === 'Escape') { event.preventDefault(); closeMenu(); }
            if (event.key === 'Tab') {
                const items = focusables();
                const first = items[0], last = items[items.length - 1];
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
            }
        });
        closeMenu(false);
    }

    document.addEventListener('click', event => {
        document.querySelectorAll('.app-profile[open]').forEach(menu => {
            if (!menu.contains(event.target)) menu.open = false;
        });
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') document.querySelectorAll('.app-profile[open]').forEach(menu => {
            menu.open = false;
            menu.querySelector('summary').focus();
        });
    });

    const dialog = document.getElementById('confirmDialog');
    let pendingForm = null;
    document.addEventListener('submit', event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.dataset.confirm && form.dataset.confirmed !== 'true') {
            event.preventDefault();
            pendingForm = form;
            const planChange = form.dataset.confirmKind === 'plan';
            dialog.classList.toggle('is-neutral', planChange);
            dialog.querySelector('[data-confirm-delete-icon]').hidden = planChange;
            dialog.querySelector('[data-confirm-info-icon]').hidden = !planChange;
            dialog.querySelector('#confirm-title').textContent = planChange ? t('Confirm plan selection?') : t('Delete this record?');
            const accept = dialog.querySelector('[data-confirm-accept]');
            accept.textContent = planChange ? t('Confirm selection') : t('Delete record');
            accept.classList.toggle('btn-danger', !planChange);
            accept.classList.toggle('btn-primary', planChange);
            dialog.querySelector('#confirm-description').textContent = form.dataset.confirm;
            dialog.showModal();
            dialog.querySelector('[data-confirm-cancel]').focus();
            return;
        }
        if (form.method.toLowerCase() !== 'post' || form.dataset.download !== undefined) return;
        if (form.dataset.submitting === 'true') { event.preventDefault(); return; }
        if (event.submitter?.name) {
            const submittedAction = document.createElement('input');
            submittedAction.type = 'hidden';
            submittedAction.name = event.submitter.name;
            submittedAction.value = event.submitter.value;
            submittedAction.dataset.submittedAction = 'true';
            form.append(submittedAction);
        }
        form.dataset.submitting = 'true';
        form.setAttribute('aria-busy', 'true');
        form.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = true; });
    });
    dialog?.querySelector('[data-confirm-cancel]').addEventListener('click', () => dialog.close());
    dialog?.querySelector('[data-confirm-accept]').addEventListener('click', () => {
        const form = pendingForm;
        dialog.close();
        if (form) { form.dataset.confirmed = 'true'; form.requestSubmit(); }
    });
    dialog?.addEventListener('close', () => { pendingForm = null; });
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('form[data-submitting]').forEach(form => {
            delete form.dataset.submitting;
            delete form.dataset.confirmed;
            form.removeAttribute('aria-busy');
            form.querySelectorAll('[data-submitted-action]').forEach(field => field.remove());
            form.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = false; });
        });
    });

    if (window.jQuery?.fn.DataTable) {
        window.jQuery('.app-data-table').each(function () {
            const headers = [...this.querySelectorAll('thead th')].map(cell => cell.textContent.trim());
            const labelRows = () => this.querySelectorAll('tbody tr').forEach(row => {
                if (row.querySelector('td[colspan]')) return;
                [...row.cells].forEach((cell, index) => { cell.dataset.label = headers[index] || 'Actions'; });
            });
            labelRows();
            if (this.hasAttribute('data-server-table')) return;
            window.jQuery(this).DataTable({
                responsive: false, pageLength: 10, lengthChange: false, autoWidth: false, order: [],
                searching: !this.hasAttribute('data-no-search'), paging: !this.hasAttribute('data-no-search'), info: !this.hasAttribute('data-no-search'),
                columnDefs: [{ orderable: false, targets: headers.map((label, index) => (/^actions?$/i.test(label) || ['Action','Actions'].some(action => t(action) === label)) ? index : null).filter(index => index !== null) }],
                language: { search: '', searchPlaceholder: t('Search this list…'), emptyTable: t('No records to show.'), zeroRecords: t('No matching records. Try another search.'), info: t('Showing _START_–_END_ of _TOTAL_'), infoEmpty: t('No records'), paginate: { previous: t('Previous'), next: t('Next') } },
                drawCallback: labelRows,
            });
            this.closest('.dataTables_wrapper')?.querySelector('input[type="search"]')?.setAttribute('aria-label', 'Search this list');
        });
    }
    document.getElementById('form-feedback')?.focus();
})();
