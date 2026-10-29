@php($fieldNamePrefix = $namePrefix ?? 'fields')
@php($fieldErrorPrefix = $errorPrefix ?? 'fields')
@php($savedCondition = $field['visible_when'] ?? [])
@php($legacyCondition = is_array($savedCondition) && isset($savedCondition['field']))
@php($conditionMode = old("{$fieldErrorPrefix}.{$index}.visible_when_mode", $legacyCondition ? 'hidden_unless' : ($savedCondition['mode'] ?? 'always')))
@php($conditionMatch = old("{$fieldErrorPrefix}.{$index}.visible_when_match", $savedCondition['match'] ?? 'all'))
@php($conditionRows = $legacyCondition ? [['field' => $savedCondition['field'], 'operator' => 'eq', 'value' => $savedCondition['equals'] ?? '']] : ($savedCondition['conditions'] ?? []))
@php($conditionRows = old("{$fieldErrorPrefix}.{$index}.conditions", $conditionRows ?: [['field' => '', 'operator' => 'eq', 'value' => '']]))
<fieldset class="fc-form-field-card" data-form-field data-form-index="{{ $index }}">
    <div class="fc-form-field-heading"><strong>Campo do formulário</strong><button type="button" class="fc-button fc-button-secondary" data-remove-field>Remover</button></div>
    <div class="fc-form-field-grid">
        <label class="fc-form-field"><span>Identificador (sem espaços)</span><input name="{{ $fieldNamePrefix }}[{{ $index }}][key]" value="{{ $field['key'] ?? '' }}" placeholder="centro_custo" pattern="[a-z][a-z0-9_]{0,49}" data-field-key><small>Use letras minúsculas, números e _.</small></label>
        <label class="fc-form-field"><span>Nome exibido</span><input name="{{ $fieldNamePrefix }}[{{ $index }}][label]" value="{{ $field['label'] ?? '' }}" placeholder="Centro de custo" maxlength="100" data-field-label></label>
        <label class="fc-form-field"><span>Tipo de campo</span><select name="{{ $fieldNamePrefix }}[{{ $index }}][type]" data-field-type>
            @foreach (['text' => 'Texto curto', 'textarea' => 'Texto longo', 'number' => 'Número', 'date' => 'Data', 'select' => 'Lista de opções', 'boolean' => 'Sim ou não', 'file' => 'Anexo'] as $value => $label)
                <option value="{{ $value }}" @selected(($field['type'] ?? 'text') === $value)>{{ $label }}</option>
            @endforeach
        </select></label>
        <label class="fc-form-required"><input type="checkbox" name="{{ $fieldNamePrefix }}[{{ $index }}][required]" value="1" @checked($field['required'] ?? false)> Obrigatório</label>
        <label class="fc-form-field fc-form-field-options" data-field-options @if (($field['type'] ?? 'text') !== 'select') hidden @endif><span>Opções (uma por linha)</span><textarea name="{{ $fieldNamePrefix }}[{{ $index }}][options]" rows="4" maxlength="2000" placeholder="Operações&#10;Financeiro&#10;Tecnologia">{{ is_array($field['options'] ?? null) ? implode("\n", $field['options']) : ($field['options'] ?? '') }}</textarea></label>
    </div>
    <section class="fc-visibility-rules" data-visibility-rules>
        <label class="fc-form-field"><span>Condições de exibição do campo</span>
            <select name="{{ $fieldNamePrefix }}[{{ $index }}][visible_when_mode]" data-condition-mode>
                <option value="always" @selected($conditionMode === 'always')>Sempre exibido</option>
                <option value="hidden_unless" @selected($conditionMode === 'hidden_unless')>Escondido a menos que</option>
                <option value="shown_unless" @selected($conditionMode === 'shown_unless')>Exibido a menos que</option>
            </select>
        </label>
        <div data-condition-editor @if ($conditionMode === 'always') hidden @endif>
            <div class="fc-condition-match"><label class="fc-form-field"><span>Combinar condições</span><select name="{{ $fieldNamePrefix }}[{{ $index }}][visible_when_match]" data-condition-match><option value="all" @selected($conditionMatch === 'all')>AND — todas</option><option value="any" @selected($conditionMatch === 'any')>OR — qualquer uma</option></select></label></div>
            <div class="fc-condition-list" data-condition-list>
                @foreach ($conditionRows as $conditionIndex => $condition)
                    <div class="fc-condition-row" data-condition-row>
                        <label class="fc-form-field"><span>Campo anterior</span><div class="fc-condition-field-picker" data-condition-field-picker>
                            <button type="button" class="fc-condition-field-trigger" data-field-picker-toggle aria-expanded="false"><span data-field-picker-label>Selecione um campo</span><span aria-hidden="true">⌄</span></button>
                            <div class="fc-condition-field-menu" data-field-picker-menu hidden><input type="search" placeholder="Pesquisar campo" aria-label="Pesquisar campo" data-field-picker-search><div data-field-picker-options role="listbox"></div></div>
                            <select class="fc-condition-field-native" name="{{ $fieldNamePrefix }}[{{ $index }}][conditions][{{ $conditionIndex }}][field]" data-condition-field-select data-selected-field="{{ $condition['field'] ?? '' }}" aria-label="Campo anterior"><option value="">Selecione um campo</option></select>
                        </div></label>
                        <label class="fc-form-field"><span>Operador</span><select name="{{ $fieldNamePrefix }}[{{ $index }}][conditions][{{ $conditionIndex }}][operator]"><option value="eq" @selected(($condition['operator'] ?? 'eq') === 'eq')>Igual a (=)</option><option value="neq" @selected(($condition['operator'] ?? '') === 'neq')>Diferente de (≠)</option><option value="contains" @selected(($condition['operator'] ?? '') === 'contains')>Contém</option><option value="not_contains" @selected(($condition['operator'] ?? '') === 'not_contains')>Não contém</option></select></label>
                        <label class="fc-form-field"><span>Valor</span><input name="{{ $fieldNamePrefix }}[{{ $index }}][conditions][{{ $conditionIndex }}][value]" value="{{ $condition['value'] ?? '' }}" maxlength="255" placeholder="Informe o valor"></label>
                        <button type="button" class="fc-button fc-button-secondary fc-condition-remove" data-remove-condition aria-label="Remover condição">−</button>
                        @foreach (['field', 'operator', 'value'] as $conditionErrorField)
                            @error("{$fieldErrorPrefix}.{$index}.conditions.{$conditionIndex}.{$conditionErrorField}") <small class="fc-form-error">{{ $message }}</small> @enderror
                        @endforeach
                    </div>
                @endforeach
            </div>
            <button type="button" class="fc-button fc-button-secondary" data-add-condition>+ Adicionar condição</button>
        </div>
    </section>
    @foreach (['key', 'label', 'type', 'required', 'options', 'visible_when_mode', 'visible_when_match'] as $errorField)
        @error("{$fieldErrorPrefix}.{$index}.{$errorField}") <small class="fc-form-error">{{ $message }}</small> @enderror
    @endforeach
    @error("{$fieldErrorPrefix}.{$index}.conditions") <small class="fc-form-error">{{ $message }}</small> @enderror
</fieldset>
