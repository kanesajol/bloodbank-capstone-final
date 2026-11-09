document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const mobileSidebarQuery = window.matchMedia('(max-width: 900px)');
    let sidebarBackdrop = document.getElementById('sidebar-backdrop');

    const isMobileSidebar = () => mobileSidebarQuery.matches;

    const ensureSidebarBackdrop = () => {
        if (sidebarBackdrop) {
            return sidebarBackdrop;
        }
        sidebarBackdrop = document.createElement('button');
        sidebarBackdrop.id = 'sidebar-backdrop';
        sidebarBackdrop.type = 'button';
        sidebarBackdrop.className = 'sidebar-backdrop';
        sidebarBackdrop.setAttribute('aria-label', 'Close navigation menu');
        sidebarBackdrop.hidden = true;
        document.body.appendChild(sidebarBackdrop);
        sidebarBackdrop.addEventListener('click', () => {
            sidebar?.classList.remove('sidebar--drawer-open');
            sidebarBackdrop.hidden = true;
            document.body.classList.remove('dashboard--drawer-open');
        });
        return sidebarBackdrop;
    };

    const setMobileDrawerOpen = (open) => {
        if (!sidebar) {
            return;
        }
        const backdrop = ensureSidebarBackdrop();
        sidebar.classList.toggle('sidebar--drawer-open', open);
        backdrop.hidden = !open;
        document.body.classList.toggle('dashboard--drawer-open', open);
    };

    const syncSidebarMode = () => {
        if (!sidebar) {
            return;
        }
        if (isMobileSidebar()) {
            sidebar.classList.remove('sidebar--collapsed');
            setMobileDrawerOpen(false);
            return;
        }
        sidebar.classList.remove('sidebar--drawer-open');
        ensureSidebarBackdrop().hidden = true;
        document.body.classList.remove('dashboard--drawer-open');
        try {
            const collapsed = localStorage.getItem('crh-sidebar') === 'collapsed';
            sidebar.classList.toggle('sidebar--collapsed', collapsed);
        } catch (e) {}
    };

    if (sidebar && sidebarToggle) {
        sidebarToggle.addEventListener('click', () => {
            if (isMobileSidebar()) {
                const open = !sidebar.classList.contains('sidebar--drawer-open');
                setMobileDrawerOpen(open);
                return;
            }
            const collapsed = sidebar.classList.toggle('sidebar--collapsed');
            try {
                localStorage.setItem('crh-sidebar', collapsed ? 'collapsed' : 'open');
            } catch (e) {}
        });

        sidebar.querySelectorAll('a.sidebar__item').forEach((link) => {
            link.addEventListener('click', () => {
                if (isMobileSidebar()) {
                    setMobileDrawerOpen(false);
                }
            });
        });

        syncSidebarMode();
        if (typeof mobileSidebarQuery.addEventListener === 'function') {
            mobileSidebarQuery.addEventListener('change', syncSidebarMode);
        } else if (typeof mobileSidebarQuery.addListener === 'function') {
            mobileSidebarQuery.addListener(syncSidebarMode);
        }
    }

    const userMenu = document.getElementById('user-menu');
    const userMenuBtn = document.getElementById('user-menu-btn');
    const userMenuWrap = document.getElementById('user-menu-wrap');
    if (userMenu && userMenuBtn && userMenuWrap) {
        userMenuBtn.addEventListener('click', (event) => {
            event.stopPropagation();
            const open = userMenu.hasAttribute('hidden');
            userMenu.toggleAttribute('hidden', !open);
            userMenuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('click', (event) => {
            if (!userMenuWrap.contains(event.target)) {
                userMenu.setAttribute('hidden', '');
                userMenuBtn.setAttribute('aria-expanded', 'false');
            }
        });
    }

    document.querySelectorAll('[data-modal-open]').forEach((button) => {
        button.addEventListener('click', () => {
            const modal = document.getElementById(button.dataset.modalOpen);
            if (modal) modal.removeAttribute('hidden');
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach((button) => {
        button.addEventListener('click', () => {
            const modal = document.getElementById(button.dataset.modalClose);
            if (modal) modal.setAttribute('hidden', '');
        });
    });

    const deleteModal = document.getElementById('delete-confirm-modal');
    const deleteForm = document.getElementById('delete-confirm-form');
    const deleteName = document.getElementById('delete-confirm-name');
    document.querySelectorAll('[data-delete-url]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            if (!deleteModal || !deleteForm) return;
            deleteForm.action = button.dataset.deleteUrl;
            if (deleteName) deleteName.textContent = button.dataset.deleteName || '';
            const fields = {
                return_to: button.dataset.returnTo || '',
                mode: button.dataset.returnMode || '',
                return_location: button.dataset.returnLocation || '',
                return_year: button.dataset.returnYear || '',
            };
            Object.entries(fields).forEach(([name, value]) => {
                const input = deleteForm.querySelector(`[name="${name}"]`);
                if (input) input.value = value;
            });
            deleteModal.removeAttribute('hidden');
        });
    });

    document.querySelectorAll('.modal-overlay, .modal-backdrop').forEach((overlay) => {
        overlay.addEventListener('click', (event) => {
            if (event.target !== overlay) return;
            if (overlay.dataset.closeUrl) {
                window.location = overlay.dataset.closeUrl;
                return;
            }
            overlay.setAttribute('hidden', '');
        });
    });

    document.querySelectorAll('[data-live-search]').forEach((input) => {
        const table = document.querySelector(input.dataset.liveSearch);
        if (!table) return;
        const rows = [...table.querySelectorAll('tbody tr[data-search]')];
        const empty = table.querySelector('[data-empty-row]');
        const count = input.dataset.count ? document.querySelector(input.dataset.count) : null;
        const queryLabel = input.dataset.queryLabel ? document.querySelector(input.dataset.queryLabel) : null;
        const clearBtn = document.getElementById(input.id === 'name-query' ? 'name-clear' : '');

        const apply = () => {
            const query = input.value.trim().toLowerCase();
            let visible = 0;
            rows.forEach((row) => {
                const shiftFilter = document.getElementById('staff-shift-filter');
                const shift = shiftFilter ? shiftFilter.value : '';
                const matchesText = !query || (row.dataset.search || '').includes(query);
                const matchesShift = !shift || !row.dataset.shift || row.dataset.shift === shift;
                const show = matchesText && matchesShift;
                row.toggleAttribute('hidden', !show);
                if (show) visible += 1;
            });
            if (empty) empty.toggleAttribute('hidden', visible !== 0);
            if (count) count.textContent = String(visible);
            if (queryLabel) {
                queryLabel.toggleAttribute('hidden', query === '');
                queryLabel.textContent = query ? ` for "${input.value.trim()}"` : '';
            }
            const nameClear = document.getElementById('name-clear');
            if (nameClear && input.id === 'name-query') nameClear.toggleAttribute('hidden', query === '');
        };

        input.addEventListener('input', apply);
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                input.value = '';
                apply();
            });
        }
    });

    const staffShift = document.getElementById('staff-shift-filter');
    if (staffShift) {
        staffShift.addEventListener('change', () => {
            const input = document.querySelector('#staff-table')?.closest('.tab-content-panel')?.querySelector('[data-live-search]');
            input?.dispatchEvent(new Event('input'));
        });
    }

    const locationInput = document.getElementById('location-query');
    if (locationInput) {
        const results = document.getElementById('location-results');
        const hint = document.getElementById('location-hint');
        const count = document.getElementById('location-count');
        const empty = document.getElementById('location-empty');
        const clear = document.getElementById('location-clear');
        const cards = [...document.querySelectorAll('[data-location]')];
        const chips = [...document.querySelectorAll('[data-location-chip]')];

        const applyLocation = () => {
            const query = locationInput.value.trim().toLowerCase();
            chips.forEach((chip) => {
                chip.classList.toggle('active', chip.dataset.locationChip.toLowerCase() === query);
            });
            if (!query) {
                results?.setAttribute('hidden', '');
                hint?.removeAttribute('hidden');
                clear?.setAttribute('hidden', '');
                return;
            }
            let visible = 0;
            cards.forEach((card) => {
                const show = (card.dataset.location || '').includes(query);
                card.toggleAttribute('hidden', !show);
                if (show) visible += 1;
            });
            if (count) {
                count.innerHTML = `${visible} donor${visible === 1 ? '' : 's'} associated with "<strong>${locationInput.value.trim()}</strong>":`;
            }
            empty?.toggleAttribute('hidden', visible !== 0);
            results?.removeAttribute('hidden');
            hint?.setAttribute('hidden', '');
            clear?.removeAttribute('hidden');
        };

        locationInput.addEventListener('input', applyLocation);
        clear?.addEventListener('click', () => {
            locationInput.value = '';
            applyLocation();
        });
        chips.forEach((chip) => {
            chip.addEventListener('click', () => {
                locationInput.value = chip.dataset.locationChip;
                applyLocation();
            });
        });
    }

    const nameClear = document.getElementById('name-clear');
    const nameQuery = document.getElementById('name-query');
    if (nameClear && nameQuery) {
        nameClear.addEventListener('click', () => {
            nameQuery.value = '';
            nameQuery.dispatchEvent(new Event('input'));
        });
    }

    const toggleDonation = document.getElementById('toggle-donation-form');
    const donationPanel = document.getElementById('add-donation-panel');
    const donationLabel = document.getElementById('toggle-donation-label');
    const cancelDonation = document.getElementById('cancel-donation-form');
    if (toggleDonation && donationPanel) {
        const setOpen = (open) => {
            donationPanel.toggleAttribute('hidden', !open);
            if (donationLabel) donationLabel.textContent = open ? 'Close Form' : 'Add New Donation';
        };
        toggleDonation.addEventListener('click', () => setOpen(donationPanel.hasAttribute('hidden')));
        cancelDonation?.addEventListener('click', () => setOpen(false));
    }

    document.querySelectorAll('[data-register-tab]').forEach((button) => {
        button.addEventListener('click', () => {
            const tab = button.dataset.registerTab;
            document.querySelectorAll('[data-register-tab]').forEach((item) => {
                item.classList.toggle('active', item === button);
            });
            document.getElementById('register-new-panel')?.toggleAttribute('hidden', tab !== 'new');
            document.getElementById('register-returning-panel')?.toggleAttribute('hidden', tab !== 'returning');
            const icon = document.getElementById('register-modal-icon');
            const title = document.getElementById('register-modal-title');
            const sub = document.getElementById('register-modal-sub');
            if (icon) icon.className = tab === 'new' ? 'ti ti-user-plus' : 'ti ti-refresh';
            if (title) title.textContent = tab === 'new' ? 'Register First-Time Donor' : 'Record Returning Donor Donation';
            if (sub) sub.textContent = tab === 'new'
                ? 'Record donor profile and initial donation details.'
                : 'Search an existing donor to add a new donation to their profile.';
        });
    });

    const returningQuery = document.getElementById('returning-query');
    if (returningQuery) {
        const results = document.getElementById('returning-results');
        const empty = document.getElementById('returning-empty');
        const emptyName = document.getElementById('returning-empty-name');
        const hint = document.getElementById('returning-hint');
        const registerBtn = document.getElementById('register-from-search');
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        let timer;
        let controller;

        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');

        returningQuery.addEventListener('input', () => {
            const query = returningQuery.value.trim();
            window.clearTimeout(timer);
            controller?.abort();
            if (!query) {
                if (results) results.innerHTML = '';
                results?.setAttribute('hidden', '');
                empty?.setAttribute('hidden', '');
                if (hint) hint.textContent = 'Type one letter to find an existing donor.';
                return;
            }
            if (query.length < 1) return;
            results?.setAttribute('hidden', '');
            empty?.setAttribute('hidden', '');
            if (hint) hint.textContent = 'Searching donors…';
            timer = window.setTimeout(() => {
                const url = new URL(returningQuery.dataset.searchUrl, window.location.origin);
                url.searchParams.set('q', query);
                controller = new AbortController();
                const current = controller;
                fetch(url.toString(), {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    signal: current.signal,
                })
                    .then(async (response) => {
                        if (!response.ok) {
                            const data = await response.json().catch(() => ({}));
                            throw new Error(data.message || 'Unable to search donors. Please try again.');
                        }

                        return response.json();
                    })
                    .then((rows) => {
                        if (controller !== current || !results) return;
                        const today = returningQuery.dataset.today || '';
                        const mode = returningQuery.dataset.mode || 'name';
                        results.innerHTML = rows.map((row) => {
                            const name = escapeHtml(row.name);
                            const action = `${returningQuery.dataset.storeBase}/${row.id}/donations`;
                            const donations = Number(row.donations) || 0;
                            return `<div class="returning-donor-item">
                                <div class="returning-donor-info">
                                    <div class="returning-donor-header">
                                        <strong class="donor-name-link">${name}</strong>
                                        <span class="blood-type-badge sm">${escapeHtml(row.blood_type || '')}</span>
                                    </div>
                                    <span class="returning-donor-meta">${escapeHtml(row.age ?? '')} yrs · ${escapeHtml(row.gender || '')} · ${escapeHtml(row.address || '')} · <strong class="text-red">${donations}</strong> previous donation${donations === 1 ? '' : 's'}</span>
                                </div>
                                <button type="button" class="btn-add-donation-sm"><i class="ti ti-plus"></i> Add Donation</button>
                                <form class="add-donation-panel" method="POST" action="${action}" hidden>
                                    <input type="hidden" name="_token" value="${escapeHtml(token)}">
                                    <input type="hidden" name="form" value="returning-donation">
                                    <input type="hidden" name="register_tab" value="returning">
                                    <input type="hidden" name="returning_donor" value="${row.id}">
                                    <input type="hidden" name="returning_donor_name" value="${name}">
                                    <input type="hidden" name="mode" value="${escapeHtml(mode)}">
                                    <div class="returning-donation-fields">
                                        <div class="form-field">
                                            <label>MBD / Date</label>
                                            <div class="mbd-control">
                                                <select name="mbd_type" data-mbd-type aria-label="MBD or WI" required>
                                                    <option value="MBD">MBD</option>
                                                    <option value="WI">WI</option>
                                                </select>
                                                <input name="mbd_on" value="${escapeHtml(today)}" type="date" data-mbd-on required>
                                            </div>
                                            <input name="mbd_date" type="hidden" data-mbd-value>
                                        </div>
                                        <div class="form-actions-inline">
                                            <button type="submit" class="btn-submit-sm">Save</button>
                                        </div>
                                    </div>
                                </form>
                            </div>`;
                        }).join('');
                        results.querySelectorAll('.btn-add-donation-sm').forEach((button) => {
                            button.addEventListener('click', () => {
                                button.hidden = true;
                                button.parentElement?.querySelector('form')?.removeAttribute('hidden');
                            });
                        });
                        window.bindCollectionFields?.(results);
                        results.toggleAttribute('hidden', rows.length === 0);
                        empty?.toggleAttribute('hidden', rows.length !== 0);
                        if (hint) {
                            hint.textContent = rows.length === 0
                                ? 'No matching donor was found.'
                                : `${rows.length} matching donor${rows.length === 1 ? '' : 's'} found.`;
                        }
                        if (emptyName) emptyName.textContent = query;
                    })
                    .catch((error) => {
                        if (error.name !== 'AbortError' && controller === current && hint) {
                            hint.textContent = error.message;
                        }
                    });
            }, 250);
        });

        registerBtn?.addEventListener('click', () => {
            document.querySelector('[data-register-tab="new"]')?.click();
            const name = document.getElementById('regFullName');
            if (name) name.value = returningQuery.value.trim();
        });
    }

    const consumptionFilters = document.getElementById('consumption-filters');
    if (consumptionFilters) {
        const rows = [...document.querySelectorAll('#consumption-table tbody tr[data-bags]')];
        const empty = document.querySelector('#consumption-table [data-empty-row]');
        const totalEl = document.getElementById('consumption-total');
        const foot = document.getElementById('consumption-foot');
        const footTotal = document.getElementById('consumption-foot-total');
        const countEl = document.getElementById('consumption-count');
        const location = document.getElementById('filter-location');
        const blood = document.getElementById('filter-blood');
        const month = document.getElementById('filter-month');
        const year = document.getElementById('filter-year');
        const reset = document.getElementById('consumption-reset');

        const apply = () => {
            const loc = location.value.trim().toLowerCase();
            const bt = blood.value;
            const mo = month.value;
            const yr = year.value;
            const typeTotals = {};
            let total = 0;
            let visible = 0;
            rows.forEach((row) => {
                const show = (!loc || row.dataset.location.includes(loc))
                    && (!bt || row.dataset.blood === bt)
                    && (!mo || row.dataset.month === mo)
                    && (!yr || row.dataset.year === yr);
                row.toggleAttribute('hidden', !show);
                if (show) {
                    visible += 1;
                    const bags = Number(row.dataset.bags);
                    total += bags;
                    typeTotals[row.dataset.blood] = (typeTotals[row.dataset.blood] || 0) + bags;
                }
            });
            if (empty) empty.toggleAttribute('hidden', visible !== 0);
            if (foot) foot.toggleAttribute('hidden', visible === 0);
            if (totalEl) totalEl.textContent = String(total);
            if (footTotal) footTotal.textContent = String(total);
            if (countEl) {
                let text = `${visible} record${visible === 1 ? '' : 's'}`;
                if (location.value.trim()) text += ` for "${location.value.trim()}"`;
                if (yr) text += ` in ${yr}`;
                countEl.textContent = text;
            }
            document.querySelectorAll('[data-summary-type]').forEach((card) => {
                const value = typeTotals[card.dataset.summaryType] || 0;
                card.toggleAttribute('hidden', value === 0);
                const strong = card.querySelector('strong');
                if (strong) strong.textContent = String(value);
            });
        };

        [location, blood, month, year].forEach((el) => el.addEventListener(el.tagName === 'SELECT' ? 'change' : 'input', apply));
        reset.addEventListener('click', () => {
            location.value = '';
            blood.value = '';
            month.value = '';
            year.value = '';
            apply();
        });
    }
});
