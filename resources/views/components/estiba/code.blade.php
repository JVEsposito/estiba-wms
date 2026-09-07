@props(['large' => false])
<span {{ $attributes->class(['eui-code', 'eui-code--large' => $large]) }}>{{ $slot }}</span>
