/**
 * Centreon Form Library — Shared JS for modernized configuration forms.
 *
 * Provides reusable UI components for the redesigned Smarty form templates:
 * - Side panel (drawer) management
 * - Accordion sections with anchor navigation
 * - Floating labels on text inputs
 * - Segmented buttons (Default / Yes / No) synced with QuickForm radio groups
 * - Chip selection (toggleable tags) synced with QuickForm checkboxes
 * - Toggle switches synced with QuickForm radio groups
 * - Custom hover tooltips replacing wz_tooltip
 * - Macro row cleanup for sheepIt clone forms
 * - Geo coordinates address autocomplete via Nominatim
 *
 * Usage in a form template (.ihtml):
 *
 *   // Initialize all form components
 *   CentreonForm.initFormPage();
 *
 *   // Initialize side panel on a listing page
 *   CentreonForm.initSidePanel(listingInstance);
 *
 * @copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 * @license Apache-2.0
 */
var CentreonForm = (function () {
    'use strict';

    // =========================================================================
    //  SIDE PANEL (Drawer)
    //  Opens a form in a slide-in panel from the right side of the listing page.
    //  The form loads in an iframe so all QuickForm JS works natively.
    // =========================================================================

    /** @private localStorage key used to remember the side panel's last width */
    var SIDE_PANEL_WIDTH_KEY = 'cf_side_panel_width';

    /** @private Read the last resized width from localStorage, if any */
    function getSavedSidePanelWidth() {
        try {
            var stored = parseInt(window.localStorage.getItem(SIDE_PANEL_WIDTH_KEY), 10);

            return stored > 0 ? stored : null;
        } catch (e) {
            // localStorage can throw (e.g. private browsing) — just skip restoring
            return null;
        }
    }

    /** @private Persist the current side panel width to localStorage */
    function saveSidePanelWidth(width) {
        try {
            window.localStorage.setItem(SIDE_PANEL_WIDTH_KEY, width);
        } catch (e) {
            // Ignore: not critical if the width isn't remembered
        }
    }

    /**
     * Open the side panel with a form URL.
     *
     * @param {string} url   - The URL to load in the panel iframe (e.g. main.get.php?p=60101&o=c&host_id=14)
     * @param {string} title - The title displayed in the panel header (e.g. "Modify Host - central")
     */
    function openPanel(url, title) {
        var titleEl = document.getElementById('cfSidePanelTitle');
        var frameEl = document.getElementById('cfSidePanelFrame');
        var overlay = document.getElementById('cfSideOverlay');
        var panel   = document.getElementById('cfSidePanel');

        if (!titleEl || !frameEl || !overlay || !panel) {
            return;
        }

        titleEl.textContent = title || '';
        frameEl.src = url;
        overlay.classList.add('open');
        panel.classList.add('open');
    }

    /**
     * Close the side panel and refresh the listing.
     *
     * @param {object} [listingInstance] - The CentreonListing instance to refresh after close.
     *                                     If null, no refresh is performed.
     */
    function closePanel(listingInstance) {
        // Use the stored listing reference if none provided
        var listing = listingInstance || _sidePanelListing;

        var overlay = document.getElementById('cfSideOverlay');
        var panel   = document.getElementById('cfSidePanel');
        var frameEl = document.getElementById('cfSidePanelFrame');

        if (!overlay || !panel) {
            return;
        }

        overlay.classList.remove('open');
        panel.classList.remove('open');

        // Reset iframe after the CSS transition completes (300ms)
        setTimeout(function () {
            if (frameEl) {
                frameEl.src = 'about:blank';
            }
        }, 300);

        // Silently refresh the listing data
        if (listing && typeof listing.getState === 'function') {
            var state = listing.getState();
            listing.fetch(state.num, state.limit, state.search, true);
        }
    }

    /**
     * Initialize the side panel: resize handle, Escape key, overlay click.
     * Call this once on each listing page that uses a side panel.
     *
     * @param {object} listingInstance - The CentreonListing instance to refresh on close.
     */
    function initSidePanel(listingInstance) {
        // Store the listing reference for closePanel calls
        _sidePanelListing = listingInstance;
        CentreonForm._sidePanelListing = listingInstance;

        // --- Resize handle (drag left edge to resize) ---
        var handle  = document.getElementById('cfSidePanelResize');
        var panel   = document.getElementById('cfSidePanel');
        var dragging = false;

        if (handle && panel) {
            // Restore the width the user picked last time, if any.
            var savedWidth = getSavedSidePanelWidth();
            if (savedWidth) {
                panel.style.width = savedWidth + 'px';
            }

            handle.addEventListener('mousedown', function (e) {
                e.preventDefault();
                dragging = true;
                handle.classList.add('active');
                document.body.style.cursor = 'col-resize';

                // Disable iframe pointer events during drag to prevent mouse capture
                var iframe = document.getElementById('cfSidePanelFrame');
                if (iframe) {
                    iframe.style.pointerEvents = 'none';
                }
            });

            document.addEventListener('mousemove', function (e) {
                if (!dragging) return;
                var width = window.innerWidth - e.clientX;
                if (width < 400) width = 400;
                if (width > window.innerWidth * 0.95) width = window.innerWidth * 0.95;
                panel.style.width = width + 'px';
            });

            document.addEventListener('mouseup', function () {
                if (!dragging) return;
                dragging = false;
                handle.classList.remove('active');
                document.body.style.cursor = '';

                var iframe = document.getElementById('cfSidePanelFrame');
                if (iframe) {
                    iframe.style.pointerEvents = '';
                }

                saveSidePanelWidth(parseInt(panel.style.width, 10));
            });
        }

        // --- Close on Escape key ---
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closePanel(listingInstance);
            }
        });
    }

    /** @private Reference to the listing instance for closePanel */
    var _sidePanelListing = null;

    /**
     * Detect if the current page is loaded inside the side panel iframe.
     * If so, close the parent's panel (used after a form save redirects to the listing).
     *
     * Call this at the top of every listing template.
     */
    function detectSidePanelClose() {
        if (window.frameElement && window.frameElement.id === 'cfSidePanelFrame') {
            try {
                var parentForm = window.parent.CentreonForm;
                parentForm.closePanel(parentForm._sidePanelListing);
            } catch (e) {
                // Silently ignore — may fail on cross-origin or missing lib
            }
        }
    }

    // =========================================================================
    //  ACCORDION SECTIONS
    //  Collapsible sections with a header bar. Click to expand/collapse.
    // =========================================================================

    /**
     * Toggle an accordion section open/closed.
     * The section element gets the CSS class "collapsed" which hides its body.
     *
     * @param {HTMLElement} header - The .cf-section-header element that was clicked.
     */
    function toggleSection(header) {
        var section = header.parentElement;
        if (section) {
            section.classList.toggle('collapsed');
        }
    }

    /**
     * Smooth-scroll to a section and expand it if collapsed.
     * Used by the tab navigation anchors at the top of the form.
     *
     * @param {string}      sectionId   - The DOM id of the target section (e.g. "cf-sec-basic").
     * @param {HTMLElement}  clickedLink - The anchor element that was clicked (to update active state).
     */
    function scrollTo(sectionId, clickedLink) {
        var section = document.getElementById(sectionId);
        if (section) {
            // Expand if currently collapsed
            if (section.classList.contains('collapsed')) {
                section.classList.remove('collapsed');
            }
            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        // Update active state on all tab nav links
        document.querySelectorAll('.cf-tab-nav a').forEach(function (a) {
            a.classList.remove('active');
        });
        if (clickedLink) {
            clickedLink.classList.add('active');
        }
    }

    // =========================================================================
    //  FLOATING LABELS
    //  Labels that float above the input when it has a value or is focused.
    //  The label gets the CSS class "cf-label-float" to trigger the animation.
    // =========================================================================

    /**
     * Initialize floating labels on all .cf-field inputs and textareas.
     * - On page load: float labels for pre-filled inputs
     * - On focus: float the label
     * - On blur: un-float if the input is empty
     */
    function initFloatLabels() {
        // The templates render every field label with the .cf-label-float class,
        // so labels sit on the top border at all times. We keep them there and no
        // longer drop them back into the field on blur (which looked inconsistent
        // once a field had been focused). This just guarantees the class is set.
        document.querySelectorAll('.cf-field input, .cf-field textarea').forEach(function (input) {
            var label = input.parentElement.querySelector('label');
            if (label) label.classList.add('cf-label-float');
        });
    }

    // =========================================================================
    //  CUSTOM TOOLTIPS
    //  Hover tooltips that replace the legacy wz_tooltip / TagToTip system.
    //  Positioned above the icon, dark background, auto-dismiss on mouseout.
    // =========================================================================

    /**
     * Initialize custom hover tooltips on all .helpTooltip elements.
     * Reads the help text from hidden <span id="help:{name}"> elements
     * generated by the PHP help.php include.
     *
     * @param {number} [delay=500] - Delay in ms before showing the tooltip.
     */
    function initTooltips(delay) {
        delay = delay || 500;

        // Wait for the footer's CentreonToolTip.render() to finish
        setTimeout(function () {
            var hoverTimer = null;
            var tipEl = null;

            jQuery('.helpTooltip')
                .off('click')
                .css('cursor', 'help')
                .on('mouseenter', function () {
                    var self = this;
                    hoverTimer = setTimeout(function () {
                        var helpSpan = document.getElementById('help:' + jQuery(self).attr('name'));
                        if (!helpSpan) return;

                        // Remove existing tooltip
                        if (tipEl) tipEl.remove();

                        // Create tooltip element
                        tipEl = document.createElement('div');
                        tipEl.className = 'cf-tooltip';
                        tipEl.innerHTML = helpSpan.innerHTML;
                        document.body.appendChild(tipEl);

                        // Position above the icon, centered, with edge detection
                        var rect = self.getBoundingClientRect();
                        var tipH = tipEl.offsetHeight;
                        var tipW = tipEl.offsetWidth;

                        var top = rect.top - tipH - 8;
                        if (top < 4) top = rect.bottom + 8; // Flip below if no room above

                        var left = rect.left + rect.width / 2 - tipW / 2;
                        if (left < 4) left = 4;
                        if (left + tipW > window.innerWidth - 4) {
                            left = window.innerWidth - tipW - 4;
                        }

                        tipEl.style.top = top + 'px';
                        tipEl.style.left = left + 'px';
                        tipEl.style.opacity = '1';
                    }, delay);
                })
                .on('mouseleave', function () {
                    clearTimeout(hoverTimer);
                    if (tipEl) {
                        tipEl.remove();
                        tipEl = null;
                    }
                });
        }, 500);
    }

    // =========================================================================
    //  SEGMENTED BUTTONS (Default / Yes / No)
    //  Replace QuickForm radio groups with a row of toggle buttons.
    //  The active button gets the "active" CSS class and the hidden radio
    //  is updated to match.
    // =========================================================================

    /**
     * Initialize all segmented button groups on the page.
     * Each group is a .cf-segmented element with data-radio-name attribute
     * pointing to the QuickForm radio group name.
     *
     * QuickForm generates radio names as either:
     *   - name="fieldname[fieldname]" (for addGroup)
     *   - name="fieldname" (for simple radios)
     * This function tries both patterns.
     */
    function initSegmentedButtons() {
        document.querySelectorAll('.cf-segmented:not([data-cf-auto])').forEach(function (group) {
            var radioName = group.dataset.radioName;
            var buttons = group.querySelectorAll('button');

            buttons.forEach(function (btn) {
                var val = btn.dataset.value;

                // Find the matching radio button (try both naming patterns)
                var radio = _findRadio(radioName, val);

                // Set initial active state from the checked radio
                if (radio && radio.checked) {
                    btn.classList.add('active');
                }

                // Click handler: update visual state and hidden radio
                btn.addEventListener('click', function () {
                    buttons.forEach(function (b) {
                        b.classList.remove('active');
                    });
                    this.classList.add('active');

                    var r = _findRadio(radioName, val);
                    if (r) {
                        r.checked = true;
                        // Notify dependent handlers bound to the underlying radio
                        try { r.dispatchEvent(new Event('change', { bubbles: true })); } catch (e) {}
                        try { r.dispatchEvent(new Event('click', { bubbles: true })); } catch (e) {}
                    }
                });
            });
        });
    }

    // =========================================================================
    //  AUTO YES/NO/DEFAULT SEGMENTS
    //  Convert visible QuickForm radio groups (.md-radio with values in {0,1,2})
    //  into segmented controls, consistently with the host form. Hidden radios
    //  (host's explicit segments, cl-toggle activate fields) are skipped via the
    //  visibility check, so this never double-processes them.
    // =========================================================================
    function initYesNoSegments() {
        var wrapper = document.querySelector('.cf-form-wrapper');
        if (!wrapper) return;

        // Group visible radios by their (field, name)
        var groups = [];
        var index = {};
        wrapper.querySelectorAll('.md-radio input[type="radio"]').forEach(function (input) {
            var mdRadio = input.closest('.md-radio');
            if (!mdRadio) return;
            // Skip hidden radios (host explicit segments, activate toggles, hidden tabs)
            if (mdRadio.offsetParent === null) return;
            var field = input.closest('.cf-field') || input.closest('.cf-segmented-row');
            if (!field || field.classList.contains('cf-segmented-row')) return;
            if (field.querySelector('.cf-segmented')) return;

            var key = field.dataset.cfRid || (field.dataset.cfRid = String(groups.length) + ':' + input.name);
            if (!(key in index)) {
                index[key] = groups.length;
                groups.push({ field: field, name: input.name, items: [] });
            }
            groups[index[key]].items.push({ input: input, mdRadio: mdRadio });
        });

        groups.forEach(function (g) {
            try {
                if (g.items.length < 2 || g.items.length > 3) return;
                // Only yes/no/default style groups (values within {0,1,2})
                var ok = g.items.every(function (it) {
                    return ['0', '1', '2'].indexOf(String(it.input.value)) !== -1;
                });
                if (!ok) return;

                var base = g.name.replace(/\[.*\]$/, '');

                // Build segmented buttons, ordered Yes(1), No(0), Default(2) when present
                var byVal = {};
                g.items.forEach(function (it) {
                    var lbl = it.mdRadio.querySelector('label');
                    var text = lbl ? lbl.textContent.trim() : '';
                    if (!text) text = ({ '2': 'Default', '1': 'Yes', '0': 'No' })[it.input.value] || it.input.value;
                    byVal[it.input.value] = text;
                });
                var order = ['1', '0', '2'].filter(function (v) { return v in byVal; });

                var seg = document.createElement('div');
                seg.className = 'cf-segmented';
                seg.setAttribute('data-radio-name', base);
                seg.setAttribute('data-cf-auto', '1');   // self-wired: skipped by initSegmentedButtons
                order.forEach(function (v) {
                    var b = document.createElement('button');
                    b.type = 'button';
                    b.setAttribute('data-value', v);
                    b.textContent = byVal[v];
                    seg.appendChild(b);
                });

                // Wire the buttons immediately (do not rely on a second init pass)
                var btns = seg.querySelectorAll('button');
                Array.prototype.forEach.call(btns, function (btn) {
                    var v = btn.getAttribute('data-value');
                    var radio = _findRadio(base, v);
                    if (radio && radio.checked) btn.classList.add('active');
                    btn.addEventListener('click', function () {
                        Array.prototype.forEach.call(btns, function (b) { b.classList.remove('active'); });
                        btn.classList.add('active');
                        var r = _findRadio(base, v);
                        if (r) {
                            r.checked = true;
                            try { r.dispatchEvent(new Event('change', { bubbles: true })); } catch (e) {}
                            try { r.dispatchEvent(new Event('click', { bubbles: true })); } catch (e) {}
                        }
                    });
                });

                // Reuse the field's float label as the inline segment label, keep help icon
                // The field's real parameter label = a cf-label-float that is NOT a radio's
                // own label (those live inside .md-radio).
                var floatLabel = null;
                var candidates = g.field.querySelectorAll('label.cf-label-float');
                Array.prototype.forEach.call(candidates, function (l) {
                    if (!floatLabel && !l.closest('.md-radio')) floatLabel = l;
                });
                var labelText = floatLabel ? floatLabel.textContent.trim() : '';
                var help = g.field.querySelector('img.helpTooltip');
                var row = document.createElement('div');
                row.className = 'cf-segmented-row';
                if (labelText) {
                    var span = document.createElement('span');
                    span.textContent = labelText;
                    row.appendChild(span);
                }
                row.appendChild(seg);
                if (help) row.appendChild(help);

                // Move ALL original field content (radios, their labels, separators and any
                // stray text node) into a hidden holder so nothing leaks before the segmented
                // control (e.g. a dangling "Yes" label). Radios stay in the DOM and submit.
                var holder = document.createElement('span');
                holder.style.display = 'none';
                while (g.field.firstChild) {
                    holder.appendChild(g.field.firstChild);
                }
                g.field.appendChild(holder);
                g.field.insertBefore(row, g.field.firstChild);
            } catch (e) { /* leave this field as plain radios on any error */ }
        });
    }

    // =========================================================================
    //  CHIP SELECTION (toggleable tags)
    //  Replace QuickForm checkbox groups with pill-shaped toggle buttons.
    //  Supports exclusive options (e.g. "None" unchecks all others).
    // =========================================================================

    /**
     * Initialize chip selection on all .cf-chip elements.
     * Each chip has a data-checkbox attribute pointing to the checkbox ID.
     *
     * @param {string} [exclusiveChip] - The data-checkbox value of the exclusive option
     *                                    (e.g. "notifN"). Selecting it unchecks all others.
     */
    function initChips(exclusiveChip) {
        document.querySelectorAll('.cf-chip').forEach(function (chip) {
            var cbId = chip.dataset.checkbox;
            var cb = document.getElementById(cbId);

            // Set initial active state
            if (cb && cb.checked) {
                chip.classList.add('active');
            }

            chip.addEventListener('click', function () {
                if (exclusiveChip && cbId === exclusiveChip) {
                    // Exclusive chip: uncheck all others
                    document.querySelectorAll('.cf-chip').forEach(function (c) {
                        if (c.dataset.checkbox !== exclusiveChip) {
                            c.classList.remove('active');
                            var other = document.getElementById(c.dataset.checkbox);
                            if (other) other.checked = false;
                        }
                    });
                } else if (exclusiveChip) {
                    // Non-exclusive chip: uncheck the exclusive one
                    var exChip = document.querySelector('.cf-chip[data-checkbox="' + exclusiveChip + '"]');
                    if (exChip) exChip.classList.remove('active');
                    var exCb = document.getElementById(exclusiveChip);
                    if (exCb) exCb.checked = false;
                }

                // Toggle this chip
                this.classList.toggle('active');
                if (cb) cb.checked = this.classList.contains('active');
            });
        });
    }

    // =========================================================================
    //  AUTO CHECKBOX CHIPS
    //  Convert visible QuickForm checkbox GROUPS (2+ .md-checkbox in one field —
    //  e.g. notification/escalation/stalking options Up/Down/Unreachable/…) into
    //  clickable chips, like the host form. Single checkboxes are left untouched.
    //  Hidden checkboxes (host explicit chips) are skipped via the visibility check.
    // =========================================================================
    function initCheckboxChips() {
        var wrapper = document.querySelector('.cf-form-wrapper');
        if (!wrapper) return;

        var groups = [];
        var fields = [];
        wrapper.querySelectorAll('.md-checkbox input[type="checkbox"]').forEach(function (input) {
            var mdc = input.closest('.md-checkbox');
            if (!mdc || mdc.offsetParent === null) return;            // skip hidden
            if (input.closest('.clonable') || input.closest('.macroclone')) return; // skip clone/macro rows
            var field = input.closest('.cf-field');
            if (!field || field.querySelector('.cf-chips')) return;
            var idx = fields.indexOf(field);
            if (idx === -1) { fields.push(field); groups.push({ field: field, items: [] }); idx = groups.length - 1; }
            groups[idx].items.push({ input: input, mdc: mdc });
        });

        groups.forEach(function (g) {
            try {
                if (g.items.length < 2) return;   // only multi-option groups become chips

                var chips = document.createElement('div');
                chips.className = 'cf-chips';
                chips.setAttribute('data-cf-auto', '1');

                // Field's real parameter label (not a checkbox's own label)
                var floatLabel = null;
                Array.prototype.forEach.call(g.field.querySelectorAll('label.cf-label-float'), function (l) {
                    if (!floatLabel && !l.closest('.md-checkbox')) floatLabel = l;
                });
                var labelText = floatLabel ? floatLabel.textContent.trim() : '';
                if (labelText) {
                    var lab = document.createElement('span');
                    lab.className = 'cf-chips-label';
                    lab.textContent = labelText;
                    chips.appendChild(lab);
                }
                var help = g.field.querySelector('img.helpTooltip');
                if (help) chips.appendChild(help);

                // "None" option is exclusive, like the host form. Its element NAME is 'n'
                // (rendered as name="group[n]", id like "notifN"); the value attribute is "1".
                var exclusive = null;
                g.items.forEach(function (it) {
                    if (/\[n\]$/i.test(it.input.name || '') || /N$/.test(it.input.id || '')) exclusive = it.input;
                });

                g.items.forEach(function (it) {
                    var lbl = it.mdc.querySelector('label');
                    var chip = document.createElement('span');
                    chip.className = 'cf-chip';
                    chip.textContent = lbl ? lbl.textContent.trim() : (it.input.value || '');
                    if (it.input.checked) chip.classList.add('active');
                    chip.addEventListener('click', function () {
                        var isExclusive = (it.input === exclusive);
                        if (exclusive && isExclusive) {
                            g.items.forEach(function (o) {
                                if (o.input !== exclusive) { o.input.checked = false; o.chipEl.classList.remove('active'); }
                            });
                        } else if (exclusive) {
                            exclusive.checked = false;
                            if (exclusive.chipEl) exclusive.chipEl.classList.remove('active');
                        }
                        this.classList.toggle('active');
                        it.input.checked = this.classList.contains('active');
                        try { it.input.dispatchEvent(new Event('change', { bubbles: true })); } catch (e) {}
                    });
                    it.chipEl = chip;
                    it.input.chipEl = chip;
                    chips.appendChild(chip);
                });

                // Hide original checkboxes/labels behind a holder; show the chips
                var holder = document.createElement('span');
                holder.style.display = 'none';
                while (g.field.firstChild) { holder.appendChild(g.field.firstChild); }
                g.field.appendChild(holder);
                g.field.insertBefore(chips, g.field.firstChild);
            } catch (e) { /* leave as plain checkboxes on error */ }
        });
    }

    // =========================================================================
    //  TOGGLE SWITCH SYNC
    //  Sync an iPhone-style toggle (cl-toggle) with hidden QuickForm radios.
    // =========================================================================

    /**
     * Sync a toggle switch checkbox with a QuickForm radio group.
     *
     * @param {string} toggleId  - The DOM id of the toggle <input type="checkbox">
     * @param {string} radioName - The QuickForm radio group name
     * @param {string} onValue   - The radio value when toggle is ON (usually "1")
     * @param {string} offValue  - The radio value when toggle is OFF (usually "0")
     */
    function syncToggle(toggleId, radioName, onValue, offValue) {
        var toggle = document.getElementById(toggleId);
        var radioOn  = _findRadio(radioName, onValue);
        var radioOff = _findRadio(radioName, offValue);

        if (!toggle || !radioOn) return;

        // Set initial state from the checked radio
        toggle.checked = radioOn.checked;

        // Update hidden radio on toggle change
        toggle.addEventListener('change', function () {
            if (this.checked) {
                radioOn.checked = true;
            } else if (radioOff) {
                radioOff.checked = true;
            }
        });

        // Keep the toggle in sync when the form is reset: the toggle checkbox
        // has no default checked state, so a native reset would always turn it
        // off. Re-read the (post-reset) radio value on the next tick.
        var form = toggle.form || radioOn.form;
        if (form) {
            form.addEventListener('reset', function () {
                setTimeout(function () {
                    toggle.checked = radioOn.checked;
                }, 0);
            });
        }
    }

    // =========================================================================
    //  MACRO ROW CLEANUP
    //  Restructures sheepIt macro clone rows for a cleaner layout.
    //  Removes inline text labels, adds placeholders, wraps action icons.
    // =========================================================================

    /**
     * Clean up macro clone rows generated by cloneMacro.ihtml + sheepIt.
     * - Removes text node labels ("Name", "Value", "Password")
     * - Adds placeholder text to Name and Value inputs
     * - Wraps trailing action icons in an aligned flex container
     *
     * Automatically re-runs when sheepIt adds or refreshes rows
     * via a MutationObserver.
     */
    function initMacroCleanup() {
        _cleanMacroRows();

        // Watch for sheepIt adding new rows
        var macroList = document.querySelector('ul.macroclone');
        if (macroList) {
            var observer = new MutationObserver(function () {
                setTimeout(_cleanMacroRows, 100);
            });
            observer.observe(macroList, { childList: true, subtree: true });
        }
    }

    /** @private Clean all macro rows */
    function _cleanMacroRows() {
        document.querySelectorAll('.macroclone .onemacro .clone-cell').forEach(function (cell) {
            if (cell.dataset.cfProcessed) return;
            cell.dataset.cfProcessed = '1';

            // Remove text nodes from label spans
            cell.querySelectorAll(':scope > span').forEach(function (span) {
                Array.from(span.childNodes).forEach(function (node) {
                    if (node.nodeType === 3 && node.textContent.trim()) {
                        node.textContent = '';
                    }
                });
            });

            // Add placeholders
            var nameInput = cell.querySelector('input[name^="macroInput"]');
            if (nameInput) nameInput.placeholder = 'Name';
            var valInput = cell.querySelector('input[name^="macroValue"]');
            if (valInput) valInput.placeholder = 'Value';

            // Wrap trailing icons in a flex container
            var icons = [];
            var children = Array.from(cell.children);
            var passedSpans = 0;
            children.forEach(function (child) {
                if (child.tagName === 'SPAN') passedSpans++;
                if (passedSpans >= 3 && (child.tagName === 'IMG' || child.tagName === 'INPUT'
                    || (child.tagName === 'SPAN' && (child.classList.contains('clonehandle')
                        || child.id.indexOf('remove_current') !== -1)))) {
                    icons.push(child);
                }
            });
            if (icons.length > 0) {
                var wrapper = document.createElement('div');
                wrapper.className = 'cf-macro-actions';
                wrapper.style.cssText = 'display:flex;align-items:center;gap:4px;flex-shrink:0;';
                icons.forEach(function (icon) {
                    wrapper.appendChild(icon);
                });
                cell.appendChild(wrapper);
            }
        });
    }

    // =========================================================================
    //  GEO COORDINATES AUTOCOMPLETE
    //  Address search via Nominatim (OpenStreetMap) API.
    //  Type 3+ characters to search, results appear in a dropdown.
    // =========================================================================

    /**
     * Initialize the geo coordinates address autocomplete.
     * Requires two elements:
     * - An input with id="cfGeoAddress" (the search field)
     * - A div with id="cfGeoResults" (the dropdown container)
     * - A target input with name="geo_coords" (where the lat,lon is written)
     *
     * @param {number} [debounce=400] - Debounce delay in ms before searching.
     */
    function initGeoAutocomplete(debounce) {
        debounce = debounce || 400;

        var geoInput   = document.getElementById('cfGeoAddress');
        var geoResults = document.getElementById('cfGeoResults');
        if (!geoInput || !geoResults) return;

        var timer = null;

        // Search on input (debounced)
        geoInput.addEventListener('input', function () {
            clearTimeout(timer);
            var query = this.value.trim();
            if (query.length < 3) {
                geoResults.style.display = 'none';
                return;
            }

            timer = setTimeout(function () {
                var url = 'https://nominatim.openstreetmap.org/search?format=json&limit=5&q='
                    + encodeURIComponent(query);

                fetch(url)
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data || data.length === 0) {
                            geoResults.style.display = 'none';
                            return;
                        }

                        var html = '';
                        data.forEach(function (item) {
                            html += '<div class="cf-geo-item" data-coords="'
                                + item.lat + ',' + item.lon + '">'
                                + '<span class="cf-geo-item-name">'
                                + item.display_name + '</span>'
                                + '<span class="cf-geo-item-coords">'
                                + item.lat + ', ' + item.lon + '</span></div>';
                        });

                        geoResults.innerHTML = html;
                        geoResults.style.display = 'block';
                    })
                    .catch(function () {
                        geoResults.style.display = 'none';
                    });
            }, debounce);
        });

        // Select a result
        geoResults.addEventListener('click', function (e) {
            var item = e.target.closest('.cf-geo-item');
            if (!item) return;

            var coordInput = document.querySelector('input[name="geo_coords"]');
            if (coordInput) {
                coordInput.value = item.dataset.coords;
            }

            // Float the label
            var label = coordInput
                ? coordInput.parentElement.querySelector('label')
                : null;
            if (label) label.classList.add('cf-label-float');

            // Show the selected address in the search field
            geoInput.value = item.querySelector('.cf-geo-item-name').textContent;
            geoResults.style.display = 'none';
        });

        // Hide dropdown on outside click
        document.addEventListener('click', function (e) {
            if (!geoInput.contains(e.target) && !geoResults.contains(e.target)) {
                geoResults.style.display = 'none';
            }
        });

        // Prevent form submit on Enter in search field
        geoInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') e.preventDefault();
        });
    }

    // =========================================================================
    //  BREADCRUMB HIDE (in side panel context)
    // =========================================================================

    /**
     * Hide the breadcrumb and page title when the form is loaded
     * inside the side panel iframe (they're redundant with the panel header).
     */
    function hideBreadcrumbInPanel() {
        if (window.frameElement && window.frameElement.id === 'cfSidePanelFrame') {
            var breadcrumb = document.querySelector('.pathway');
            if (breadcrumb) breadcrumb.style.display = 'none';

            var title = document.querySelector('.cf-page-title');
            if (title) title.style.display = 'none';
        }
    }

    // =========================================================================
    //  CONVENIENCE INITIALIZERS
    // =========================================================================

    /**
     * Initialize all form components on a form page.
     * Call this once in the form template's DOMContentLoaded handler.
     *
     * @param {object} [options]
     * @param {string} [options.exclusiveChip]  - Exclusive chip ID for initChips (e.g. "notifN")
     * @param {boolean} [options.macros=false]  - Whether to initialize macro cleanup
     * @param {boolean} [options.geo=false]     - Whether to initialize geo autocomplete
     */
    function initFormPage(options) {
        options = options || {};

        // Each step is isolated so a failure in one never aborts the others.
        // initYesNoSegments runs BEFORE initFloatLabels: otherwise float-labels tag the
        // radios' own "Yes/No/Default" <label> with cf-label-float and we'd pick that up
        // instead of the field's real parameter label.
        var steps = [initYesNoSegments, initCheckboxChips, initSoloToggles, initToggleDependencies, initFloatLabels, initSegmentedButtons, initTooltips, hideBreadcrumbInPanel, initEnterToSubmit];
        if (options.exclusiveChip) steps.push(function () { initChips(options.exclusiveChip); });
        if (options.macros) steps.push(initMacroCleanup);
        if (options.geo) steps.push(initGeoAutocomplete);

        steps.forEach(function (step) {
            try { step(); } catch (e) { if (window.console) console.error('CentreonForm init step failed', e); }
        });
    }

    /**
     * Pressing Enter in a plain text field submits the form via its visible
     * Save button, same as a native <input type="submit"> is supposed to do.
     * Explicit instead of relying on the browser default so it's consistent
     * regardless of field/browser quirks (e.g. select2's own search box,
     * which manages Enter itself to confirm the highlighted option).
     *
     * @private
     */
    function initEnterToSubmit() {
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;

            var target = e.target;
            if (!target || target.tagName !== 'INPUT') return;
            if (target.classList.contains('select2-search__field')) return;

            var type = (target.getAttribute('type') || 'text').toLowerCase();
            if (['text', 'email', 'number', 'tel', 'url', 'password', 'search'].indexOf(type) === -1) return;

            var form = target.closest('form');
            if (!form) return;
            var submitBtn = form.querySelector('.cf-actions input[type="submit"]:not([disabled])');
            if (!submitBtn) return;

            e.preventDefault();
            submitBtn.click();
        });
    }

    // =========================================================================
    //  PRIVATE HELPERS
    // =========================================================================

    /**
     * Find a QuickForm radio button by name and value.
     * Tries both naming patterns: "name[name]" (addGroup) and "name" (simple).
     *
     * @private
     * @param {string} name  - The radio group name
     * @param {string} value - The radio value to find
     * @returns {HTMLElement|null}
     */
    function _findRadio(name, value) {
        return document.querySelector('input[name="' + name + '[' + name + ']"][value="' + value + '"]')
            || document.querySelector('input[name="' + name + '"][value="' + value + '"]');
    }

    // =========================================================================
    //  MAKE TOGGLE
    //  Turn QuickForm checkbox field(s) into a Centreon design-system switch.
    //  Call BEFORE initFormPage so the chip auto-transform skips them.
    // =========================================================================

    /**
     * @param {string|string[]} names one or more checkbox element names
     */
    function makeToggle(names) {
        if (!Array.isArray(names)) {
            names = [names];
        }
        names.forEach(function (name) {
            var input = document.querySelector('input[name="' + name + '"]');
            if (!input) {
                return;
            }
            var field = input.closest('.cf-field');
            if (field) {
                _convertToToggle(field, input);
            }
        });
    }

    /**
     * @private Convert a single checkbox inside a .cf-field into a cl-toggle switch.
     * The original <input> element is preserved (id, name, handlers) so submission
     * and any bound JS keep working.
     */
    function _convertToToggle(field, input) {
        if (field.classList.contains('cf-field-toggle')) {
            return;
        }
        var lbl = field.querySelector('.cf-label-float') || field.querySelector('label');
        var text = lbl ? lbl.textContent.trim() : '';
        var help = field.querySelector('.helpTooltip');

        var toggle = document.createElement('label');
        toggle.className = 'cl-toggle';
        toggle.appendChild(input);
        var slider = document.createElement('span');
        slider.className = 'cl-toggle-slider';
        toggle.appendChild(slider);

        field.innerHTML = '';
        field.classList.add('cf-field-toggle');
        field.appendChild(toggle);
        var span = document.createElement('span');
        span.className = 'cf-toggle-label';
        span.textContent = text;
        field.appendChild(span);
        if (help) {
            field.appendChild(help);
        }
    }

    // =========================================================================
    //  AUTO SOLO TOGGLES
    //  Turn every remaining single (solo) QuickForm checkbox into a design-system
    //  toggle. Multi-checkbox groups (turned into chips) and hidden/clone
    //  checkboxes are left alone; a field or container marked
    //  [data-cf-no-auto-toggle] opts out.
    // =========================================================================
    function initSoloToggles() {
        var wrapper = document.querySelector('.cf-form-wrapper');
        if (!wrapper) return;

        var fields = [];
        var groups = [];
        wrapper.querySelectorAll('.md-checkbox input[type="checkbox"]').forEach(function (input) {
            var mdc = input.closest('.md-checkbox');
            if (!mdc || mdc.offsetParent === null) return;                        // hidden
            if (input.closest('.clonable') || input.closest('.macroclone')) return; // clone/macro rows
            var field = input.closest('.cf-field');
            if (!field) return;
            if (field.querySelector('.cf-chips') || field.classList.contains('cf-field-toggle')) return;
            if (field.closest('[data-cf-no-auto-toggle]')) return;                // opt-out
            var idx = fields.indexOf(field);
            if (idx === -1) { fields.push(field); groups.push({ field: field, items: [] }); idx = groups.length - 1; }
            groups[idx].items.push(input);
        });

        groups.forEach(function (g) {
            if (g.items.length !== 1) return;   // solo checkboxes only (groups become chips)
            try { _convertToToggle(g.field, g.items[0]); } catch (e) {}
        });
    }

    // =========================================================================
    //  TOGGLE DEPENDENCIES (grey out dependent fields)
    //  Declarative: on a checkbox/toggle set
    //    data-cf-disables="<selector>"  -> targets disabled + greyed when checked
    //    data-cf-enables="<selector>"   -> targets disabled + greyed when unchecked
    //  The attribute may sit on the checkbox itself or on a container holding one.
    // =========================================================================
    function initToggleDependencies() {
        document.querySelectorAll('[data-cf-disables],[data-cf-enables]').forEach(_applyToggleDependency);
    }

    /** @private Wire one dependency source and apply its initial state. */
    function _applyToggleDependency(el) {
        var input = (el.matches && el.matches('input[type="checkbox"]'))
            ? el
            : el.querySelector('input[type="checkbox"]');
        if (!input) return;

        var disSel = el.getAttribute('data-cf-disables');
        var enSel  = el.getAttribute('data-cf-enables');
        if (!disSel && !enSel) return;

        function apply() {
            if (disSel) _setFieldsDisabled(disSel, input.checked);
            if (enSel)  _setFieldsDisabled(enSel, !input.checked);
        }
        input.addEventListener('change', apply);
        apply();
    }

    /** @private Disable + grey (or restore) every field/control matched by a selector. */
    function _setFieldsDisabled(selector, disabled) {
        document.querySelectorAll(selector).forEach(function (target) {
            var isControl = target.matches && target.matches('input,select,textarea,button');
            var box = isControl ? (target.closest('.cf-field') || target) : target;
            box.classList.toggle('cf-disabled', disabled);
            if (isControl) target.disabled = disabled;
            box.querySelectorAll('input,select,textarea,button').forEach(function (el) {
                el.disabled = disabled;
            });
        });
    }

    // =========================================================================
    //  PUBLIC API
    // =========================================================================

    return {
        // Side panel
        openPanel:            openPanel,
        closePanel:           closePanel,
        initSidePanel:        initSidePanel,
        detectSidePanelClose: detectSidePanelClose,
        /** @internal Exposed for cross-iframe access in detectSidePanelClose */
        _sidePanelListing:    null,

        // Accordion
        toggleSection: toggleSection,
        scrollTo:      scrollTo,

        // Form components
        initFloatLabels:      initFloatLabels,
        initTooltips:         initTooltips,
        initSegmentedButtons: initSegmentedButtons,
        initYesNoSegments:    initYesNoSegments,
        initCheckboxChips:    initCheckboxChips,
        initChips:            initChips,
        syncToggle:           syncToggle,
        makeToggle:           makeToggle,
        initSoloToggles:      initSoloToggles,
        initToggleDependencies: initToggleDependencies,
        initMacroCleanup:     initMacroCleanup,
        initGeoAutocomplete:  initGeoAutocomplete,
        hideBreadcrumbInPanel: hideBreadcrumbInPanel,

        // Convenience
        initFormPage: initFormPage
    };

})();

// Global aliases for use in onclick attributes in Smarty templates
var cfOpenPanel     = CentreonForm.openPanel;
var cfClosePanel    = function () { CentreonForm.closePanel(CentreonForm._sidePanelListing); };
var cfToggleSection = CentreonForm.toggleSection;
var cfScrollTo      = CentreonForm.scrollTo;
var cfMakeToggle    = CentreonForm.makeToggle;
