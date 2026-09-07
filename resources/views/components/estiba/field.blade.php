@props(['id', 'label', 'hint' => null, 'error' => null])
<div class="eui-field">
    <label for="{{ $id }}">{{ $label }}</label>
    <input id="{{ $id }}" {{ $attributes->class('eui-input')->merge([
        'type' => 'text',
        'aria-invalid' => $error ? 'true' : null,
        'aria-describedby' => ($hint || $error) ? trim(($hint ? $id.'-hint ' : '').($error ? $id.'-error' : '')) : null,
    ]) }}>
    @if($hint)<span class="eui-field__hint" id="{{ $id }}-hint">{{ $hint }}</span>@endif
    @if($error)<span class="eui-field__error" id="{{ $id }}-error">{{ $error }}</span>@endif
</div>
