@props(['title', 'description'])
<div {{ $attributes->class('eui-empty') }}>
    <p class="eui-empty__title">{{ $title }}</p>
    <p class="eui-empty__description">{{ $description }}</p>
    @if($slot->isNotEmpty())<div class="eui-empty__action">{{ $slot }}</div>@endif
</div>
