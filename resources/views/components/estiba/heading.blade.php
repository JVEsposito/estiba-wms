@props(['title', 'eyebrow' => null, 'description' => null, 'level' => 'h1'])
@php($level = in_array($level, ['h1', 'h2', 'h3'], true) ? $level : 'h1')
<div {{ $attributes->class('eui-heading') }}>
    <div class="eui-heading__copy">
        @if($eyebrow)<p class="eui-heading__eyebrow">{{ $eyebrow }}</p>@endif
        <{{ $level }} class="eui-heading__title">{{ $title }}</{{ $level }}>
        @if($description)<p class="eui-heading__description">{{ $description }}</p>@endif
    </div>
    @if($slot->isNotEmpty())<div class="eui-actions">{{ $slot }}</div>@endif
</div>
