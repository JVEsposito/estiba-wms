<?php

namespace App\Enums;

enum CategoriaOperacionalMaterial: string
{
    case Insumo = 'insumo';
    case MaterialMp = 'material_mp';
    case MaterialPt = 'material_pt';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Insumo => 'Insumo',
            self::MaterialMp => 'Material de embalaje sin preparar',
            self::MaterialPt => 'Material preparado para línea',
        };
    }
}
