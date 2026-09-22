@props([
    'variant' => 'lockup-horizontal',
    'surface' => 'light',
    'alt' => 'FoliOS',
    'inline' => false,
])

@php
    $allowedVariants = ['symbol', 'lockup-horizontal', 'lockup-stacked'];
    $resolvedVariant = in_array($variant, $allowedVariants, true) ? $variant : 'lockup-horizontal';
    $darkSuffix = $surface === 'dark' ? '-on-dark' : '';
    $relativePath = "brand/folios/folios-{$resolvedVariant}{$darkSuffix}.svg";
    $source = $inline
        ? 'data:image/svg+xml;base64,'.base64_encode(file_get_contents(public_path($relativePath)))
        : asset($relativePath);
@endphp

<img
    src="{{ $source }}"
    alt="{{ $alt }}"
    {{ $attributes->class(['folios-logo', "folios-logo--{$resolvedVariant}"]) }}
>
