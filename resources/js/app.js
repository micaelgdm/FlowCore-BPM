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

const gatewayOperatorLabels = {
    eq: 'é igual a',
    neq: 'é diferente de',
    gt: 'é maior que',
    gte: 'é maior ou igual a',
    lt: 'é menor que',
    lte: 'é menor ou igual a',
    contains: 'contém',
    not_contains: 'não contém',
};

document.querySelectorAll('[data-gateway-mode-card]').forEach((card) => {
    const modeSelect = card.querySelector('[data-gateway-mode]');
    const approvalRules = card.querySelector('[data-gateway-approval]');
    const conditionRules = card.querySelector('[data-gateway-conditions]');

    const setSectionEnabled = (section, enabled) => {
        if (!(section instanceof HTMLElement)) return;
        section.hidden = !enabled;
        section.querySelectorAll('input, select, textarea, button').forEach((control) => {
            control.disabled = !enabled;
        });
    };

    const updateRuleRow = (row) => {
        const fieldSelect = row.querySelector('[data-condition-field]');
        const selectedField = fieldSelect?.selectedOptions[0];
        const fieldType = selectedField?.dataset.fieldType || '';
        const operatorSelect = row.querySelector('[data-condition-operator]');
        const textValue = row.querySelector('[data-condition-text-value]');
        const optionValue = row.querySelector('[data-condition-option-value]');
        const valueInput = row.querySelector('[data-condition-value-input]');
        const valueSelect = row.querySelector('[data-condition-value-select]');
        const operators = ['number', 'calculated'].includes(fieldType)
            ? ['eq', 'neq', 'gt', 'gte', 'lt', 'lte']
            : ['boolean', 'date', 'select'].includes(fieldType)
                ? ['eq', 'neq']
                : ['eq', 'neq', 'contains', 'not_contains'];
        const currentOperator = operatorSelect?.value || 'eq';
        if (operatorSelect) {
            operatorSelect.replaceChildren(...operators.map((operator) => {
                const option = document.createElement('option');
                option.value = operator;
                option.textContent = gatewayOperatorLabels[operator];
                return option;
            }));
            operatorSelect.value = operators.includes(currentOperator) ? currentOperator : 'eq';
        }

        const choices = fieldType === 'boolean'
            ? [{ value: 'true', label: 'Sim' }, { value: 'false', label: 'Não' }]
            : fieldType === 'select'
                ? (() => { try { return JSON.parse(selectedField.dataset.fieldOptions || '[]').map((value) => ({ value, label: value })); } catch { return []; } })()
                : null;

        if (choices && valueSelect) {
            const previousValue = valueInput?.value || valueSelect.value;
            valueSelect.replaceChildren(new Option('Selecione um valor', ''), ...choices.map(({ value, label }) => new Option(label, value)));
            valueSelect.value = choices.some(({ value }) => String(value) === previousValue) ? previousValue : '';
            if (valueInput) valueInput.disabled = true;
            if (textValue) textValue.hidden = true;
            if (optionValue) optionValue.hidden = false;
            valueSelect.disabled = false;
        } else {
            if (valueInput) {
                valueInput.disabled = false;
                valueInput.type = ['number', 'calculated'].includes(fieldType) ? 'number' : 'text';
                valueInput.step = ['number', 'calculated'].includes(fieldType) ? 'any' : '';
            }
            if (textValue) textValue.hidden = false;
            if (optionValue) optionValue.hidden = true;
            if (valueSelect) valueSelect.disabled = true;
        }
    };

    const updateCard = () => {
        const useConditions = Boolean(conditionRules) && (!modeSelect || modeSelect.value === 'conditions');
        setSectionEnabled(approvalRules, !useConditions);
        setSectionEnabled(conditionRules, useConditions);
        card.querySelectorAll('[data-gateway-condition-row]').forEach((row) => {
            const typeSelect = row.querySelector('[data-gateway-condition-type]');
            const inputArea = row.querySelector('[data-gateway-condition-inputs]');
            const isConditional = useConditions && typeSelect?.value === 'condition';
            if (inputArea) {
                inputArea.hidden = !isConditional;
                inputArea.querySelectorAll('input, select, button').forEach((control) => { control.disabled = !isConditional; });
            }
            if (isConditional) row.querySelectorAll('[data-gateway-rule-row]').forEach(updateRuleRow);
        });
    };

    card.addEventListener('change', (event) => {
        if (event.target.matches('[data-gateway-mode]')) updateCard();
        if (event.target.matches('[data-gateway-condition-type]')) updateCard();
        if (event.target.matches('[data-condition-field]')) updateRuleRow(event.target.closest('[data-gateway-rule-row]'));
    });

    card.addEventListener('click', (event) => {
        const addButton = event.target.closest('[data-add-gateway-condition]');
        if (addButton) {
            const conditionArea = addButton.closest('[data-gateway-condition-inputs]');
            const list = conditionArea?.querySelector('[data-gateway-rule-list]');
            const template = conditionArea?.querySelector('[data-gateway-rule-template]');
            if (!(list instanceof HTMLElement) || !(template instanceof HTMLTemplateElement)) return;
            const usedIndexes = [...list.querySelectorAll('[name]')].map((control) => Number(control.name.match(/\[conditions\]\[(\d+)\]/)?.[1] ?? -1));
            const index = Math.max(-1, ...usedIndexes) + 1;
            const row = template.content.cloneNode(true);
            row.querySelectorAll('[name]').forEach((control) => {
                control.name = control.name.replaceAll('__RULE_INDEX__', String(index));
            });
            list.append(row);
            const addedRow = list.lastElementChild;
            if (addedRow) updateRuleRow(addedRow);
            return;
        }

        const removeButton = event.target.closest('[data-remove-gateway-condition]');
        if (removeButton) {
            const row = removeButton.closest('[data-gateway-rule-row]');
            const list = row?.parentElement;
            if (row && list?.querySelectorAll('[data-gateway-rule-row]').length > 1) row.remove();
        }
    });

    updateCard();
});
