@props(['label', 'value' => null, 'total' => null, 'detail' => null, 'tone' => 'info'])
@php
    $tone = in_array($tone, ['neutral', 'info', 'success', 'warning', 'critical', 'reserved', 'temporary', 'blocked'], true) ? $tone : 'info';
    $available = is_numeric($value) && is_numeric($total) && is_finite((float) $value) && is_finite((float) $total) && $value >= 0 && $total > 0;
    $percent = $available ? max(0, min(100, round($value / $total * 100))) : null;
@endphp
<div {{ $attributes->class(['eui-metric', 'eui-tone-'.$tone]) }}>
    <div class="eui-metric__heading"><span>{{ $label }}</span><strong class="eui-metric__value">{{ $available ? $value.' / '.$total : 'Sin registro' }}</strong></div>
    @if($available)
        <div class="eui-meter" role="progressbar" aria-label="{{ $label }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}" aria-valuetext="{{ $value }} de {{ $total }}">
            <div class="eui-meter__fill" style="width: {{ $percent }}%"></div>
        </div>
    @endif
    @if($detail)<p class="eui-metric__detail">{{ $detail }}</p>@endif
</div>
