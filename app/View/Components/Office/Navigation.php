<?php

namespace App\View\Components\Office;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Navigation extends Component
{
    public function __construct(
        public string $domain,
        public string $office,
        public string $context = 'OPERACIÓN DE OFICINA',
        public string $icon = '◆',
    ) {}

    public function render(): View|Closure|string
    {
        return function (): string {
            $html = view('components.office.navigation', [
                'domain' => $this->domain,
                'office' => $this->office,
                'context' => $this->context,
                'icon' => $this->icon,
            ])->render();

            if ($this->domain !== 'frigorifico') {
                return $html;
            }

            $active = $this->office === 'repaletizajes' ? ' class="is-active"' : '';
            $link = sprintf(
                '<a%s data-office-key="repaletizajes" data-office-domain="frigorifico" data-navigation-permissions="puede_consultar_validaciones_pallet" data-navigation-module="frigorifico.validacion" href="/oficina/validacion/repaletizajes">Repaletizajes</a>',
                $active,
            );
            $position = strrpos($html, '</nav>');

            return $position === false
                ? $html.$link
                : substr($html, 0, $position).$link.substr($html, $position);
        };
    }
}
