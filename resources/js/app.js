import '../css/app.css';

const themeStorageKey = 'flowcore-theme';
const themeToggleButtons = [...document.querySelectorAll('[data-theme-toggle]')];
let activeTheme = 'light';

try {
    const savedTheme = window.localStorage.getItem(themeStorageKey);
    if (savedTheme === 'dark' || savedTheme === 'light') activeTheme = savedTheme;
} catch { /* Keep light mode when browser storage is unavailable. */ }

const applyTheme = (theme) => {
    activeTheme = theme;
    document.documentElement.dataset.theme = theme;
    const nextTheme = theme === 'dark' ? 'light' : 'dark';
    themeToggleButtons.forEach((button) => {
        const label = nextTheme === 'dark' ? 'Escuro' : 'Claro';
        button.setAttribute('aria-label', `Ativar modo ${label.toLocaleLowerCase()}`);
        button.setAttribute('aria-pressed', String(theme === 'dark'));
        button.title = `Alternar para modo ${label.toLocaleLowerCase()}`;
        const moonIcon = button.querySelector('[data-theme-icon="moon"]');
        const sunIcon = button.querySelector('[data-theme-icon="sun"]');
        const text = button.querySelector('[data-theme-label]');
        if (moonIcon) moonIcon.hidden = theme === 'dark';
        if (sunIcon) sunIcon.hidden = theme !== 'dark';
        if (text) text.textContent = label;
    });
};

applyTheme(activeTheme);
themeToggleButtons.forEach((button) => button.addEventListener('click', () => {
    const nextTheme = activeTheme === 'dark' ? 'light' : 'dark';
    applyTheme(nextTheme);
    try { window.localStorage.setItem(themeStorageKey, nextTheme); } catch { /* Theme still applies for this page. */ }
}));

document.querySelectorAll('.fc-nav-dropdown').forEach((dropdown) => {
    let closeTimer = null;
    const cancelClose = () => {
        if (closeTimer !== null) window.clearTimeout(closeTimer);
        closeTimer = null;
    };
    const scheduleClose = () => {
        cancelClose();
        closeTimer = window.setTimeout(() => {
            if (!dropdown.matches(':hover')) dropdown.open = false;
            closeTimer = null;
        }, 140);
    };

    dropdown.addEventListener('pointerenter', () => {
        cancelClose();
        dropdown.open = true;
    });
    dropdown.addEventListener('pointerleave', scheduleClose);
    dropdown.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || !dropdown.open) return;
        dropdown.open = false;
        dropdown.querySelector('summary')?.focus();
    });
});

const confirmDialog = document.createElement('dialog');
confirmDialog.className = 'fc-confirm-dialog';
confirmDialog.setAttribute('aria-labelledby', 'fc-confirm-title');
confirmDialog.setAttribute('aria-describedby', 'fc-confirm-message');
const confirmModal = document.createElement('section');
confirmModal.className = 'fc-confirm-modal';

const confirmIcon = document.createElement('span');
confirmIcon.className = 'fc-confirm-icon';
confirmIcon.setAttribute('aria-hidden', 'true');
const helpIcon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
helpIcon.setAttribute('viewBox', '0 0 24 24');
helpIcon.setAttribute('fill', 'none');
helpIcon.setAttribute('stroke', 'currentColor');
helpIcon.setAttribute('stroke-width', '1.8');
helpIcon.setAttribute('stroke-linecap', 'round');
helpIcon.setAttribute('stroke-linejoin', 'round');
const helpCircle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
helpCircle.setAttribute('cx', '12');
helpCircle.setAttribute('cy', '12');
helpCircle.setAttribute('r', '9');
const helpPath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
helpPath.setAttribute('d', 'M9.7 9a2.35 2.35 0 1 1 3.9 1.77c-.96.82-1.6 1.28-1.6 2.73m0 3h.01');
helpIcon.append(helpCircle, helpPath);
confirmIcon.append(helpIcon);

const confirmTitle = document.createElement('h2');
confirmTitle.id = 'fc-confirm-title';
confirmTitle.textContent = 'Confirmar ação';
const confirmMessage = document.createElement('p');
confirmMessage.id = 'fc-confirm-message';
const confirmActions = document.createElement('div');
confirmActions.className = 'fc-confirm-actions';
const cancelButton = document.createElement('button');
cancelButton.className = 'fc-button fc-button-secondary';
cancelButton.type = 'button';
cancelButton.dataset.confirmCancel = '';
cancelButton.textContent = 'Cancelar';
const acceptButton = document.createElement('button');
acceptButton.className = 'fc-button fc-button-primary';
acceptButton.type = 'button';
acceptButton.dataset.confirmAccept = '';
acceptButton.textContent = 'Continuar';
confirmActions.append(cancelButton, acceptButton);
confirmModal.append(confirmIcon, confirmTitle, confirmMessage, confirmActions);
confirmDialog.append(confirmModal);
document.body.append(confirmDialog);

let resolveConfirmation = null;
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

document.querySelectorAll('[data-conditional-form]').forEach((form) => {
    const controlsFor = (key) => [...form.querySelectorAll('[data-field-value]')]
        .filter((input) => input.dataset.fieldValue === key);
    const valueFor = (key) => {
        const controls = controlsFor(key);
        const control = controls[0];
        if (control instanceof HTMLSelectElement && control.multiple) {
            return [...control.selectedOptions].map((option) => option.value);
        }
        if (controls.length > 1) return controls.map((item) => item.value);
        return control?.value ?? '';
    };

    const conditionMatches = ({ field, operator = 'eq', value = '' }) => {
        const current = valueFor(field);
        const expected = String(value);
        if (Array.isArray(current)) {
            if (operator === 'neq') return !current.includes(expected);
            if (operator === 'contains') return current.some((item) => item.toLocaleLowerCase().includes(expected.toLocaleLowerCase()));
            if (operator === 'not_contains') return !current.some((item) => item.toLocaleLowerCase().includes(expected.toLocaleLowerCase()));
            return current.includes(expected);
        }
        if (current === '') return ['neq', 'not_contains'].includes(operator);
        switch (operator) {
            case 'neq': return current !== expected;
            case 'contains': return current.toLocaleLowerCase().includes(expected.toLocaleLowerCase());
            case 'not_contains': return !current.toLocaleLowerCase().includes(expected.toLocaleLowerCase());
            default: return current === expected;
        }
    };

    const updateConditionalFields = () => {
        form.querySelectorAll('[data-calculated-field]').forEach((output) => {
            const left = Number.parseFloat(valueFor(output.dataset.calculationLeft));
            const right = Number.parseFloat(valueFor(output.dataset.calculationRight));
            if (!Number.isFinite(left) || !Number.isFinite(right)) {
                output.value = '';
                return;
            }
            const result = ({
                add: left + right,
                subtract: left - right,
                multiply: left * right,
                divide: right === 0 ? Number.NaN : left / right,
            })[output.dataset.calculationOperation];
            output.value = Number.isFinite(result) ? String(Number(result.toFixed(8))) : '';
        });

        form.querySelectorAll('[data-conditional-field]').forEach((field) => {
            let condition = {};
            try { condition = JSON.parse(field.dataset.visibilityCondition || '{}'); } catch { condition = {}; }
            if (condition.field) {
                condition = { mode: 'hidden_unless', match: 'all', conditions: [{ field: condition.field, operator: 'eq', value: condition.equals ?? '' }] };
            }
            const conditions = Array.isArray(condition.conditions) ? condition.conditions : [];
            const matches = conditions.map(conditionMatches);
            const matched = conditions.length === 0 || (condition.match === 'any' ? matches.includes(true) : matches.every(Boolean));
            const visible = condition.mode === 'shown_unless' ? !matched : condition.mode === 'always' || matched;
            field.hidden = !visible;
            field.querySelectorAll('input, select, textarea, button').forEach((control) => {
                control.disabled = !visible;
            });
        });
    };

    form.addEventListener('input', updateConditionalFields);
    form.addEventListener('change', updateConditionalFields);
    updateConditionalFields();
});

document.querySelectorAll('[data-assignment-type]').forEach((typeSelect) => {
    const card = typeSelect.closest('.fc-task-disclosure-content');
    const groupField = card?.querySelector('[data-assignment-group]');
    const userField = card?.querySelector('[data-assignment-user]');

    const updateAssignmentFields = () => {
        const isUser = typeSelect.value === 'user';
        [[groupField, !isUser], [userField, isUser]].forEach(([wrapper, visible]) => {
            if (!(wrapper instanceof HTMLElement)) return;
            wrapper.hidden = !visible;
        });
    };

    typeSelect.addEventListener('change', updateAssignmentFields);
    updateAssignmentFields();
});

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

document.querySelectorAll('[data-access-picker]').forEach((picker) => {
    const search = picker.querySelector('[data-access-search]');
    const count = picker.querySelector('[data-access-count]');
    const options = [...picker.querySelectorAll('[data-access-option]')];
    const emptyState = picker.querySelector('[data-access-empty]');

    const updateCount = () => {
        const selected = options.filter((option) => [...option.querySelectorAll('input[type="checkbox"]')].some((input) => input.checked)).length;
        count.textContent = `${selected} ${selected === 1 ? 'usuário liberado' : 'usuários liberados'}`;
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
