document.querySelectorAll('[data-form-fields]').forEach((fieldList) => {
    const wrapper = fieldList.closest('[data-field-builder]') || fieldList.parentElement;
    const template = wrapper.querySelector('[data-form-field-template]') || document.querySelector('#form-field-template');
    const addButton = wrapper.querySelector('[data-add-form-field]') || document.querySelector('#add-form-field');
    const existingIndices = [...fieldList.querySelectorAll('[data-form-index]')]
        .map((field) => Number.parseInt(field.dataset.formIndex, 10))
        .filter(Number.isInteger);
    let nextIndex = existingIndices.length ? Math.max(...existingIndices) + 1 : 0;

    const updateOptionsVisibility = (field) => {
        const selector = field.querySelector('[data-field-type]');
        const options = field.querySelector('[data-field-options]');
        options.hidden = selector.value !== 'select';
    };

    fieldList.addEventListener('change', (event) => {
        if (event.target.matches('[data-field-type]')) {
            updateOptionsVisibility(event.target.closest('[data-form-field]'));
        }
    });

    fieldList.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-remove-field]');
        if (remove) {
            remove.closest('[data-form-field]').remove();
        }
    });

    addButton?.addEventListener('click', () => {
        const html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex++));
        fieldList.insertAdjacentHTML('beforeend', html);
    });
});
