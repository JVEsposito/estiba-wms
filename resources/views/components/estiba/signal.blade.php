@props(['tone' => 'neutral'])
@php($tone = in_array($tone, ['neutral', 'info', 'success', 'warning', 'critical', 'reserved', 'temporary', 'blocked'], true) ? $tone : 'neutral')
<span {{ $attributes->class(['eui-signal', 'eui-tone-'.$tone]) }}><span class="eui-signal__marker" aria-hidden="true"></span><span>{{ $slot }}</span></span>
