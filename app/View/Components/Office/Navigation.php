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

            $links = [
                [
                    'key' => 'repaletizajes',
                    'label' => 'Repaletizajes',
                    'href' => '/oficina/validacion/repaletizajes',
                ],
                [
                    'key' => 'anulaciones-validacion',
                    'label' => 'Anulaciones',
                    'href' => '/oficina/validacion/anulaciones',
                ],
            ];

            $extras = collect($links)->map(function (array $link): string {
                $active = $this->office === $link['key'] ? ' class="is-active"' : '';

                return sprintf(
                    '<a%s data-office-key="%s" data-office-domain="frigorifico" data-navigation-permissions="puede_consultar_validaciones_pallet" data-navigation-module="frigorifico.validacion" href="%s">%s</a>',
                    $active,
                    $link['key'],
                    $link['href'],
                    $link['label'],
                );
            })->implode('');
            $position = strrpos($html, '</nav>');

            return $position === false
                ? $html.$extras
                : substr($html, 0, $position).$extras.substr($html, $position);
        };
    }
}
