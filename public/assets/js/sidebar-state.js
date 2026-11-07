// Remember only this application's navigation preference; no account data is stored.
try {
    if (localStorage.getItem('bizpilot.sidebar.collapsed') === 'true') {
        document.documentElement.classList.add('app-sidebar-collapsed');
    }
} catch (_) { /* Navigation still works when browser storage is unavailable. */ }
