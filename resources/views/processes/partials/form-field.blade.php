@php($fieldNamePrefix = $namePrefix ?? 'fields')
<fieldset class="fc-form-field-card" data-form-field data-form-index="{{ $index }}">
    <div class="fc-form-field-heading"><strong>Campo do formulário</strong><button type="button" class="fc-button fc-button-secondary" data-remove-field>Remover</button></div>
    <div class="fc-form-field-grid">
        <label class="fc-form-field"><span>Identificador (sem espaços)</span><input name="{{ $fieldNamePrefix }}[{{ $index }}][key]" value="{{ $field['key'] ?? '' }}" placeholder="centro_custo" pattern="[a-z][a-z0-9_]{0,49}"><small>Use letras minúsculas, números e _.</small></label>
        <label class="fc-form-field"><span>Nome exibido</span><input name="{{ $fieldNamePrefix }}[{{ $index }}][label]" value="{{ $field['label'] ?? '' }}" placeholder="Centro de custo" maxlength="100"></label>
        <label class="fc-form-field"><span>Tipo de campo</span><select name="{{ $fieldNamePrefix }}[{{ $index }}][type]" data-field-type>
            @foreach (['text' => 'Texto curto', 'textarea' => 'Texto longo', 'number' => 'Número', 'date' => 'Data', 'select' => 'Lista de opções', 'boolean' => 'Sim ou não'] as $value => $label)
                <option value="{{ $value }}" @selected(($field['type'] ?? 'text') === $value)>{{ $label }}</option>
            @endforeach
        </select></label>
        <label class="fc-form-required"><input type="checkbox" name="{{ $fieldNamePrefix }}[{{ $index }}][required]" value="1" @checked($field['required'] ?? false)> Obrigatório</label>
        <label class="fc-form-field fc-form-field-options" data-field-options @if (($field['type'] ?? 'text') !== 'select') hidden @endif><span>Opções (uma por linha)</span><textarea name="{{ $fieldNamePrefix }}[{{ $index }}][options]" rows="4" maxlength="2000" placeholder="Operações&#10;Financeiro&#10;Tecnologia">{{ is_array($field['options'] ?? null) ? implode("\n", $field['options']) : ($field['options'] ?? '') }}</textarea></label>
    </div>
    @foreach (['key', 'label', 'type', 'required', 'options'] as $errorField)
        @error("fields.{$index}.{$errorField}") <small class="fc-form-error">{{ $message }}</small> @enderror
    @endforeach
</fieldset>
