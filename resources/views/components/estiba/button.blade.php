@props(['variant' => 'primary', 'type' => 'button', 'disabled' => false, 'busy' => false, 'icon' => null])
@php
    $variant = in_array($variant, ['primary', 'secondary', 'critical', 'confirm'], true) ? $variant : 'primary';
    $type = in_array($type, ['button', 'submit', 'reset'], true) ? $type : 'button';
@endphp
<button {{ $attributes->class(['eui-button', 'eui-button--'.$variant]) }} type="{{ $type }}" @disabled($disabled || $busy) @if($busy) aria-busy="true" @endif>
    @if($icon)<x-estiba.icon :name="$icon" />@endif
    <span>{{ $slot }}</span>
</button>
