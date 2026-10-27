import '../css/app.css';

const confirmDialog = document.createElement('dialog');
confirmDialog.className = 'fc-confirm-dialog';
confirmDialog.setAttribute('aria-labelledby', 'fc-confirm-title');
confirmDialog.setAttribute('aria-describedby', 'fc-confirm-message');
confirmDialog.innerHTML = `
    <section class="fc-confirm-modal">
        <span class="fc-confirm-icon" aria-hidden="true">?</span>
        <h2 id="fc-confirm-title">Confirmar ação</h2>
        <p id="fc-confirm-message"></p>
        <div class="fc-confirm-actions">
            <button class="fc-button fc-button-secondary" type="button" data-confirm-cancel>Cancelar</button>
            <button class="fc-button fc-button-primary" type="button" data-confirm-accept>Continuar</button>
        </div>
    </section>
`;
document.body.append(confirmDialog);

let resolveConfirmation = null;
const confirmMessage = confirmDialog.querySelector('#fc-confirm-message');
confirmDialog.querySelector('[data-confirm-cancel]')?.addEventListener('click', () => confirmDialog.close('cancel'));
confirmDialog.querySelector('[data-confirm-accept]')?.addEventListener('click', () => confirmDialog.close('confirm'));
confirmDialog.addEventListener('close', () => {
    resolveConfirmation?.(confirmDialog.returnValue === 'confirm');
    resolveConfirmation = null;
});

window.flowcoreConfirm = (message) => new Promise((resolve) => {
    resolveConfirmation = resolve;
    confirmMessage.textContent = message;
    confirmDialog.showModal();
});

const confirmedForms = new WeakSet();
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) return;
    if (confirmedForms.has(form)) {
        confirmedForms.delete(form);
        return;
    }

    event.preventDefault();
    const submitter = event.submitter;
    window.flowcoreConfirm(form.dataset.confirm).then((confirmed) => {
        if (!confirmed) return;
        confirmedForms.add(form);
        if (submitter instanceof HTMLElement) form.requestSubmit(submitter);
        else form.requestSubmit();
    });
});

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
