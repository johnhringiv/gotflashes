/**
 * Home-grown single-select combobox — replaces tom-select.
 *
 * Enhances a native <select> without replacing it as the source of truth:
 * the select is hidden but keeps the form value, options, and 'change'
 * events; the combobox is a text input plus a filtered listbox popup.
 *
 * Livewire-proof the same way the date picker is — by construction, not by
 * hooks: options are re-read from the select's <option> elements on EVERY
 * open (Livewire can morph them freely while the popup is closed), and the
 * listbox is appended to document.body only while open, so a morph never
 * sees it and it escapes any overflow container.
 *
 * Contract with the markup:
 * - option[value=""] is a placeholder, not a pickable row — unless the
 *   select has data-allow-empty="true" (admin filters: "All Fleets").
 * - Arbitrary per-option data rides on the option's data-* attributes and is
 *   exposed to the extraFilter hook via the option element itself.
 * - Selecting a row sets select.value and dispatches a bubbling 'change'
 *   event on the select — external wiring listens there, exactly as it
 *   would for a native select.
 *
 * Selection semantics: typing filters but never destroys the current
 * selection; abandoning (Escape / blur / light dismiss) reverts the text to
 * the selected label. Committing an EMPTY text field (Enter or blur after
 * clearing it) clears the selection — the one deliberate clear gesture.
 *
 * Test handle: select._combobox (setValue / clear / open / close).
 */

/**
 * An <option>'s display text with Blade's template whitespace collapsed
 * (multi-line option bodies render with newlines and indentation).
 */
function optionLabel(option) {
    return option.textContent.replace(/\s+/g, ' ').trim();
}

/**
 * Pure filter: case-insensitive substring match of query against label.
 * Exported for unit tests.
 */
export function filterOptions(options, query) {
    const q = query.trim().toLowerCase();
    if (!q) return options;
    return options.filter((o) => o.label.toLowerCase().includes(q));
}

export class Combobox {
    constructor(select, { placeholder = '', extraFilter = null } = {}) {
        this.select = select;
        this.extraFilter = extraFilter;
        this.listbox = null; // non-null exactly while open
        this.activeIndex = -1;
        this.visible = []; // options currently rendered in the listbox

        select.hidden = true;

        this.input = document.createElement('input');
        this.input.type = 'text';
        this.input.id = `${select.id}-input`;
        this.input.className = 'combobox-input';
        this.input.placeholder = placeholder;
        this.input.autocomplete = 'off';
        this.input.setAttribute('role', 'combobox');
        this.input.setAttribute('aria-expanded', 'false');
        this.input.setAttribute('aria-haspopup', 'listbox');
        this.input.setAttribute('aria-autocomplete', 'list');
        select.insertAdjacentElement('afterend', this.input);
        this.showSelectedLabel();

        // External drivers (glue code, tests) may set select.value and
        // dispatch 'change' directly — keep the visible label in sync
        select.addEventListener('change', () => this.showSelectedLabel());

        this.input.addEventListener('click', () => this.open());
        this.input.addEventListener('input', () => {
            if (!this.listbox) this.open();
            this.renderOptions(this.input.value);
        });
        this.input.addEventListener('keydown', (e) => this.onKeydown(e));

        this.onDocumentPointerDown = (e) => {
            // Self-heal if a morph removed the field while open — close()
            // also detaches these document/window listeners (no leak)
            if (!this.input.isConnected) return this.close({ revert: false });
            if (this.input.contains(e.target)) return;
            if (this.listbox && this.listbox.contains(e.target)) return;
            this.close();
        };
        this.reposition = () => {
            if (!this.input.isConnected) return this.close({ revert: false });
            if (this.listbox) this.position();
        };
    }

    // --- open / close ------------------------------------------------------

    open() {
        if (this.listbox) return;
        this.listbox = document.createElement('ul');
        this.listbox.className = 'combobox-listbox';
        this.listbox.id = `${this.select.id}-listbox`;
        this.listbox.setAttribute('role', 'listbox');
        this.listbox.setAttribute('aria-label', this.input.placeholder || 'Options');
        // pointerdown, not click: commit before the input's blur can revert
        this.listbox.addEventListener('pointerdown', (e) => {
            const li = e.target.closest('[role="option"]');
            if (li) {
                e.preventDefault();
                this.pick(li.dataset.value);
            }
        });
        // The active highlight follows the pointer, so a mouse user never
        // sees two grey rows (hovered + the keyboard-anchored selected row)
        this.listbox.addEventListener('pointerover', (e) => {
            const li = e.target.closest('[role="option"]');
            if (li) {
                this.setActive(this.visible.findIndex((o) => o.value === li.dataset.value));
            }
        });
        document.body.appendChild(this.listbox);
        this.input.setAttribute('aria-expanded', 'true');
        this.input.setAttribute('aria-controls', this.listbox.id);
        // Open shows the full list; the current text is a label, not a query.
        // Select it so typing starts a fresh filter in one keystroke.
        this.input.select();
        this.renderOptions('');
        document.addEventListener('pointerdown', this.onDocumentPointerDown, true);
        window.addEventListener('scroll', this.reposition, true);
        window.addEventListener('resize', this.reposition);
    }

    close({ revert = true } = {}) {
        if (!this.listbox) return;
        this.listbox.remove();
        this.listbox = null;
        this.activeIndex = -1;
        this.input.setAttribute('aria-expanded', 'false');
        this.input.removeAttribute('aria-controls');
        this.input.removeAttribute('aria-activedescendant');
        document.removeEventListener('pointerdown', this.onDocumentPointerDown, true);
        window.removeEventListener('scroll', this.reposition, true);
        window.removeEventListener('resize', this.reposition);
        if (revert) {
            if (this.input.value.trim() === '' && this.select.value !== '') {
                this.clear(); // deliberate clear gesture: emptied text, then left
            } else {
                this.showSelectedLabel();
            }
        }
    }

    // --- rendering ---------------------------------------------------------

    /** Re-read pickable options from the live <select> DOM. */
    readOptions() {
        const allowEmpty = this.select.dataset.allowEmpty === 'true';
        return Array.from(this.select.options)
            .filter((o) => o.value !== '' || allowEmpty)
            .filter((o) => !this.extraFilter || this.extraFilter(o))
            .map((o) => ({ value: o.value, label: optionLabel(o) }));
    }

    renderOptions(query) {
        this.visible = filterOptions(this.readOptions(), query);
        this.listbox.textContent = '';
        if (this.visible.length === 0) {
            const li = document.createElement('li');
            li.className = 'combobox-empty';
            li.textContent = 'No matches';
            this.listbox.appendChild(li);
        }
        this.visible.forEach((opt, i) => {
            const li = document.createElement('li');
            li.className = 'combobox-option';
            li.id = `${this.select.id}-opt-${i}`;
            li.setAttribute('role', 'option');
            li.dataset.value = opt.value;
            li.textContent = opt.label;
            if (opt.value === this.select.value && opt.value !== '') {
                li.classList.add('selected');
                li.setAttribute('aria-selected', 'true');
            } else {
                li.setAttribute('aria-selected', 'false');
            }
            this.listbox.appendChild(li);
        });
        // Highlight the current selection when showing the unfiltered list,
        // the first match while filtering
        const selectedAt = query.trim() === ''
            ? this.visible.findIndex((o) => o.value === this.select.value && o.value !== '')
            : -1;
        this.setActive(selectedAt !== -1 ? selectedAt : (this.visible.length ? 0 : -1));
        this.position();
    }

    position() {
        const rect = this.input.getBoundingClientRect();
        this.listbox.style.top = `${rect.bottom + window.scrollY + 4}px`;
        this.listbox.style.left = `${rect.left + window.scrollX}px`;
        this.listbox.style.width = `${rect.width}px`;
        // ALWAYS open downward (flipping above reads wrong — user call).
        // Cap the height to the viewport space below — ceiling 24rem (the
        // stylesheet default, which this inline value overrides), floor
        // 160px so the list stays usable; the page scrolls for the rest.
        const spaceBelow = window.innerHeight - rect.bottom - 12;
        this.listbox.style.maxHeight = `${Math.min(384, Math.max(160, spaceBelow))}px`;
    }

    setActive(index) {
        if (this.activeIndex >= 0) {
            this.listbox.children[this.indexOffset() + this.activeIndex]?.classList.remove('active');
        }
        this.activeIndex = index;
        if (index >= 0) {
            const li = this.listbox.children[this.indexOffset() + index];
            li.classList.add('active');
            li.scrollIntoView?.({ block: 'nearest' }); // absent in happy-dom
            this.input.setAttribute('aria-activedescendant', li.id);
        } else {
            this.input.removeAttribute('aria-activedescendant');
        }
    }

    /** The "No matches" row precedes option rows when present. */
    indexOffset() {
        return this.visible.length === 0 ? 1 : 0;
    }

    // --- selection ---------------------------------------------------------

    pick(value) {
        // setValue updates the visible label itself (and a second, idempotent
        // time via the select's own change listener) — no extra call needed
        this.setValue(value);
        this.close({ revert: false });
    }

    /** Programmatic set: updates select + input, dispatches 'change' unless silent. */
    setValue(value, { silent = false } = {}) {
        this.select.value = value;
        this.showSelectedLabel();
        if (!silent) {
            this.select.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    clear({ silent = false } = {}) {
        this.setValue('', { silent });
    }

    getValue() {
        return this.select.value;
    }

    showSelectedLabel() {
        // Find by value rather than via selectedOptions — happy-dom (Vitest)
        // does not keep selectedOptions in sync with the value setter
        const value = this.select.value;
        const opt = value === ''
            ? null
            : Array.from(this.select.options).find((o) => o.value === value);
        this.input.value = opt ? optionLabel(opt) : '';
    }

    // --- keyboard ----------------------------------------------------------

    onKeydown(e) {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            if (!this.listbox) {
                this.open();
                return;
            }
            if (!this.visible.length) return;
            const delta = e.key === 'ArrowDown' ? 1 : -1;
            const next = Math.min(this.visible.length - 1, Math.max(0, this.activeIndex + delta));
            this.setActive(next);
        } else if (e.key === 'Enter') {
            if (!this.listbox) return;
            e.preventDefault();
            if (this.input.value.trim() === '' && this.select.value !== '') {
                this.close(); // revert path doubles as the clear gesture
            } else if (this.activeIndex >= 0) {
                this.pick(this.visible[this.activeIndex].value);
            }
        } else if (e.key === 'Escape') {
            if (this.listbox) {
                e.stopPropagation(); // don't also close an enclosing modal
                this.close();
            }
        } else if (e.key === 'Tab') {
            this.close(); // default action moves focus on
        }
    }
}

/**
 * Idempotent initializer — safe to call again after Livewire morphs.
 */
export function initCombobox(select, options) {
    if (select._combobox) return select._combobox;
    const combobox = new Combobox(select, options);
    select._combobox = combobox;
    return combobox;
}
