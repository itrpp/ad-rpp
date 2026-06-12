/**
 * OU Register – client-side search + integration filters (GTW / e-phis / ยังไม่มี)
 */
(function () {
    const STORAGE_KEY = 'ouRegisterIntegrationFilters';

    function getState() {
        return {
            gtw: !!(document.getElementById('filterHasGtw')?.checked),
            ephis: !!(document.getElementById('filterHasEphis')?.checked),
            missing: !!(document.getElementById('filterHasNone')?.checked),
        };
    }

    function saveState() {
        try {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(getState()));
        } catch (e) {
            // ignore
        }
    }

    function restoreState() {
        const url = new URL(window.location.href);
        const hasUrl = url.searchParams.has('filter_gtw')
            || url.searchParams.has('filter_ephis')
            || url.searchParams.has('filter_missing');

        if (hasUrl) {
            const gtw = document.getElementById('filterHasGtw');
            const ephis = document.getElementById('filterHasEphis');
            const missing = document.getElementById('filterHasNone');
            if (gtw) gtw.checked = url.searchParams.get('filter_gtw') === '1';
            if (ephis) ephis.checked = url.searchParams.get('filter_ephis') === '1';
            if (missing) missing.checked = url.searchParams.get('filter_missing') === '1';
            saveState();
            return;
        }

        try {
            const raw = sessionStorage.getItem(STORAGE_KEY);
            if (!raw) return;
            const data = JSON.parse(raw);
            const gtw = document.getElementById('filterHasGtw');
            const ephis = document.getElementById('filterHasEphis');
            const missing = document.getElementById('filterHasNone');
            if (gtw) gtw.checked = !!data.gtw;
            if (ephis) ephis.checked = !!data.ephis;
            if (missing) missing.checked = !!data.missing;
        } catch (e) {
            // ignore
        }
    }

    function updatePaginationLinks() {
        const state = getState();
        document.querySelectorAll('.ou-register-pagination a.page-link[href]').forEach((link) => {
            try {
                const url = new URL(link.getAttribute('href'), window.location.origin);
                if (state.gtw) url.searchParams.set('filter_gtw', '1');
                else url.searchParams.delete('filter_gtw');
                if (state.ephis) url.searchParams.set('filter_ephis', '1');
                else url.searchParams.delete('filter_ephis');
                if (state.missing) url.searchParams.set('filter_missing', '1');
                else url.searchParams.delete('filter_missing');
                link.setAttribute('href', url.toString());
            } catch (e) {
                // ignore
            }
        });
    }

    function updateFilteredCount() {
        const container = document.querySelector('.ou-users[data-register-list="1"]');
        if (!container) return;

        const total = parseInt(container.getAttribute('data-total-count') || '0', 10);
        const rows = Array.from(document.querySelectorAll('.user-row'));
        const visible = rows.filter((r) => r.style.display !== 'none').length;
        const el = document.getElementById('filteredCount');
        if (!el) return;

        if (total === 0) {
            el.textContent = '0 คน';
        } else if (visible === rows.length) {
            el.textContent = total + ' คน';
        } else {
            el.textContent = visible + ' จาก ' + total + ' คน (ในหน้านี้)';
        }
    }

    function applyFilters() {
        const searchTerm = (document.getElementById('userSearch')?.value || '')
            .replace(/\s+/g, ' ')
            .trim()
            .toLowerCase();
        const state = getState();

        document.querySelectorAll('.user-row').forEach((row) => {
            const username = (row.dataset.username || '').toLowerCase();
            const displayName = (row.dataset.displayname || '').toLowerCase();
            const cn = (row.dataset.cn || '').toLowerCase();
            const department = (row.dataset.department || '').toLowerCase();
            const company = (row.dataset.company || '').toLowerCase();

            const globalMatch = !searchTerm
                || username.includes(searchTerm)
                || displayName.includes(searchTerm)
                || cn.includes(searchTerm)
                || department.includes(searchTerm)
                || company.includes(searchTerm);

            const hasGtw = row.dataset.hasGtw === '1';
            const hasEphis = row.dataset.hasEphis === '1';
            const hasNone = !hasGtw && !hasEphis;
            const gtwMatch = !state.gtw || hasGtw;
            const ephisMatch = !state.ephis || hasEphis;
            const missingMatch = !state.missing || hasNone;
            const integrationMatch = gtwMatch && ephisMatch && missingMatch;

            row.style.display = (globalMatch && integrationMatch) ? '' : 'none';
        });

        updateFilteredCount();
    }

    function onFilterChange() {
        saveState();
        updatePaginationLinks();
        applyFilters();
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (!document.querySelector('.ou-users[data-register-list="1"]')) {
            return;
        }

        restoreState();
        updatePaginationLinks();

        ['filterHasGtw', 'filterHasEphis', 'filterHasNone'].forEach((id) => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('change', onFilterChange);
        });

        const clearBtn = document.getElementById('clearIntegrationFilter');
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                ['filterHasGtw', 'filterHasEphis', 'filterHasNone'].forEach((id) => {
                    const el = document.getElementById(id);
                    if (el) el.checked = false;
                });
                onFilterChange();
            });
        }

        const userSearch = document.getElementById('userSearch');
        const searchButton = document.getElementById('searchButton');
        if (userSearch) userSearch.addEventListener('input', applyFilters);
        if (searchButton) searchButton.addEventListener('click', applyFilters);

        applyFilters();
    });
})();
