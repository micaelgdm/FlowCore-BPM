import '../css/app.css';

if (document.querySelector('[data-form-fields]')) {
    import('./process-form-builder.js').catch((error) => console.error('Form builder failed to load:', error));
}

if (document.querySelector('#bpmn-canvas')) {
    import('./bpmn-editor.js').catch((error) => console.error('BPMN editor failed to load:', error));
}

document.querySelectorAll('[data-member-picker]').forEach((picker) => {
    const search = picker.querySelector('[data-member-search]');
    const count = picker.querySelector('[data-member-count]');
    const options = [...picker.querySelectorAll('[data-member-option]')];
    const emptyState = picker.querySelector('[data-member-empty]');

    const updateCount = () => {
        const selected = options.filter((option) => option.querySelector('input')?.checked).length;
        count.textContent = `${selected} ${selected === 1 ? 'selecionado' : 'selecionados'}`;
    };

    search?.addEventListener('input', () => {
        const query = search.value.trim().toLocaleLowerCase('pt-BR').normalize('NFD').replace(/\p{Diacritic}/gu, '');
        let visibleOptions = 0;

        options.forEach((option) => {
            const text = option.textContent.toLocaleLowerCase('pt-BR').normalize('NFD').replace(/\p{Diacritic}/gu, '');
            option.hidden = !text.includes(query);
            if (!option.hidden) visibleOptions += 1;
        });

        emptyState.hidden = visibleOptions > 0;
    });

    picker.addEventListener('change', updateCount);
    updateCount();
});
