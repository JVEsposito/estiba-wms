@props(['title', 'flush' => false])
<section {{ $attributes->class('eui-panel')->merge(['aria-label' => $title]) }}>
    <header class="eui-panel__header">
        <h2 class="eui-panel__title">{{ $title }}</h2>
        @isset($actions)<div class="eui-actions">{{ $actions }}</div>@endisset
    </header>
    <div @class(['eui-panel__body' => ! $flush])>{{ $slot }}</div>
</section>
