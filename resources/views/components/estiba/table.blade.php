@props(['caption'])
<div {{ $attributes->class('eui-table-scroll') }} role="region" aria-label="{{ $caption }}" tabindex="0">
    <table class="eui-table">
        <caption>{{ $caption }}</caption>
        <thead>{{ $head }}</thead>
        <tbody>{{ $slot }}</tbody>
    </table>
</div>
