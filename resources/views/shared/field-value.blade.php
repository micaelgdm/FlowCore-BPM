@if (is_array($value) && filled($value['attachment_id'] ?? null))
    <a class="fc-attachment-link" href="{{ route('portal.attachments.download', [$instance->uuid, $value['attachment_id']]) }}">📎 {{ $value['name'] ?? 'Baixar anexo' }}</a>
    <small class="fc-attachment-size">{{ number_format(((int) ($value['size'] ?? 0)) / 1024, 0, ',', '.') }} KB</small>
@elseif (is_bool($value))
    {{ $value ? 'Sim' : 'Não' }}
@elseif (is_scalar($value))
    {{ $value }}
@else
    —
@endif
