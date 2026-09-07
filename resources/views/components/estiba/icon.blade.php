@props(['name' => 'info'])
<svg {{ $attributes->class('eui-icon') }} viewBox="0 0 24 24" aria-hidden="true" focusable="false">
    @switch($name)
        @case('arrow-right')<path d="M4 12h16m-6-6 6 6-6 6" />@break
        @case('check')<path d="m5 12 4 4L19 6" />@break
        @case('warning')<path d="M12 3 2 21h20L12 3Z" /><path d="M12 9v5m0 3v.1" />@break
        @case('warehouse')<path d="m3 9 9-6 9 6v12H3V9Zm4 12V11h10v10M7 15h10M7 18h10" />@break
        @case('pallet')<path d="M3 18h18M4 21v-3m8 3v-3m8 3v-3M5 4h14v11H5V4Zm7 0v5" />@break
        @case('refresh')<path d="M20 7v5h-5M4 17v-5h5M5 8a8 8 0 0 1 13-3l2 3M4 16l2 3a8 8 0 0 0 13-3" />@break
        @case('clock')<circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" />@break
        @case('lock')<rect x="5" y="10" width="14" height="11" rx="1" /><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3" />@break
        @default<circle cx="12" cy="12" r="9" /><path d="M12 11v6m0-10v.1" />
    @endswitch
</svg>
