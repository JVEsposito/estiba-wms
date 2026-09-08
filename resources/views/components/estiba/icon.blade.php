@props(['name' => 'info'])
<svg {{ $attributes->class('eui-icon') }} viewBox="0 0 24 24" aria-hidden="true" focusable="false">
    @switch($name)
        @case('arrow-right')<path d="M4 12h16m-6-6 6 6-6 6" />@break
        @case('check')<path d="m5 12 4 4L19 6" />@break
        @case('warning')<path d="M12 3 2 21h20L12 3Z" /><path d="M12 9v5m0 3v.1" />@break
        @case('warehouse')<path d="m3 9 9-6 9 6v12H3V9Zm4 12V11h10v10M7 15h10M7 18h10" />@break
        @case('pallet')<path d="M3 18h18M4 21v-3m8 3v-3m8 3v-3M5 4h14v11H5V4Zm7 0v5" />@break
        @case('snowflake')<path d="M12 2v20M4 6l16 12M20 6 4 18M8.5 4 12 6l3.5-2M8.5 20l3.5-2 3.5 2M3.5 10 7 12l-.5 4M20.5 10 17 12l.5 4" />@break
        @case('boxes')<path d="m12 3 4.5 2.5L12 8 7.5 5.5 12 3ZM7 6.5l4.5 2.5v5L7 11.5v-5Zm10 0L12.5 9v5l4.5-2.5v-5ZM6.5 13 11 15.5 6.5 18 2 15.5 6.5 13Zm11 0 4.5 2.5-4.5 2.5-4.5-2.5 4.5-2.5ZM2 16.5 6.5 19v2L2 18.5v-2Zm9 0v2L6.5 21v-2l4.5-2.5Zm2 0 4.5 2.5v2L13 18.5v-2Zm9 0v2L17.5 21v-2l4.5-2.5Z" />@break
        @case('settings')<circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.86 2.86-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21H9.6v-.1A1.7 1.7 0 0 0 8.5 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06-2.86-2.86.06-.06A1.7 1.7 0 0 0 4.1 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.1-.4H2.3V9.6h.1A1.7 1.7 0 0 0 4.1 8.5a1.7 1.7 0 0 0-.34-1.88l-.06-.06L6.56 3.7l.06.06A1.7 1.7 0 0 0 8.5 4.1a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1.1v-.1h4v.1A1.7 1.7 0 0 0 15 4.1a1.7 1.7 0 0 0 1.88-.34l.06-.06 2.86 2.86-.06.06A1.7 1.7 0 0 0 19.4 8.5a1.7 1.7 0 0 0 .6 1 1.7 1.7 0 0 0 1.1.4h.1v4h-.1A1.7 1.7 0 0 0 19.4 15Z" />@break
        @case('search')<circle cx="10.5" cy="10.5" r="6.5" /><path d="m15.5 15.5 5 5" />@break
        @case('refresh')<path d="M20 7v5h-5M4 17v-5h5M5 8a8 8 0 0 1 13-3l2 3M4 16l2 3a8 8 0 0 0 13-3" />@break
        @case('clock')<circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" />@break
        @case('lock')<rect x="5" y="10" width="14" height="11" rx="1" /><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3" />@break
        @default<circle cx="12" cy="12" r="9" /><path d="M12 11v6m0-10v.1" />
    @endswitch
</svg>
