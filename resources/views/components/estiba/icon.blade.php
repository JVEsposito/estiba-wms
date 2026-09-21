{{-- Rediseño 2026-09-21 ("Manifiesto"): los 12 trazos originales se redibujaron a esquina
     recta (heredan stroke-linecap:square/stroke-linejoin:miter de .eui-icon en
     resources/css/estiba-ui.css, no redondeado) y el set se amplió a 20 íconos. Los 12
     nombres originales no cambiaron, solo su geometría — ver design/estiba.tokens.json y
     resources/css/estiba-ui.css § identidad para el resto del rediseño. --}}
@props(['name' => 'info'])
<svg {{ $attributes->class('eui-icon') }} viewBox="0 0 24 24" aria-hidden="true" focusable="false">
    @switch($name)
        @case('arrow-right')<path d="M4 12h12m0-6 6 6-6 6" />@break
        @case('check')<path d="m5 12 4 4L19 6" />@break
        @case('warning')<path d="M12 3 2 21h20L12 3Z" /><path d="M12 9v5m0 3v.1" />@break
        @case('warehouse')<path d="M2 11 12 4l10 7" /><rect x="4" y="11" width="16" height="9" /><rect x="9" y="14" width="6" height="6" /><path d="M9 17h6" />@break
        @case('pallet')<path d="M4 6h16M4 10h16M4 14h16M4 6v8M20 6v8M4 18h16" /><path d="M7 18v3M17 18v3" />@break
        @case('snowflake')<path d="M12 2v20M4 6l16 12M20 6 4 18M8.5 4 12 6l3.5-2M8.5 20l3.5-2 3.5 2M3.5 10 7 12l-.5 4M20.5 10 17 12l.5 4" />@break
        @case('boxes')<path d="m12 3 4.5 2.5L12 8 7.5 5.5 12 3ZM7 6.5l4.5 2.5v5L7 11.5v-5Zm10 0L12.5 9v5l4.5-2.5v-5ZM6.5 13 11 15.5 6.5 18 2 15.5 6.5 13Zm11 0 4.5 2.5-4.5 2.5-4.5-2.5 4.5-2.5ZM2 16.5 6.5 19v2L2 18.5v-2Zm9 0v2L6.5 21v-2l4.5-2.5Zm2 0 4.5 2.5v2L13 18.5v-2Zm9 0v2L17.5 21v-2l4.5-2.5Z" />@break
        @case('settings')<path d="M12 3 4.5 7.5v9L12 21l7.5-4.5v-9L12 3Z" /><circle cx="12" cy="12" r="3" />@break
        @case('search')<circle cx="10" cy="10" r="6.5" /><path d="m15 15 5.5 5.5" />@break
        @case('refresh')<path d="M20 7v5h-5M4 17v-5h5M5 8a8 8 0 0 1 13-3l2 3M4 16l2 3a8 8 0 0 0 13-3" />@break
        @case('clock')<circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" />@break
        @case('lock')<rect x="5" y="10" width="14" height="11" /><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3" />@break
        @case('thermometer')<rect x="10" y="3" width="4" height="11" rx="2" /><circle cx="12" cy="17" r="3.5" /><path d="M12 6v8" />@break
        @case('scale')<rect x="2" y="15" width="20" height="4" /><path d="M5 19v2M19 19v2" /><rect x="8" y="3" width="8" height="6" /><path d="M12 9v6" />@break
        @case('truck')<path d="M3 16V7h10v9" /><path d="M13 11h5l3 3v2h-3" /><circle cx="7" cy="18" r="2" /><circle cx="17" cy="18" r="2" /><path d="M3 18h2m6 0h4" />@break
        @case('dock')<rect x="4" y="4" width="16" height="16" /><path d="M4 15h16" /><path d="M6 15l2 3m2-3 2 3m2-3 2 3" />@break
        @case('forklift')<path d="M4 16V9h6v7" /><path d="M10 16h3V6" /><path d="M13 6h3" /><circle cx="7" cy="19" r="2" /><circle cx="15" cy="19" r="2" /><path d="M4 19h1m6 0h3" />@break
        @case('ticket')<path d="M5 4h14v16l-3-2-3 2-3-2-3 2V4Z" /><path d="M8 9h8M8 13h5" />@break
        @case('print')<path d="M6 9V4h12v5" /><rect x="4" y="9" width="16" height="7" /><path d="M7 16v4h10v-4" />@break
        @case('filter')<path d="M4 5h16l-6 8v6l-4 2v-8L4 5Z" />@break
        @default<circle cx="12" cy="12" r="9" /><path d="M12 11v6m0-10v.1" />
    @endswitch
</svg>
