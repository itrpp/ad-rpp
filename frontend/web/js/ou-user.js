/**
 * OU User Page JavaScript Module
 * Handles user management functionality including search, pagination, and modals
 */

class OuUserManager {
    constructor() {
        this.PAGE_SIZE = 15;
        this.sortKey = 'row';
        this.sortDir = 'asc';
        this.toggleStatusDebounce = new Map();
        this.integrationFilterStorageKey = 'ouUserIntegrationFilters';
        this.listStateStorageKey = 'ouUserListReturnUrl';
        
        this.initializeElements();
        this.restoreIntegrationFilters();
        this.bindEvents();
        this.saveListStateToStorage();
        this.initializePage();
    }

    saveListStateToStorage() {
        const container = document.querySelector('.ou-users[data-server-pagination="1"]');
        if (!container) {
            return;
        }
        try {
            sessionStorage.setItem(this.listStateStorageKey, window.location.pathname + window.location.search);
        } catch (err) {
            // ignore storage errors
        }
    }

    appendReturnUrlToActionLink(link) {
        if (!link || !link.getAttribute('href')) {
            return;
        }
        try {
            const href = link.getAttribute('href');
            if (!/\/ldapuser(%2F|\/)update|\/ldapuser(%2F|\/)move/.test(href)) {
                return;
            }
            const url = new URL(link.href, window.location.origin);
            if (url.searchParams.has('returnUrl')) {
                return;
            }
            const returnUrl = window.location.pathname + window.location.search;
            url.searchParams.set('returnUrl', returnUrl);
            link.setAttribute('href', url.pathname + url.search);
        } catch (err) {
            // ignore malformed URLs
        }
    }

    initializeElements() {
        // Search and filter elements
        this.userSearch = document.getElementById('userSearch');
        this.searchButton = document.getElementById('searchButton');
        this.clearUserSearch = document.getElementById('clearUserSearch');
        this.ouFilter = document.getElementById('ouFilter');
        this.clearOuFilter = document.getElementById('clearOuFilter');
        this.ouPathInput = document.getElementById('ouPath');
        this.filterHasGtw = document.getElementById('filterHasGtw');
        this.filterHasEphis = document.getElementById('filterHasEphis');
        this.filterHasNone = document.getElementById('filterHasNone');
        this.clearIntegrationFilter = document.getElementById('clearIntegrationFilter');
        this.filterGtwHidden = document.getElementById('filterGtwHidden');
        this.filterEphisHidden = document.getElementById('filterEphisHidden');
        this.filterMissingHidden = document.getElementById('filterMissingHidden');
        
        // Pagination elements
        this.paginationInfo = document.getElementById('paginationInfo');
        this.paginationButtons = document.getElementById('paginationButtons');
        
        // Modal elements
        this.viewButtons = document.querySelectorAll('.view-user');
        this.updateButtons = document.querySelectorAll('.btn-primary[data-toggle="modal"]');
        this.updateForm = document.getElementById('update-user-form');
        
        // Filter inputs
        this.filterInputs = ['filterUsername', 'filterCn', 'filterDepartment', 'filterTitle', 'filterStatus']
            .map(id => document.getElementById(id)).filter(Boolean);
    }

    bindEvents() {
        // Search events
        if (this.userSearch) {
            this.userSearch.addEventListener('input', () => {
                // เมื่อใช้ server-side pagination ให้รอให้ผู้ใช้กด Enter หรือปุ่มค้นหา
                const container = document.querySelector('.ou-users');
                const serverPagination = container && container.getAttribute('data-server-pagination') === '1';
                if (serverPagination) {
                    return;
                }
                this.applySearchAndRender(1);
            });
        }
        
        if (this.searchButton) {
            this.searchButton.addEventListener('click', (e) => {
                const serverPagination = document.querySelector('.ou-users')?.getAttribute('data-server-pagination') === '1';
                if (serverPagination) {
                    return;
                }
                e.preventDefault();
                this.applySearchAndRender(1);
            });
        }
        
        if (this.clearUserSearch) {
            this.clearUserSearch.addEventListener('click', () => {
                this.userSearch.value = '';
                const form = document.getElementById('userSearchForm');
                if (form && document.querySelector('.ou-users')?.getAttribute('data-server-pagination') === '1') {
                    form.submit();
                } else {
                    this.applySearchAndRender(1);
                }
                this.userSearch.focus();
            });
        }
        
        // OU filter events
        if (this.ouFilter) {
            this.ouFilter.addEventListener('change', () => {
                const container = document.querySelector('.ou-users');
                const serverPagination = container && container.getAttribute('data-server-pagination') === '1';
                if (serverPagination) {
                    const form = document.getElementById('userSearchForm');
                    if (this.ouPathInput) {
                        const selected = this.ouFilter.options[this.ouFilter.selectedIndex];
                        const path = selected ? (selected.getAttribute('data-path') || selected.value) : '';
                        this.ouPathInput.value = path;
                    }
                    if (form) {
                        this.syncIntegrationFilterHiddenInputs();
                        form.submit();
                        return;
                    }
                }
                this.applySearchAndRender(1);
            });
        }
        
        if (this.clearOuFilter) {
            this.clearOuFilter.addEventListener('click', () => {
                this.ouFilter.value = '';
                if (this.ouPathInput) {
                    this.ouPathInput.value = '';
                }
                const container = document.querySelector('.ou-users');
                const serverPagination = container && container.getAttribute('data-server-pagination') === '1';
                if (serverPagination) {
                    const form = document.getElementById('userSearchForm');
                    if (form) {
                        this.syncIntegrationFilterHiddenInputs();
                        form.submit();
                        return;
                    }
                }
                this.applySearchAndRender(1);
            });
        }

        const userSearchForm = document.getElementById('userSearchForm');
        if (userSearchForm) {
            userSearchForm.addEventListener('submit', () => {
                this.syncIntegrationFilterHiddenInputs();
            });
        }

        [this.filterHasGtw, this.filterHasEphis, this.filterHasNone].forEach((el) => {
            if (el) {
                el.addEventListener('change', () => this.onIntegrationFilterChange());
            }
        });

        if (this.clearIntegrationFilter) {
            this.clearIntegrationFilter.addEventListener('click', () => {
                if (this.filterHasGtw) {
                    this.filterHasGtw.checked = false;
                }
                if (this.filterHasEphis) {
                    this.filterHasEphis.checked = false;
                }
                if (this.filterHasNone) {
                    this.filterHasNone.checked = false;
                }
                this.onIntegrationFilterChange();
            });
        }

        document.addEventListener('click', (e) => {
            const container = document.querySelector('.ou-users[data-server-pagination="1"]');
            if (!container) {
                return;
            }
            const link = e.target.closest('a.ou-user-action-btn');
            if (!link) {
                return;
            }
            this.saveListStateToStorage();
            this.appendReturnUrlToActionLink(link);
        }, true);
        
        // Filter input events
        this.filterInputs.forEach(el => {
            const evt = el.tagName === 'SELECT' ? 'change' : 'input';
            el.addEventListener(evt, () => this.applySearchAndRender(1));
        });
        
        // Sorting events
        document.querySelectorAll('th.sortable').forEach(th => {
            th.addEventListener('click', () => this.handleSort(th));
        });
        
        // Modal events
        this.bindModalEvents();
        
        // Server-side pagination: ensure link clicks navigate (in case something blocks default)
        const paginationButtonsEl = document.getElementById('paginationButtons');
        if (paginationButtonsEl && document.querySelector('.ou-users')?.getAttribute('data-server-pagination') === '1') {
            paginationButtonsEl.addEventListener('click', (e) => {
                const link = e.target.closest('a.page-link[href]');
                if (link && link.getAttribute('href') && link.getAttribute('href') !== '#') {
                    e.preventDefault();
                    window.location.href = this.appendIntegrationFiltersToUrl(link.getAttribute('href'));
                }
            });
        }

        // Toggle status events - handle both button clicks and switch toggles
        document.addEventListener('click', (e) => {
            if (e.target.closest('.toggle-status-btn')) {
                this.handleToggleStatus(e);
            } else if (e.target.closest('.toggle-status-switch')) {
                this.handleToggleStatusSwitch(e);
            }
        });
        
        // Also handle change event for switches
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('toggle-status-switch')) {
                this.handleToggleStatusSwitch(e);
            }
        });
        
        // Form submission events
        if (this.updateForm) {
            this.updateForm.addEventListener('submit', (e) => this.handleFormSubmission(e));
        }
    }

    initializePage() {
        const container = document.querySelector('.ou-users');
        const serverPagination = container && container.getAttribute('data-server-pagination') === '1';
        if (serverPagination) {
            return;
        }
        this.applySearchAndRender(1);
    }

    getIntegrationFilterState() {
        return {
            gtw: !!(this.filterHasGtw && this.filterHasGtw.checked),
            ephis: !!(this.filterHasEphis && this.filterHasEphis.checked),
            missing: !!(this.filterHasNone && this.filterHasNone.checked),
        };
    }

    hasActiveIntegrationFilters() {
        const state = this.getIntegrationFilterState();
        return state.gtw || state.ephis || state.missing;
    }

    saveIntegrationFilters() {
        const state = this.getIntegrationFilterState();
        try {
            sessionStorage.setItem(this.integrationFilterStorageKey, JSON.stringify(state));
        } catch (err) {
            // ignore storage errors
        }
    }

    restoreIntegrationFilters() {
        const url = new URL(window.location.href);
        const hasUrlGtw = url.searchParams.has('filter_gtw');
        const hasUrlEphis = url.searchParams.has('filter_ephis');
        const hasUrlMissing = url.searchParams.has('filter_missing');

        if (hasUrlGtw || hasUrlEphis || hasUrlMissing) {
            if (this.filterHasGtw) {
                this.filterHasGtw.checked = url.searchParams.get('filter_gtw') === '1';
            }
            if (this.filterHasEphis) {
                this.filterHasEphis.checked = url.searchParams.get('filter_ephis') === '1';
            }
            if (this.filterHasNone) {
                this.filterHasNone.checked = url.searchParams.get('filter_missing') === '1';
            }
            this.syncIntegrationFilterHiddenInputs();
            this.saveIntegrationFilters();
            return;
        }

        try {
            const raw = sessionStorage.getItem(this.integrationFilterStorageKey);
            if (!raw) {
                return;
            }
            const data = JSON.parse(raw);
            const hasActive = !!(data.gtw || data.ephis || data.missing);
            if (!hasActive) {
                return;
            }

            url.searchParams.set('page', '1');
            if (data.gtw) {
                url.searchParams.set('filter_gtw', '1');
            } else {
                url.searchParams.delete('filter_gtw');
            }
            if (data.ephis) {
                url.searchParams.set('filter_ephis', '1');
            } else {
                url.searchParams.delete('filter_ephis');
            }
            if (data.missing) {
                url.searchParams.set('filter_missing', '1');
            } else {
                url.searchParams.delete('filter_missing');
            }
            window.location.replace(url.pathname + (url.searchParams.toString() ? '?' + url.searchParams.toString() : ''));
        } catch (err) {
            // ignore storage errors
        }
    }

    navigateWithIntegrationFilters(page = 1) {
        const url = new URL(window.location.href);
        const state = this.getIntegrationFilterState();

        url.searchParams.set('page', String(page));
        if (state.gtw) {
            url.searchParams.set('filter_gtw', '1');
        } else {
            url.searchParams.delete('filter_gtw');
        }
        if (state.ephis) {
            url.searchParams.set('filter_ephis', '1');
        } else {
            url.searchParams.delete('filter_ephis');
        }
        if (state.missing) {
            url.searchParams.set('filter_missing', '1');
        } else {
            url.searchParams.delete('filter_missing');
        }

        window.location.href = url.pathname + (url.searchParams.toString() ? '?' + url.searchParams.toString() : '');
    }

    getSavedListStateUrl() {
        try {
            const saved = sessionStorage.getItem(this.listStateStorageKey);
            return saved || '';
        } catch (err) {
            return '';
        }
    }

    syncIntegrationFilterHiddenInputs() {
        const state = this.getIntegrationFilterState();
        if (this.filterGtwHidden) {
            this.filterGtwHidden.value = state.gtw ? '1' : '';
            this.filterGtwHidden.disabled = !state.gtw;
        }
        if (this.filterEphisHidden) {
            this.filterEphisHidden.value = state.ephis ? '1' : '';
            this.filterEphisHidden.disabled = !state.ephis;
        }
        if (this.filterMissingHidden) {
            this.filterMissingHidden.value = state.missing ? '1' : '';
            this.filterMissingHidden.disabled = !state.missing;
        }
    }

    updateIntegrationFilterQuery() {
        const url = new URL(window.location.href);
        const state = this.getIntegrationFilterState();
        if (state.gtw) {
            url.searchParams.set('filter_gtw', '1');
        } else {
            url.searchParams.delete('filter_gtw');
        }
        if (state.ephis) {
            url.searchParams.set('filter_ephis', '1');
        } else {
            url.searchParams.delete('filter_ephis');
        }
        if (state.missing) {
            url.searchParams.set('filter_missing', '1');
        } else {
            url.searchParams.delete('filter_missing');
        }
        const nextUrl = url.pathname + (url.searchParams.toString() ? '?' + url.searchParams.toString() : '');
        window.history.replaceState({}, '', nextUrl);
    }

    appendIntegrationFiltersToUrl(href) {
        try {
            const url = new URL(href, window.location.origin);
            const state = this.getIntegrationFilterState();
            if (state.gtw) {
                url.searchParams.set('filter_gtw', '1');
            } else {
                url.searchParams.delete('filter_gtw');
            }
            if (state.ephis) {
                url.searchParams.set('filter_ephis', '1');
            } else {
                url.searchParams.delete('filter_ephis');
            }
            if (state.missing) {
                url.searchParams.set('filter_missing', '1');
            } else {
                url.searchParams.delete('filter_missing');
            }
            return url.toString();
        } catch (err) {
            return href;
        }
    }

    updatePaginationLinkHrefs() {
        document.querySelectorAll('#paginationButtons a.page-link[href]').forEach((link) => {
            const href = link.getAttribute('href');
            if (!href || href === '#') {
                return;
            }
            link.setAttribute('href', this.appendIntegrationFiltersToUrl(href));
        });
    }

    onIntegrationFilterChange() {
        this.syncIntegrationFilterHiddenInputs();
        this.saveIntegrationFilters();

        const container = document.querySelector('.ou-users');
        const serverPagination = container && container.getAttribute('data-server-pagination') === '1';
        if (serverPagination) {
            this.navigateWithIntegrationFilters(1);
            return;
        }

        this.updateIntegrationFilterQuery();
        this.updatePaginationLinkHrefs();
        this.applySearchAndRender(1);
    }

    showAllMatchedRows() {
        const allRows = Array.from(document.querySelectorAll('.user-row'));
        allRows.forEach(r => {
            r.style.display = (r.dataset.matches !== '0') ? '' : 'none';
        });
        this.updateClientFilteredCount();
    }

    updateClientFilteredCount() {
        const container = document.querySelector('.ou-users');
        if (!container || container.getAttribute('data-server-pagination') !== '1') return;
        const total = parseInt(container.getAttribute('data-total-count') || '0', 10);
        const allRows = Array.from(document.querySelectorAll('.user-row'));
        const visible = allRows.filter(r => r.style.display !== 'none').length;
        const el = document.getElementById('filteredCount');
        if (el) {
            if (total === 0) {
                el.textContent = '0 คน';
            } else if (visible === allRows.length) {
                el.textContent = total + ' คน';
            } else {
                el.textContent = visible + ' จาก ' + total + ' คน (ในหน้านี้)';
            }
            el.setAttribute('data-total', String(total));
        }
    }

    // Search and Filter Methods
    applySearch() {
        const searchTerm = (this.userSearch?.value || '').replace(/\s+/g, ' ').trim().toLowerCase();
        const selectedOu = (this.ouFilter?.value || '').trim().toLowerCase();
        const fUsernameRaw = (document.getElementById('filterUsername')?.value || '');
        const fCnRaw = (document.getElementById('filterCn')?.value || '');
        const fDeptRaw = (document.getElementById('filterDepartment')?.value || '');
        const fTitleRaw = (document.getElementById('filterTitle')?.value || '');
        const fStatusRaw = (document.getElementById('filterStatus')?.value || '');
        const filterGtw = !!(this.filterHasGtw && this.filterHasGtw.checked);
        const filterEphis = !!(this.filterHasEphis && this.filterHasEphis.checked);
        const filterMissing = !!(this.filterHasNone && this.filterHasNone.checked);
        
        const norm = (s) => String(s).replace(/\s+/g, ' ').trim().toLowerCase();

        const fUsername = norm(fUsernameRaw);
        const fCn = norm(fCnRaw);
        const fDept = norm(fDeptRaw);
        const fTitle = norm(fTitleRaw);
        const fStatus = norm(fStatusRaw);

        const container = document.querySelector('.ou-users');
        const serverPagination = container && container.getAttribute('data-server-pagination') === '1';

        const allRows = Array.from(document.querySelectorAll('.user-row'));
        
        allRows.forEach(row => {
            const username = norm(row.dataset.username || '');
            const cn = norm(row.dataset.cn || '');
            const displayName = norm(row.dataset.displayname || '');
            const department = norm(row.dataset.department || '');
            const title = norm(row.dataset.title || '');
            const ouDn = norm(row.getAttribute('data-ou') || '');
            const ouName = norm(row.dataset.ouname || '');
            const ouPath = norm(row.dataset.oupath || '');
            const email = norm(row.dataset.email || '');
            const postalcode = norm(row.dataset.postalcode || '');
            const postalDigits = postalcode.replace(/\D/g, '');
            const searchDigits = searchTerm.replace(/\D/g, '');
            const rowStatus = norm(row.dataset.status || '');

            const globalMatch = !searchTerm || username.includes(searchTerm) ||
                            cn.includes(searchTerm) ||
                            displayName.includes(searchTerm) ||
                            department.includes(searchTerm) ||
                            title.includes(searchTerm) ||
                            email.includes(searchTerm) ||
                            postalcode.includes(searchTerm) ||
                            (searchDigits !== '' && postalDigits.includes(searchDigits)) ||
                            ouDn.includes(searchTerm) ||
                            ouName.includes(searchTerm) ||
                            ouPath.includes(searchTerm);

            const ouMatch = !selectedOu || norm(ouPath) === norm(selectedOu) || norm(ouPath).indexOf(norm(selectedOu)) >= 0;

            const colMatch = (!fUsername || username.includes(fUsername) || cn.includes(fUsername)) &&
                             (!fCn || cn.includes(fCn)) &&
                             (!fDept || department.includes(fDept)) &&
                             (!fTitle || title.includes(fTitle)) &&
                             (!fStatus || rowStatus === fStatus);

            const hasGtw = row.dataset.hasGtw === '1';
            const hasEphis = row.dataset.hasEphis === '1';
            const hasNone = !hasGtw && !hasEphis;
            const gtwMatch = !filterGtw || hasGtw;
            const ephisMatch = !filterEphis || hasEphis;
            const missingMatch = !filterMissing || hasNone;
            const integrationMatch = serverPagination ? true : (gtwMatch && ephisMatch && missingMatch);

            row.dataset.matches = (globalMatch && colMatch && ouMatch && integrationMatch) ? '1' : '0';
        });
    }

    // Sorting Methods
    handleSort(th) {
        const key = th.getAttribute('data-sort-key');
        if (!key) return;
        
        if (this.sortKey === key) {
            this.sortDir = (this.sortDir === 'asc') ? 'desc' : 'asc';
        } else {
            this.sortKey = key;
            this.sortDir = 'asc';
        }
        
        this.updateSortIndicators(th);
        this.applySearchAndRender(1);
    }

    updateSortIndicators(currentTh) {
        document.querySelectorAll('th.sortable').forEach(h => h.setAttribute('aria-sort', 'none'));
        currentTh.setAttribute('aria-sort', this.sortDir);
        
        document.querySelectorAll('th.sortable .sort-icon i').forEach(icon => {
            icon.className = 'fas fa-sort';
        });
        
        const currentIcon = currentTh.querySelector('.sort-icon i');
        if (currentIcon) {
            currentIcon.className = this.sortDir === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down';
        }
    }

    sortRows(rows) {
        const getVal = (row) => {
            switch (this.sortKey) {
                case 'row': 
                    return Number(row.dataset.rowindex || row.querySelector('td.col-row-no')?.textContent) || 0;
                case 'username': 
                    return (row.dataset.username || '').toLowerCase().trim();
                case 'cn':
                    return (row.dataset.cn || '').toLowerCase().trim();
                case 'department': 
                    return (row.dataset.department || '').toLowerCase().trim();
                case 'title': 
                    return (row.dataset.title || '').toLowerCase().trim();
                case 'status': 
                    return (row.dataset.status || '').toLowerCase().trim();
                case 'whencreated': 
                    return this.parseAdWhenCreated(row.dataset.whencreated || '');
                default: 
                    return (row.dataset.username || '').toLowerCase().trim();
            }
        };
        
        rows.sort((a, b) => {
            const va = getVal(a);
            const vb = getVal(b);
            
            if (va === null || va === undefined) return 1;
            if (vb === null || vb === undefined) return -1;
            
            if (va === '' && vb === '') return 0;
            if (va === '') return 1;
            if (vb === '') return -1;
            
            if (this.sortKey === 'row') {
                const ia = Number(a.dataset.rowindex || '0');
                const ib = Number(b.dataset.rowindex || '0');
                if (ia && ib) return this.sortDir === 'asc' ? ia - ib : ib - ia;
                const ua = (a.dataset.username || '').toLowerCase();
                const ub = (b.dataset.username || '').toLowerCase();
                return this.sortDir === 'asc' ? ua.localeCompare(ub) : ub.localeCompare(ua);
            }
            
            if (typeof va === 'number' && typeof vb === 'number') {
                return this.sortDir === 'asc' ? va - vb : vb - va;
            }
            
            const comparison = va.toString().localeCompare(vb.toString(), 'th', {
                numeric: true,
                sensitivity: 'base',
                ignorePunctuation: true
            });
            
            return this.sortDir === 'asc' ? comparison : -comparison;
        });
        
        return rows;
    }

    parseAdWhenCreated(value) {
        if (!value) return 0;
        const m = String(value).match(/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/);
        if (!m) return 0;
        const [_, y, mo, d, h, mi, s] = m;
        const dt = new Date(Date.UTC(Number(y), Number(mo) - 1, Number(d), Number(h), Number(mi), Number(s)));
        return dt.getTime();
    }

    // Pagination Methods
    renderPage(page = 1) {
        const allRows = Array.from(document.querySelectorAll('.user-row'));
        const matched = allRows.filter(r => r.dataset.matches !== '0');
        
        this.sortRows(matched);
        
        const total = matched.length;
        const totalPages = Math.max(1, Math.ceil(total / this.PAGE_SIZE));
        const current = Math.min(Math.max(1, page), totalPages);

        allRows.forEach(r => r.style.display = 'none');
        
        const start = (current - 1) * this.PAGE_SIZE;
        const end = start + this.PAGE_SIZE;
        const pageRows = matched.slice(start, end);
        const from = total === 0 ? 0 : start + 1;
        
        pageRows.forEach((r, idx) => {
            r.style.display = '';
            const numCell = r.querySelector('td.col-row-no');
            if (numCell) {
                numCell.textContent = String(start + idx + 1);
            }
            r.dataset.rowindex = String(start + idx + 1);
        });

        const to = Math.min(end, total);
        if (this.paginationInfo) {
            this.paginationInfo.textContent = `Showing ${from}-${to} of ${total} users`;
        }
        
        const filteredCount = document.getElementById('filteredCount');
        if (filteredCount) {
            filteredCount.textContent = `${total} คน`;
        }

        this.renderPaginationButtons(current, totalPages);
    }

    renderPaginationButtons(current, totalPages) {
        if (!this.paginationButtons) return;
        const serverPagination = document.querySelector('.ou-users')?.getAttribute('data-server-pagination') === '1';
        if (serverPagination) return;
        this.paginationButtons.innerHTML = '';
        
        const makeBtn = (label, target, disabled = false, active = false) => {
            const a = document.createElement('a');
            a.href = '#';
            a.className = `page-link${active ? ' active' : ''}`;
            a.textContent = label;
            const li = document.createElement('li');
            li.className = `page-item${disabled ? ' disabled' : ''}`;
            li.appendChild(a);
            if (!disabled) {
                a.addEventListener('click', (e) => { 
                    e.preventDefault(); 
                    this.renderPage(target); 
                });
            }
            return li;
        };

        const ul = document.createElement('ul');
        ul.className = 'pagination pagination-sm mb-0';
        ul.appendChild(makeBtn('«', 1, current === 1));
        ul.appendChild(makeBtn('‹', current - 1, current === 1));
        
        const windowSize = 5;
        const startPage = Math.max(1, current - Math.floor(windowSize / 2));
        const endPage = Math.min(totalPages, startPage + windowSize - 1);
        
        for (let p = startPage; p <= endPage; p++) {
            ul.appendChild(makeBtn(String(p), p, false, p === current));
        }
        
        ul.appendChild(makeBtn('›', current + 1, current === totalPages));
        ul.appendChild(makeBtn('»', totalPages, current === totalPages));
        this.paginationButtons.appendChild(ul);
    }

    applySearchAndRender(page = 1) {
        const container = document.querySelector('.ou-users');
        const serverPagination = container && container.getAttribute('data-server-pagination') === '1';
        this.applySearch();
        if (serverPagination) {
            this.showAllMatchedRows();
        } else {
            this.renderPage(page);
        }
        const header = document.querySelector('th.sortable[data-sort-key="row"]');
        if (header) {
            document.querySelectorAll('th.sortable').forEach(h => h.setAttribute('aria-sort', 'none'));
            header.setAttribute('aria-sort', this.sortDir);
            const icon = header.querySelector('.sort-icon i');
            if (icon) {
                icon.className = this.sortDir === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down';
            }
        }
    }

    // Modal Methods
    bindModalEvents() {
        this.viewButtons.forEach(button => {
            button.addEventListener('click', (e) => this.handleViewUser(e));
        });

        this.updateButtons.forEach(button => {
            button.addEventListener('click', (e) => this.handleUpdateUser(e));
        });
    }

    handleViewUser(e) {
        const userData = JSON.parse(e.target.closest('.view-user').getAttribute('data-user'));
        
        const modalTitle = document.getElementById('viewUserModalLabel');
        const displayName = userData.displayname || userData.username || '';
        modalTitle.innerHTML = '<i class="fas fa-user-circle me-2"></i>รายละเอียดผู้ใช้เพิ่มเติม';
        
        // Populate all fields
        document.getElementById('modalDisplayName').textContent = userData.displayname || 'ไม่ระบุ';
        document.getElementById('modalEmail').textContent = userData.email || 'ไม่ระบุ';
        document.getElementById('modalTelephone').textContent = userData.telephone || 'ไม่ระบุ';
        document.getElementById('modalCompany').textContent = userData.company || 'ไม่ระบุ';
        document.getElementById('modalOffice').textContent = userData.office || 'ไม่ระบุ';
        document.getElementById('modalPostalcode').textContent = userData.postalcode || 'ไม่ระบุ';
        document.getElementById('modalOu').textContent = userData.ou || 'ไม่ระบุ';
        document.getElementById('modalStreetAddress').textContent = userData.streetaddress || 'ไม่ระบุ';
    }

    handleUpdateUser(e) {
        const userData = JSON.parse(e.target.closest('.btn-primary[data-toggle="modal"]').getAttribute('data-user'));
        
        const modalTitle = document.getElementById('updateUserModalLabel');
        const displayName = userData.displayname || userData.username;
        modalTitle.innerHTML = '<i class="fas fa-user-edit me-2"></i>แก้ไขข้อมูลผู้ใช้: ' + displayName;
        
        document.getElementById('updateCn').value = userData.cn;
        document.getElementById('updateUsername').value = userData.username;
        document.getElementById('updateDisplayName').value = userData.displayname;
        document.getElementById('updateDepartment').value = userData.department;
        document.getElementById('updateTitle').value = userData.title || '';
        document.getElementById('updateEmail').value = userData.email;
    }

    handleFormSubmission(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        
        fetch(e.target.action, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showNotification('User updated successfully', 'success');
                const modal = bootstrap.Modal.getInstance(document.getElementById('updateUserModal'));
                if (modal) {
                    modal.hide();
                }
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                this.showNotification(data.message || 'Failed to update user', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            this.showNotification('An error occurred while updating the user', 'error');
        });
    }

    // Toggle Status Methods
    handleToggleStatusSwitch(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const switchInput = e.target.closest('.toggle-status-switch') || e.target;
        
        // Check if switch is disabled
        if (switchInput.disabled) {
            console.log('Switch is disabled, ignoring toggle');
            // Revert switch state
            switchInput.checked = !switchInput.checked;
            return false;
        }
        
        const cn = switchInput.getAttribute('data-cn');
        const samaccountname = switchInput.getAttribute('data-samaccountname') || cn;
        const currentStatus = switchInput.getAttribute('data-current-status');
        
        // Logic: Switch state AFTER toggle (switchInput.checked) represents the NEW desired status
        // - If switch is checked (ON) after toggle → user wants ENABLED status → send enable='1'
        // - If switch is unchecked (OFF) after toggle → user wants DISABLED status → send enable='0'
        const enable = switchInput.checked ? '1' : '0';
        
        // Use sAMAccountName for better search accuracy, fallback to CN
        const identifier = samaccountname || cn;
        
        console.log('Toggle switch logic:', {
            cn,
            samaccountname,
            identifier,
            currentStatusBeforeToggle: currentStatus,
            switchStateAfterToggle: switchInput.checked,
            willSendEnable: enable,
            action: switchInput.checked ? 'ENABLE' : 'DISABLE',
            explanation: switchInput.checked 
                ? 'Switch ON → User wants account ENABLED → send enable=1'
                : 'Switch OFF → User wants account DISABLED → send enable=0'
        });
        
        if (this.toggleStatusDebounce.has(identifier)) {
            console.log('Request already in progress for:', identifier);
            // Revert switch state กลับไปเป็นสถานะเดิม
            switchInput.checked = (currentStatus === 'enabled');
            return false;
        }
        
        console.log('Toggle status switch clicked:', { identifier, cn, samaccountname, enable, currentStatus, checked: switchInput.checked });
        
        this.toggleStatusDebounce.set(identifier, true);
        
        // Disable switch during request
        switchInput.disabled = true;
        
        const formData = new FormData();
        formData.append('cn', identifier); // Send sAMAccountName if available, otherwise CN
        formData.append('samaccountname', samaccountname); // Also send sAMAccountName separately
        formData.append('enable', enable);
        formData.append(document.querySelector('meta[name="csrf-param"]').content, document.querySelector('meta[name="csrf-token"]').content);
        
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000);
        
        fetch('index.php?r=ldapuser/toggle-status', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData,
            signal: controller.signal
        })
        .then(response => {
            clearTimeout(timeoutId);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            this.toggleStatusDebounce.delete(identifier);
            
            if (data.success) {
                this.updateUserStatusSwitch(identifier, data.newStatus, data.newStatusText);
                this.showNotification(data.message, 'success');
            } else {
                console.error('Toggle status failed:', data.message);
                this.showNotification(data.message || 'Failed to update user status', 'error');
                // Revert switch state on failure - กลับไปเป็นสถานะเดิม (ก่อน toggle)
                switchInput.checked = (currentStatus === 'enabled');
                switchInput.disabled = false;
            }
        })
        .catch(error => {
            clearTimeout(timeoutId);
            this.toggleStatusDebounce.delete(identifier);
            console.error('Toggle status error:', error);
            
            let errorMessage = 'An error occurred while updating user status';
            if (error.name === 'AbortError') {
                errorMessage = 'Request timeout. Please try again.';
            } else if (error.message) {
                errorMessage = error.message;
            }
            
            this.showNotification(errorMessage, 'error');
            
            // Always revert switch state on error - กลับไปเป็นสถานะเดิม (ก่อน toggle)
            // ถ้าสถานะเดิมเป็น enabled → switch ต้องเป็น checked
            // ถ้าสถานะเดิมเป็น disabled → switch ต้องเป็น unchecked
            switchInput.checked = (currentStatus === 'enabled');
            switchInput.disabled = false;
        });
        
        return false;
    }

    handleToggleStatus(e) {
        if (e.target.closest('.toggle-status-btn')) {
            e.preventDefault();
            e.stopPropagation();
            
            const button = e.target.closest('.toggle-status-btn');
            
            // Check if button is disabled
            if (button.disabled) {
                console.log('Button is disabled, ignoring click');
                return false;
            }
            
            const cn = button.getAttribute('data-cn');
            const enable = button.getAttribute('data-enable');
            const currentStatus = button.getAttribute('data-current-status');
            
            if (this.toggleStatusDebounce.has(cn)) {
                console.log('Request already in progress for:', cn);
                return false;
            }
            
            console.log('Toggle status clicked:', { cn, enable, currentStatus });
            
            this.toggleStatusDebounce.set(cn, true);
            
            const originalContent = button.innerHTML;
            button.disabled = true;
            button.style.opacity = '0.6';
            
            const formData = new FormData();
            formData.append('cn', cn);
            formData.append('enable', enable);
            formData.append(document.querySelector('meta[name="csrf-param"]').content, document.querySelector('meta[name="csrf-token"]').content);
            
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 10000);
            
            fetch('index.php?r=ldapuser/toggle-status', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData,
                signal: controller.signal
            })
            .then(response => {
                clearTimeout(timeoutId);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                this.toggleStatusDebounce.delete(cn);
                
                if (data.success) {
                    this.updateUserStatus(cn, data.newStatus, data.newStatusText);
                    this.showNotification(data.message, 'success');
                } else {
                    console.error('Toggle status failed:', data.message);
                    this.showNotification(data.message || 'Failed to update user status', 'error');
                    // Restore button state on failure
                    button.innerHTML = originalContent;
                    button.disabled = false;
                    button.style.opacity = '1';
                }
            })
            .catch(error => {
                clearTimeout(timeoutId);
                this.toggleStatusDebounce.delete(cn);
                console.error('Toggle status error:', error);
                
                let errorMessage = 'An error occurred while updating user status';
                if (error.name === 'AbortError') {
                    errorMessage = 'Request timeout. Please try again.';
                } else if (error.message) {
                    errorMessage = error.message;
                }
                
                this.showNotification(errorMessage, 'error');
                
                // Always restore button state on error
                button.innerHTML = originalContent;
                button.disabled = false;
                button.style.opacity = '1';
            });
            
            return false;
        }
    }

    updateUserStatusSwitch(identifier, newStatus, newStatusText) {
        console.log('Updating user status switch:', { identifier, newStatus, newStatusText });
        
        // Try multiple selectors to find the user row
        // First try by sAMAccountName (username), then by CN
        let userRow = document.querySelector(`.user-row[data-username="${identifier}"]`);
        if (!userRow) {
            userRow = document.querySelector(`.user-row[data-cn="${identifier}"]`);
        }
        if (!userRow) {
            console.error('User row not found for identifier:', identifier);
            return;
        }
        
        console.log('Found user row:', userRow);
        
        // Update row data attributes
        userRow.setAttribute('data-status', newStatus);
        userRow.setAttribute('data-disabled', newStatus === 'disabled' ? '1' : '0');
        
        // Update toggle switch
        const toggleSwitch = userRow.querySelector('.toggle-status-switch');
        if (toggleSwitch) {
            const username = userRow.getAttribute('data-username') || identifier;
            const displayname = userRow.getAttribute('data-displayname') || username;
            
            console.log('Updating toggle switch for status:', newStatus);
            
            // Update switch checked state to reflect current status in AD
            // enabled = checked (ON/green), disabled = unchecked (OFF/red)
            toggleSwitch.checked = (newStatus === 'enabled');
            
            // Update data attributes
            // If currently enabled, next toggle will disable (enable='0')
            // If currently disabled, next toggle will enable (enable='1')
            toggleSwitch.setAttribute('data-enable', newStatus === 'enabled' ? '0' : '1');
            toggleSwitch.setAttribute('data-current-status', newStatus);
            toggleSwitch.setAttribute('title', displayname + ' - ' + (newStatus === 'enabled' ? 'เปิดอยู่ (Enabled)' : 'ถูกปิดอยู่ (Disabled)'));
            
            // Re-enable the switch
            toggleSwitch.disabled = false;
            
            console.log('Toggle switch updated:', {
                checked: toggleSwitch.checked,
                dataEnable: toggleSwitch.getAttribute('data-enable'),
                dataCurrentStatus: toggleSwitch.getAttribute('data-current-status')
            });
        } else {
            console.error('Toggle switch not found for user:', identifier);
        }
    }

    updateUserStatus(cn, newStatus, newStatusText) {
        console.log('Updating user status:', { cn, newStatus, newStatusText });
        
        // Try multiple selectors to find the user row
        let userRow = document.querySelector(`.user-row[data-username="${cn}"]`);
        if (!userRow) {
            userRow = document.querySelector(`.user-row[data-cn="${cn}"]`);
        }
        if (!userRow) {
            console.error('User row not found for CN:', cn);
            return;
        }
        
        console.log('Found user row:', userRow);
        
        // Update row data attributes
        userRow.setAttribute('data-status', newStatus);
        userRow.setAttribute('data-disabled', newStatus === 'disabled' ? '1' : '0');
        
        // Try to update switch first (new format)
        const toggleSwitch = userRow.querySelector('.toggle-status-switch');
        if (toggleSwitch) {
            this.updateUserStatusSwitch(cn, newStatus, newStatusText);
            return;
        }
        
        // Fallback to old button format if switch doesn't exist
        const toggleBtn = userRow.querySelector('.toggle-status-btn');
        if (toggleBtn) {
            const username = userRow.getAttribute('data-username') || cn;
            const displayname = userRow.getAttribute('data-displayname') || username;
            
            console.log('Updating toggle button for status:', newStatus);
            
            if (newStatus === 'enabled') {
                toggleBtn.className = 'btn btn-sm btn-success toggle-status-btn';
                toggleBtn.setAttribute('data-enable', '0');
                toggleBtn.setAttribute('data-current-status', 'enabled');
                toggleBtn.setAttribute('title', 'สถานะ: ' + displayname + ' เปิดอยู่ (Enabled)');
                toggleBtn.innerHTML = '<i class="fas fa-check-circle"></i> <span class="badge bg-success">Enabled</span>';
            } else {
                toggleBtn.className = 'btn btn-sm btn-danger toggle-status-btn';
                toggleBtn.setAttribute('data-enable', '1');
                toggleBtn.setAttribute('data-current-status', 'disabled');
                toggleBtn.setAttribute('title', 'สถานะ: ' + displayname + ' ถูกปิดอยู่ (Disabled)');
                toggleBtn.innerHTML = '<i class="fas fa-ban"></i> <span class="badge bg-danger">Disabled</span>';
            }
            
            toggleBtn.disabled = false;
            toggleBtn.style.opacity = '1';
        }
    }

    // Utility Methods
    showNotification(message, type = 'success') {
        const toast = document.getElementById('statusToast');
        const toastMessage = document.getElementById('toastMessage');
        const toastHeader = toast.querySelector('.toast-header');
        const icon = toastHeader.querySelector('i');
        
        toastMessage.textContent = message;
        
        if (type === 'success') {
            icon.className = 'fas fa-check-circle text-success me-2';
            toastHeader.className = 'toast-header';
        } else {
            icon.className = 'fas fa-exclamation-circle text-danger me-2';
            toastHeader.className = 'toast-header bg-danger text-white';
        }
        
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
    }

    updateRowWithFreshDataSwitch(userRow, userData) {
        const rowIndex = userRow.getAttribute('data-rowindex') || userRow.querySelector('td.col-row-no')?.textContent;
        const isDisabled = userData.status === 'disabled';
        
        // Find existing switch wrapper or create new one
        let switchWrapper = userRow.querySelector('.toggle-status-wrapper');
        if (!switchWrapper) {
            // Create switch wrapper if it doesn't exist
            const actionsCell = userRow.querySelector('td:last-child');
            if (actionsCell) {
                const btnGroup = actionsCell.querySelector('.btn-group');
                if (btnGroup) {
                    switchWrapper = document.createElement('div');
                    switchWrapper.className = 'form-check form-switch toggle-status-wrapper';
                    btnGroup.appendChild(switchWrapper);
                }
            }
        }
        
        if (switchWrapper) {
            const switchId = `statusSwitch-${rowIndex}`;
            switchWrapper.innerHTML = `
                <input class="form-check-input toggle-status-switch" 
                    type="checkbox" 
                    role="switch"
                    id="${switchId}"
                    data-cn="${userData.username}" 
                    data-enable="${isDisabled ? '1' : '0'}" 
                    data-current-status="${isDisabled ? 'disabled' : 'enabled'}"
                    ${!isDisabled ? 'checked' : ''}
                    title="${userData.displayname || userData.username} - ${isDisabled ? 'ถูกปิดอยู่ (Disabled)' : 'เปิดอยู่ (Enabled)'}">
            `;
        }
        
        userRow.setAttribute('data-status', userData.status);
        userRow.setAttribute('data-disabled', isDisabled ? '1' : '0');
        userRow.setAttribute('data-username', userData.username);
        userRow.setAttribute('data-displayname', userData.displayname || '');
        userRow.setAttribute('data-department', userData.department || '');
        userRow.setAttribute('data-title', userData.title || '');
        userRow.setAttribute('data-email', userData.email || '');
        userRow.setAttribute('data-ou', userData.ou || '');
    }

    updateRowWithFreshData(userRow, userData) {
        const rowIndex = userRow.getAttribute('data-rowindex') || userRow.querySelector('td.col-row-no')?.textContent;
        const isDisabled = userData.status === 'disabled';
        
        userRow.innerHTML = `
            <td class="text-end">${rowIndex}</td>
            <td>${userData.username || ''}</td>
            <td>${userData.department || ''}</td>
            <td>
                ${isDisabled ? '<span class="badge badge-danger">Disabled</span>' : '<span class="badge badge-success">Enabled</span>'}
            </td>
            <td>
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-info view-user" 
                        data-bs-toggle="modal" 
                        data-bs-target="#viewUserModal"
                        data-user='${JSON.stringify({
                            username: userData.username,
                            displayname: userData.displayname,
                            department: userData.department,
                            title: userData.title || '',
                            email: userData.email,
                            status: userData.status,
                            ou: userData.ou
                        })}'
                        title="ดูข้อมูลผู้ใช้: ${userData.displayname || userData.username}">
                        <i class="fas fa-eye"></i>
                    </button>
                    <a href="${window.location.origin}/ldapuser/update&cn=${userData.username}" class="btn btn-sm btn-primary" title="แก้ไขข้อมูลผู้ใช้: ${userData.displayname || userData.username}">
                        <i class="fas fa-edit"></i>
                    </a>
                    <a href="${window.location.origin}/ldapuser/move&cn=${userData.username}" class="btn btn-sm btn-warning" title="ย้ายผู้ใช้: ${userData.displayname || userData.username}">
                        <i class="fas fa-exchange-alt"></i>
                    </a>
                    ${isDisabled ? 
                        `<button type="button" class="btn btn-sm btn-danger toggle-status-btn" 
                            data-cn="${userData.username}" 
                            data-enable="1" 
                            data-current-status="disabled"
                            title="สถานะ: ${userData.displayname || userData.username} ถูกปิดอยู่ (Disabled)">
                            <i class="fas fa-ban"></i> <span class="badge bg-danger">Disabled</span>
                        </button>` :
                        `<button type="button" class="btn btn-sm btn-success toggle-status-btn" 
                            data-cn="${userData.username}" 
                            data-enable="0" 
                            data-current-status="enabled"
                            title="สถานะ: ${userData.displayname || userData.username} เปิดอยู่ (Enabled)">
                            <i class="fas fa-check-circle"></i> <span class="badge bg-success">Enabled</span>
                        </button>`
                    }
                </div>
            </td>
        `;
        
        userRow.setAttribute('data-status', userData.status);
        userRow.setAttribute('data-disabled', isDisabled ? '1' : '0');
        userRow.setAttribute('data-username', userData.username);
        userRow.setAttribute('data-displayname', userData.displayname || '');
        userRow.setAttribute('data-department', userData.department || '');
        userRow.setAttribute('data-title', userData.title || '');
        userRow.setAttribute('data-email', userData.email || '');
        userRow.setAttribute('data-ou', userData.ou || '');
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    new OuUserManager();
});
