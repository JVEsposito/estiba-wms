@props(['title', 'tone' => 'info', 'live' => false])
@php($tone = in_array($tone, ['neutral', 'info', 'success', 'warning', 'critical', 'reserved', 'temporary', 'blocked'], true) ? $tone : 'info')
<div {{ $attributes->class(['eui-alert', 'eui-tone-'.$tone]) }} @if($live) role="{{ in_array($tone, ['critical', 'blocked'], true) ? 'alert' : 'status' }}" @endif>
    <x-estiba.icon :name="in_array($tone, ['warning', 'critical', 'blocked'], true) ? 'warning' : 'info'" />
    <div class="eui-alert__body"><p class="eui-alert__title">{{ $title }}</p>@if($slot->isNotEmpty())<div class="eui-alert__detail">{{ $slot }}</div>@endif</div>
</div>
