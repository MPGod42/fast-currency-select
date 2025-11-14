(function($) {
    $(function() {
        // Provide a small debug helper which respects the server-side debug toggle. Use this
        // instead of direct console.debug so we can keep verbose logging off in production.
        const fcsDebug = function() {
            if ( !(window.FastCurrencySelectData && window.FastCurrencySelectData.debug_enabled) ) return;
            if (window.console && window.console.debug) {
                window.console.debug.apply(window.console, arguments);
            }
        };
        // HPOS / NEW ORDERS SCREEN (admin.php?page=wc-orders)
        // Some admin pages may include orders via 'page=wc-orders' or 'post_type=shop_order'.
        if (window.location.href.indexOf('page=wc-orders') !== -1 || window.location.href.indexOf('post_type=shop_order') !== -1) {

            const addCurrencyDropdown = () => {
                const btn = document.querySelector(
                    'a.woocommerce-add-order, a.woocommerce-order-data-new__button, a.page-title-action'
                );
                if (btn) {
                    if (document.getElementById('fast-currency-select')) {
                        return true;
                    }

                    const currencySelect = document.createElement('select');
                    currencySelect.id = 'fast-currency-select';
                    currencySelect.name = 'currency';
                    currencySelect.setAttribute('aria-label', (window.FastCurrencySelectData && window.FastCurrencySelectData.select_currency) ? window.FastCurrencySelectData.select_currency : 'Select currency');

                    const defaultOption = document.createElement('option');
                    defaultOption.value = '';
                    defaultOption.textContent = (window.FastCurrencySelectData && window.FastCurrencySelectData.default_currency) ? window.FastCurrencySelectData.default_currency : 'Default Currency';
                    currencySelect.appendChild(defaultOption);

                    // wc_get_currencies returns an object of code => label. For the order dropdown we
                    // prefer the localized `wc_currencies` mapping if available and display "CODE — Label".
                    const wcCurrencies = (window.FastCurrencySelectData && window.FastCurrencySelectData.wc_currencies) || null;
                    const allowedCurrencies = (window.FastCurrencySelectData && window.FastCurrencySelectData.allowed_currencies) || [];
                    const filteredCurrencies = {};
                    if (wcCurrencies && allowedCurrencies.length) {
                        allowedCurrencies.forEach(code => {
                            if (wcCurrencies[code]) {
                                filteredCurrencies[code] = wcCurrencies[code];
                            }
                        });
                    }
                    if (Object.keys(filteredCurrencies).length) {
                        // Preserve the admin-configured order for allowed currencies so the
                        // dropdown reflects the same order as users configured in the table.
                        // We iterate over `allowedCurrencies` instead of sorting by label.
                        allowedCurrencies.forEach(code => {
                            if (filteredCurrencies[code]) {
                                const option = document.createElement('option');
                                option.value = code;
                                option.textContent = code + ' — ' + filteredCurrencies[code];
                                currencySelect.appendChild(option);
                            }
                        });
                    }

                    btn.parentNode.insertBefore(currencySelect, btn.nextSibling);

                    const originalHref = btn.getAttribute('href');

                    currencySelect.addEventListener('change', function() {
                        const selectedCurrency = currencySelect.value;

                        if (selectedCurrency) {
                            const url = new URL(originalHref || window.location.href);
                            url.searchParams.set('currency', selectedCurrency);
                            // Attach a nonce to harden this GET action against CSRF when actually executed.
                            // The nonce is created server-side and exposed via FastCurrencySelectData.set_currency_nonce
                            // — verify it in PHP before applying the currency change.
                            if (window.FastCurrencySelectData && window.FastCurrencySelectData.set_currency_nonce) {
                                url.searchParams.set('_wpnonce', window.FastCurrencySelectData.set_currency_nonce);
                            }
                            btn.setAttribute('href', url.toString());
                        } else {
                            btn.setAttribute('href', originalHref);
                        }
                    });

                    return true;
                }
                return false;
            };

            // Listen for updates to allowed currencies (saved from settings page) so
            // the dropdown on the Orders page can update in-place when changes are made
            // elsewhere in the admin without a full page reload.
            document.addEventListener('fcsAllowedCurrenciesUpdated', function(e) {
                try {
                    const select = document.getElementById('fast-currency-select');
                    if (!select) return;

                    // Preserve the first default option and clear any other options
                    while (select.options.length > 1) select.remove(1);

                    const wcCurrencies = (window.FastCurrencySelectData && window.FastCurrencySelectData.wc_currencies) || {};
                    const codes = (e && e.detail) ? e.detail : [];
                    codes.forEach(code => {
                        if (wcCurrencies[code]) {
                            const option = document.createElement('option');
                            option.value = code;
                            option.textContent = code + ' — ' + wcCurrencies[code];
                            select.appendChild(option);
                        }
                    });
                } catch (err) {
                    if (window.console && window.console.error) console.error('FCS: failed to update dropdown', err);
                }
            });

            if (!addCurrencyDropdown()) {
                const obs = new MutationObserver(() => {
                    if (addCurrencyDropdown()) {
                        obs.disconnect();
                    }
                });
                obs.observe(document.body, { childList: true, subtree: true });
            }

        }
        
        // SETTINGS PAGE: Add currency interactions (top and bottom buttons)
        const fcsTables = document.querySelectorAll('.fcs-currency-list');
        if (fcsTables.length) {
            fcsTables.forEach(table => {
                table.addEventListener('click', function(e) {
                    const addBtn = e.target.closest('.fcs-add-currency');
                    const remove = e.target.closest('.fcs-remove-currency');

                    if (remove) {
                        e.preventDefault();
                        const row = remove.closest('tr');
                        if (row) {
                            row.parentNode.removeChild(row);
                            // Reapply stripes and persist immediately
                            fcsUpdateTableStriping(table);
                            fcsSaveAllowedCurrencies();
                        }
                        return;
                    }

                    if (addBtn) {
                        e.preventDefault();

                        // If there is already an add row, focus it
                        const existing = table.querySelector('.fcs-add-row');
                        if (existing) {
                            const sel = existing.querySelector('select');
                            if (sel) sel.focus();
                            return;
                        }

                        const selectedCodes = Array.from(table.querySelectorAll('tr[data-code]')).map(r => r.dataset.code);
                        // Use WC currencies mapping exposed by PHP; do not fall back to a hard-coded list.
                        const allCurrencies = window.FastCurrencySelectData && window.FastCurrencySelectData.wc_currencies ? window.FastCurrencySelectData.wc_currencies : {};

                        // Create select with available options not already in table
                        const select = document.createElement('select');
                        select.className = 'fcs-add-select';

                        const placeholder = document.createElement('option');
                        placeholder.value = '';
                        placeholder.textContent = (window.FastCurrencySelectData && window.FastCurrencySelectData.select_currency) ? window.FastCurrencySelectData.select_currency : 'Select currency';
                        select.appendChild(placeholder);

                        Object.entries(allCurrencies).sort((a, b) => a[1].localeCompare(b[1])).forEach(([code, label]) => {
                            if (selectedCodes.indexOf(code) === -1) {
                                const option = document.createElement('option');
                                option.value = code;
                                option.textContent = code + ' — ' + label;
                                select.appendChild(option);
                            }
                        });

                        const addRow = document.createElement('tr');
                        addRow.className = 'fcs-add-row';
                        // Create separate columns for Enabled, Currency (select), Name (input) and Actions (buttons)
                        addRow.innerHTML = '<td><label><input type="checkbox" disabled /></label></td><td class="column-code"></td><td class="column-name"><input type="text" class="fcs-add-name" value="" placeholder="" /></td><td class="column-actions"></td><td class="column-order"></td>';
                        const codeCell = addRow.querySelector('.column-code');
                        const nameCell = addRow.querySelector('.column-name');
                        const actionsCell = addRow.querySelector('.column-actions');
                        const addBtnAction = document.createElement('button');
                        addBtnAction.type = 'button';
                        addBtnAction.className = 'button fcs-do-add';
                        addBtnAction.innerHTML = (window.FastCurrencySelectData && window.FastCurrencySelectData.add_text) ? window.FastCurrencySelectData.add_text : 'Add';

                        const cancelBtn = document.createElement('button');
                        cancelBtn.type = 'button';
                        cancelBtn.className = 'button fcs-cancel';
                        cancelBtn.textContent = 'Cancel';

                        // Put select in the Currency column
                        codeCell.appendChild(select);
                        // Name input already exists; leave empty initially
                        nameCell.querySelector('.fcs-add-name').value = '';

                        // Auto-populate name input when currency is selected
                        select.addEventListener('change', function() {
                            const selectedCode = select.value;
                            const nameInput = nameCell.querySelector('.fcs-add-name');
                            if (selectedCode && allCurrencies[selectedCode]) {
                                nameInput.value = allCurrencies[selectedCode];
                            } else {
                                nameInput.value = '';
                            }
                        });

                        // Put action buttons in Actions column
                        actionsCell.appendChild(addBtnAction);
                        actionsCell.appendChild(cancelBtn);

                        if (addBtn.dataset.position === 'top') {
                            const firstRow = table.querySelector('tbody tr:not(.fcs-action-row)');
                            if (firstRow) table.querySelector('tbody').insertBefore(addRow, firstRow);
                            else table.querySelector('tbody').appendChild(addRow);
                        } else {
                            table.querySelector('tbody').appendChild(addRow);
                        }

                        select.focus();

                        // Event delegation for add/cancel buttons
                        addRow.addEventListener('click', function(ev) {
                            const doAdd = ev.target.closest('.fcs-do-add');
                            const cancel = ev.target.closest('.fcs-cancel');
                            if (cancel) {
                                addRow.parentNode.removeChild(addRow);
                                return;
                            }
                            if (doAdd) {
                                const value = select.value;
                                if (!value || value.trim() === '') {
                                    if (window.console && window.console.error) {
                                        console.error('FCS: Empty or invalid select value!', value);
                                    }
                                    select.focus();
                                    return;
                                }
                                
                                const trimmedValue = value.trim();
                                
                                // Debug the selected value
                                fcsDebug('FCS: Adding currency, selected value from dropdown:', trimmedValue);
                                fcsDebug('FCS: Currency label from allCurrencies:', allCurrencies[trimmedValue]);
                                fcsDebug('FCS: Is value in allCurrencies object?', trimmedValue in allCurrencies);
                                
                                // Allow the admin to edit the display name for the currency in the Name field.
                                const nameInput = addRow.querySelector('.fcs-add-name');
                                const nameVal = nameInput ? nameInput.value.trim() : '';
                                const label = nameVal || allCurrencies[trimmedValue] || trimmedValue;
                                // Create new row with checked input
                                const newRow = document.createElement('tr');
                                newRow.setAttribute('data-code', trimmedValue);
                                
                                // Create checkbox properly to ensure value is set correctly
                                const checkbox = document.createElement('input');
                                checkbox.type = 'checkbox';
                                checkbox.name = 'fast_currency_select_allowed_currencies[]';
                                checkbox.value = trimmedValue;
                                checkbox.checked = true;
                                
                                const checkboxLabel = document.createElement('label');
                                checkboxLabel.appendChild(checkbox);
                                
                                const checkboxTd = document.createElement('td');
                                checkboxTd.appendChild(checkboxLabel);
                                
                                const codeTd = document.createElement('td');
                                codeTd.className = 'column-code';
                                codeTd.textContent = trimmedValue;
                                
                                const nameTd = document.createElement('td');
                                nameTd.className = 'column-name';
                                nameTd.textContent = label;
                                
                                const actionTd = document.createElement('td');
                                actionTd.className = 'column-actions';
                                const removeLink = document.createElement('a');
                                removeLink.href = '#';
                                removeLink.className = 'fcs-remove-currency';
                                removeLink.style.color = '#a00';
                                removeLink.textContent = (window.FastCurrencySelectData && window.FastCurrencySelectData.remove_text) ? window.FastCurrencySelectData.remove_text : 'Remove';
                                actionTd.appendChild(removeLink);
                                
                                const orderTd = document.createElement('td');
                                orderTd.className = 'column-order';
                                const dragHandle = document.createElement('span');
                                dragHandle.className = 'fcs-drag-handle dashicons dashicons-menu';
                                orderTd.appendChild(dragHandle);
                                
                                newRow.appendChild(checkboxTd);
                                newRow.appendChild(codeTd);
                                newRow.appendChild(nameTd);
                                newRow.appendChild(actionTd);
                                newRow.appendChild(orderTd);

                                // Insert before the first non-action row for selected items
                                const firstNonAction = table.querySelector('tbody tr:not(.fcs-action-row)');
                                if (firstNonAction) table.querySelector('tbody').insertBefore(newRow, firstNonAction);
                                else table.querySelector('tbody').appendChild(newRow);

                                // Remove addRow
                                addRow.parentNode.removeChild(addRow);

                                // Reapply stripes after adding
                                fcsUpdateTableStriping(table);

                                // Persist immediately
                                fcsSaveAllowedCurrencies();
                            }
                        });
                    }
                });

                // Save on checkbox changes (enable/disable)
                table.addEventListener('change', function(e) {
                    const chk = e.target.closest('input[name="fast_currency_select_allowed_currencies[]"]');
                    if (chk) {
                        fcsUpdateTableStriping(table);
                        fcsSaveAllowedCurrencies();
                    }
                });

                // Initialize sortable for reordering
                $(table).find('tbody').sortable({
                    handle: '.fcs-drag-handle',
                    items: 'tr:not(.fcs-add-row)',
                    containment: 'tbody',
                    axis: 'y',
                    // Append helper to the table itself to avoid absolute positioning differences
                    appendTo: $(table),
                    tolerance: 'pointer',
                    // Use a helper clone and preserve each cell width so the row doesn't collapse
                    helper: function(e, tr) {
                        const $originals = tr.children();
                        const $helper = tr.clone();
                        $helper.children().each(function(index) {
                            // Preserve cell width from the original row to keep layout
                            $(this).width($originals.eq(index).width());
                        });
                        // Ensure the helper uses the same width as the table so it doesn't overflow
                        const tableWidth = tr.closest('table').outerWidth();
                        $helper.css({
                            boxSizing: 'border-box',
                            margin: 0,
                            width: tableWidth
                        });
                        // Keep helper as a table row to maintain consistent rendering
                        $helper.addClass('ui-sortable-helper');
                        return $helper.get(0);
                    },
                    start: function(e, ui) {
                        // Ensure placeholder cells match widths to avoid layout shift
                        ui.placeholder.children().each(function(index) {
                            $(this).width(ui.item.children().eq(index).width());
                        });
                        // Also ensure the helper has full width
                        ui.helper.css({
                            display: 'table',
                            boxSizing: 'border-box',
                            width: ui.item.closest('table').outerWidth(),
                            margin: 0
                        });
                        // Ensure helper uses the right stripe color on drag start
                        fcsUpdateDragHelper(table, ui.helper);
                    },
                    // Live-updating stripes while dragging
                    sort: function(e, ui) {
                        fcsUpdateTableStriping(table);
                        // Update the helper color to match the placeholder
                        fcsUpdateDragHelper(table, ui.helper);
                    },
                    update: function() {
                        // Ensure stripes are updated when the DOM order has changed
                        fcsUpdateTableStriping(table);
                        fcsSaveAllowedCurrencies();
                    }
                });
            });
        }

        // Save allowed currencies via AJAX helper
        let fcsSaving = false;
        const fcsSaveAllowedCurrencies = () => {
            if (fcsSaving) return;
            if ( ! (window.FastCurrencySelectData && window.FastCurrencySelectData.ajax_url && window.FastCurrencySelectData.save_nonce) ) {
                return;
            }

            const codes = Array.from(document.querySelectorAll('input[name="fast_currency_select_allowed_currencies[]"]')).filter(i => i.checked).map(i => i.value);
            // Debug: log what we're sending
            fcsDebug('FCS: saving currencies ->', codes);
            fcsDebug('FCS: available currencies from WC ->', window.FastCurrencySelectData.wc_currencies ? Object.keys(window.FastCurrencySelectData.wc_currencies) : []);
            
            // Detailed check for each code
            if (window.FastCurrencySelectData.wc_currencies) {
                const wcKeys = Object.keys(window.FastCurrencySelectData.wc_currencies);
                codes.forEach(code => {
                    const found = wcKeys.find(k => k === code);
                    fcsDebug(`FCS: Code "${code}" in WC currencies?`, !!found);
                });
            }

            const params = new URLSearchParams();
            params.append('action', 'fcs_save_allowed_currencies');
            params.append('nonce', window.FastCurrencySelectData.save_nonce);
            codes.forEach(c => params.append('currencies[]', c));

            // Show saving state
            // const noticeWrap = document.querySelector('.fcs-currencies-wrap') || document.body;
            // const notice = document.createElement('div');
            // notice.className = 'fcs-notice saving';
            // notice.textContent = (window.FastCurrencySelectData && window.FastCurrencySelectData.saving_text) ? window.FastCurrencySelectData.saving_text : 'Saving…';
            // noticeWrap.insertBefore(notice, noticeWrap.firstChild);

            fcsSaving = true;
            fetch(window.FastCurrencySelectData.ajax_url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: params.toString()
            }).then(res => res.json()).then(data => {
                // Remove saving state
                // if (notice && notice.parentNode) notice.parentNode.removeChild(notice);

                if ( ! data || typeof data !== 'object' ) {
                    alert('Error: Unexpected response from server.');
                    return;
                }

                fcsSaving = false;
                if ( data.success ) {
                    // Small transient success message
                    // const ok = document.createElement('div');
                    // ok.className = 'fcs-notice success';
                    // ok.textContent = (window.FastCurrencySelectData && window.FastCurrencySelectData.saved_text) ? window.FastCurrencySelectData.saved_text : 'Saved';
                    // noticeWrap.insertBefore(ok, noticeWrap.firstChild);
                    // setTimeout(() => { if (ok && ok.parentNode) ok.parentNode.removeChild(ok); }, 1600);
                } else {
                    const msg = (data.data && data.data.message) ? data.data.message : 'Save failed';
                    // If backend returns 'available' and 'payload', show in console for debugging
                    if (data.data && data.data.available) {
                        if (window.console && window.console.warn) {
                            console.warn('FCS: Validation Error!');
                            console.warn('FCS: Currencies sent ->', data.data.payload);
                            console.warn('FCS: Valid WooCommerce currencies ->', data.data.available);
                            console.warn('FCS: Mismatch detected - fetching debug info...');
                        }
                        // Fetch debug info
                        // Include debug nonce when submitting debug info to protect the endpoint
                        if ( ! (window.FastCurrencySelectData && window.FastCurrencySelectData.debug_nonce) ) {
                            if (window.console && window.console.warn) console.warn('FCS: debug_nonce missing; cannot request debug info');
                        } else {
                            fetch(window.FastCurrencySelectData.ajax_url, {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                                body: new URLSearchParams({ action: 'fcs_debug_currencies', nonce: window.FastCurrencySelectData.debug_nonce }).toString()
                            }).then(res => res.json()).then(debugData => {
                            if (debugData.success && debugData.data) {
                                console.warn('FCS: Debug Info:', debugData.data);
                                console.warn('FCS: Check that your selected currency code matches exactly (case-sensitive in WC)');
                            }
                            });
                        }
                    }
                    alert('Error: ' + msg);
                }
                // Fire a document-level event with the saved order so external pages
                // (e.g., Orders) can update their dropdowns if present. The saved list
                // is in data.data.saved per server response.
                if ( data && data.data && data.data.saved ) {
                    try {
                        document.dispatchEvent(new CustomEvent('fcsAllowedCurrenciesUpdated', { detail: data.data.saved }));
                    } catch (e) {
                        // Silently ignore if CustomEvent isn't supported in some older browsers
                    }
                }
            })
            // Add basic error handling
            .catch(() => {
                fcsSaving = false;
                // if (notice && notice.parentNode) notice.parentNode.removeChild(notice);
                alert('Network error while saving. Please try again.');
            });
        };

        // Helper to apply zebra striping to a table's rows - updates live during drag
        const fcsUpdateTableStriping = (tableEl) => {
            if (!tableEl) return;

            const tbody = tableEl.querySelector('tbody');
            if (!tbody) return;

            // Collect the rows which should be striped (ignore add-row/action rows)
            const rows = Array.from(tbody.querySelectorAll('tr:not(.fcs-add-row):not(.fcs-action-row)'));

            // Apply odd/even classes — index 0 => first row will be considered odd
            rows.forEach((r, i) => {
                r.classList.toggle('fcs-odd', i % 2 === 0);
                r.classList.toggle('fcs-even', i % 2 === 1);
            });

            // Update placeholder too so you can preview stripes while dragging
            const placeholder = tbody.querySelector('.ui-sortable-placeholder');
            if (placeholder) {
                // Get the index where the placeholder is located
                const all = Array.from(tbody.querySelectorAll('tr:not(.fcs-action-row)'));
                const idx = all.indexOf(placeholder);
                if (idx !== -1) {
                    placeholder.classList.toggle('fcs-odd', idx % 2 === 0);
                    placeholder.classList.toggle('fcs-even', idx % 2 === 1);
                }
            }
        };

        // Helper to update the drag helper's stripe class so the dragged piece matches
        // the placeholder. This is run from start/sort/update events.
        const fcsUpdateDragHelper = (tableEl, helper) => {
            if (!tableEl || !helper) return;

            // jQuery or DOM node -> normalize
            const helperNode = helper && helper.jquery ? helper.get(0) : helper;
            if (!helperNode) return;

            const tbody = tableEl.querySelector('tbody');
            if (!tbody) return;

            // Find the placeholder index among visible rows
            const rows = Array.from(tbody.querySelectorAll('tr:not(.fcs-add-row):not(.fcs-action-row):not(.ui-sortable-helper)'));
            const placeholder = tbody.querySelector('.ui-sortable-placeholder');

            let idx = -1;
            if (placeholder) {
                // If placeholder is present, use that
                const rowsWithPlaceholder = Array.from(tbody.querySelectorAll('tr:not(.fcs-action-row)'));
                idx = rowsWithPlaceholder.indexOf(placeholder);
            } else {
                // Fallback: find the index of the row we started dragging
                const item = tbody.querySelector('.ui-sortable-helper');
                idx = rows.indexOf(item);
            }

            // Clear previous helper classes
            helperNode.classList.toggle('fcs-odd', false);
            helperNode.classList.toggle('fcs-even', false);

            if (idx === -1) return;

            // Toggle classes to match striping (same logic as rows)
            helperNode.classList.toggle('fcs-odd', idx % 2 === 0);
            helperNode.classList.toggle('fcs-even', idx % 2 === 1);
        };

        // Make sure tables are striped on initial render
        document.querySelectorAll('.fcs-currency-list').forEach(t => fcsUpdateTableStriping(t));

        // Also support clicking the single Add button in the page title (outside the table)
        document.addEventListener('click', function(e) {
            const addBtn = e.target.closest('.fcs-add-currency');
            if (!addBtn) return;

            // If the add button is already inside the table, the table's handler will manage it.
            // Here we only handle the page title button.
            const isInsideTable = !!addBtn.closest('.fcs-currency-list');
            if (isInsideTable) return;

            // Find our first visible table on the page
            const table = document.querySelector('.fcs-currency-list');
            if (!table) return;

            // Re-use the same click logic by programmatically dispatching a click on a virtual
            // add button with dataset.position === 'top'. We create a temporary element inside the table
            // to trigger the existing handler; this keeps behavior consistent.
            const virtual = document.createElement('button');
            virtual.className = 'fcs-add-currency';
            virtual.dataset.position = addBtn.dataset.position || 'top';

            // Insert virtual before first row so event handler will add the inline addRow in the proper place
            table.querySelector('tbody').insertBefore(virtual, table.querySelector('tbody').firstChild);

            // Dispatch a click on the virtual button that will be handled by the table's event listener.
            virtual.click();

            // Remove the temporary trigger
            virtual.parentNode.removeChild(virtual);
        });
    });
})(jQuery);
