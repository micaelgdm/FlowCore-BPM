document.querySelectorAll('[data-form-fields]').forEach((fieldList) => {
    const wrapper = fieldList.closest('[data-field-builder]') || fieldList.parentElement;
    const template = wrapper.querySelector('[data-form-field-template]') || document.querySelector('#form-field-template');
    const addButton = wrapper.querySelector('[data-add-form-field]') || document.querySelector('#add-form-field');

    const existingIndices = [...fieldList.querySelectorAll('[data-form-index]')]
        .map((field) => Number.parseInt(field.dataset.formIndex, 10))
        .filter(Number.isInteger);
    let nextIndex = existingIndices.length ? Math.max(...existingIndices) + 1 : 0;
    let nextConditionIndex = [...fieldList.querySelectorAll('[name*="[conditions]"]')]
        .map((input) => Number.parseInt(input.name.match(/\[conditions\]\[(\d+)\]/)?.[1] ?? '', 10))
        .filter(Number.isInteger)
        .reduce((max, index) => Math.max(max, index + 1), 0);

    const updateOptionsVisibility = (field) => {
        const selector = field.querySelector('[data-field-type]');
        const options = field.querySelector('[data-field-options]');
        if (selector && options) options.hidden = selector.value !== 'select';
    };

    const updateConditionVisibility = (field) => {
        const editor = field.querySelector('[data-condition-editor]');
        const mode = field.querySelector('[data-condition-mode]');
        if (editor && mode) editor.hidden = mode.value === 'always';
    };

    const updateConditionLimit = (field) => {
        const count = field.querySelectorAll('[data-condition-row]').length;
        const button = field.querySelector('[data-add-condition]');
        if (button) {
            button.disabled = count >= 10;
            button.title = count >= 10 ? 'Limite de 10 condições por campo.' : '';
        }
    };

    const syncPicker = (picker) => {
        const select = picker.querySelector('[data-condition-field-select]');
        const label = picker.querySelector('[data-field-picker-label]');
        const options = picker.querySelector('[data-field-picker-options]');
        if (!select || !label || !options) return;
        const selected = select.selectedOptions[0];
        label.textContent = selected?.value ? selected.textContent : 'Selecione um campo';
        options.replaceChildren(...[...select.options].filter((option) => option.value).map((option) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'fc-condition-field-option';
            button.dataset.pickerValue = option.value;
            button.setAttribute('role', 'option');
            button.setAttribute('aria-selected', String(select.value === option.value));
            button.textContent = option.textContent;
            return button;
        }));
    };

    const refreshConditionFields = () => {
        const cards = [...fieldList.querySelectorAll('[data-form-field]')];
        cards.forEach((card, cardIndex) => {
            const eligible = cards.slice(0, cardIndex).map((candidate) => ({
                key: candidate.querySelector('[data-field-key]')?.value.trim() || '',
                label: candidate.querySelector('[data-field-label]')?.value.trim() || '',
                type: candidate.querySelector('[data-field-type]')?.value || '',
            })).filter((candidate) => candidate.key && candidate.type !== 'file');

            card.querySelectorAll('[data-condition-field-select]').forEach((select) => {
                const wanted = select.value || select.dataset.selectedField || '';
                select.replaceChildren(new Option('Selecione um campo', ''));
                eligible.forEach((candidate) => {
                    select.add(new Option(`${candidate.label || candidate.key} · ${candidate.key}`, candidate.key));
                });
                select.value = eligible.some((candidate) => candidate.key === wanted) ? wanted : '';
                select.dataset.selectedField = select.value;
                syncPicker(select.closest('[data-condition-field-picker]'));
            });
        });
    };

    const initializeField = (field) => {
        updateOptionsVisibility(field);
        updateConditionVisibility(field);
        updateConditionLimit(field);
    };

    fieldList.querySelectorAll('[data-form-field]').forEach(initializeField);
    refreshConditionFields();

    fieldList.addEventListener('input', (event) => {
        if (event.target.matches('[data-field-key], [data-field-label]')) refreshConditionFields();
        if (event.target.matches('[data-field-picker-search]')) {
            const query = event.target.value.trim().toLocaleLowerCase();
            event.target.closest('[data-field-picker-menu]')?.querySelectorAll('[data-picker-value]').forEach((option) => {
                option.hidden = !option.textContent.toLocaleLowerCase().includes(query);
            });
        }
    });

    fieldList.addEventListener('change', (event) => {
        const field = event.target.closest('[data-form-field]');
        if (!field) return;
        if (event.target.matches('[data-field-type]')) updateOptionsVisibility(field);
        if (event.target.matches('[data-condition-mode]')) updateConditionVisibility(field);
        if (event.target.matches('[data-condition-field-select]')) event.target.dataset.selectedField = event.target.value;
        if (event.target.matches('[data-condition-field-select]')) syncPicker(event.target.closest('[data-condition-field-picker]'));
        if (event.target.matches('[data-field-type], [data-field-key], [data-field-label]')) refreshConditionFields();
    });

    fieldList.addEventListener('click', (event) => {
        const togglePicker = event.target.closest('[data-field-picker-toggle]');
        if (togglePicker) {
            const picker = togglePicker.closest('[data-condition-field-picker]');
            const menu = picker?.querySelector('[data-field-picker-menu]');
            const opening = menu?.hidden;
            fieldList.querySelectorAll('[data-field-picker-menu]').forEach((item) => { item.hidden = true; });
            fieldList.querySelectorAll('[data-field-picker-toggle]').forEach((button) => button.setAttribute('aria-expanded', 'false'));
            if (menu && opening) {
                menu.hidden = false;
                togglePicker.setAttribute('aria-expanded', 'true');
                const search = menu.querySelector('[data-field-picker-search]');
                if (search) { search.value = ''; search.dispatchEvent(new Event('input', { bubbles: true })); search.focus(); }
            }
            return;
        }

        const pickerOption = event.target.closest('[data-picker-value]');
        if (pickerOption) {
            const picker = pickerOption.closest('[data-condition-field-picker]');
            const select = picker?.querySelector('[data-condition-field-select]');
            if (select) {
                select.value = pickerOption.dataset.pickerValue;
                select.dataset.selectedField = select.value;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                const menu = picker.querySelector('[data-field-picker-menu]');
                if (menu) menu.hidden = true;
                picker.querySelector('[data-field-picker-toggle]')?.setAttribute('aria-expanded', 'false');
            }
            return;
        }

        const removeCondition = event.target.closest('[data-remove-condition]');
        if (removeCondition) {
            const field = removeCondition.closest('[data-form-field]');
            const row = removeCondition.closest('[data-condition-row]');
            const list = row?.parentElement;
            if (field && row && list?.children.length === 1) {
                row.querySelectorAll('input').forEach((input) => { input.value = ''; });
                row.querySelectorAll('select').forEach((select) => { select.selectedIndex = 0; });
                const picker = row.querySelector('[data-condition-field-picker]');
                if (picker) {
                    const menu = picker.querySelector('[data-field-picker-menu]');
                    if (menu) menu.hidden = true;
                    picker.querySelector('[data-field-picker-toggle]')?.setAttribute('aria-expanded', 'false');
                    syncPicker(picker);
                }
            } else {
                row?.remove();
            }
            if (field) updateConditionLimit(field);
            return;
        }

        const addCondition = event.target.closest('[data-add-condition]');
        if (addCondition) {
            const field = addCondition.closest('[data-form-field]');
            const list = field?.querySelector('[data-condition-list]');
            const source = list?.querySelector('[data-condition-row]');
            if (!field || !list || !source) return;
            if (list.children.length >= 10) return;
            const index = nextConditionIndex++;
            const row = source.cloneNode(true);
            row.querySelectorAll('input').forEach((input) => { input.value = ''; });
            row.querySelectorAll('select').forEach((select) => { select.selectedIndex = 0; });
            const search = row.querySelector('[data-field-picker-search]');
            if (search) search.value = '';
            row.querySelectorAll('[name]').forEach((input) => {
                input.name = input.name.replace(/\[conditions\]\[\d+\]/, `[conditions][${index}]`);
                if (input.matches('[data-condition-field-select]')) input.dataset.selectedField = '';
            });
            list.append(row);
            refreshConditionFields();
            updateConditionLimit(field);
            return;
        }

        const remove = event.target.closest('[data-remove-field]');
        if (remove) {
            remove.closest('[data-form-field]')?.remove();
            refreshConditionFields();
        }
    });

    addButton?.addEventListener('click', () => {
        const html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex++));
        fieldList.insertAdjacentHTML('beforeend', html);
        const addedField = fieldList.lastElementChild;
        if (addedField instanceof HTMLElement) initializeField(addedField);
        refreshConditionFields();
    });
});
