import { estibaTokens as t } from './estibaTokens';

/**
 * Tema de trabajo para camareros.
 *
 * Mantiene la identidad de Oficina, pero aumenta contraste, jerarquía y áreas
 * táctiles para el uso continuo en PDA y tablets dentro del frigorífico.
 */
export const operatorTheme = {
  color: {
    canvas: '#E7EEF2',
    surface: '#FFFFFF',
    surfaceMuted: '#F0F4F6',
    navy: '#123342',
    navyRaised: '#1C4658',
    onNavy: '#FFFFFF',
    text: '#102B3A',
    muted: '#526A78',
    border: '#A9BBC6',
    borderStrong: '#718A99',
    primary: '#0F6EAD',
    primaryPressed: '#0B5688',
    onPrimary: '#FFFFFF',
    selected: '#D9EAF5',
    success: '#147A4D',
    successSurface: '#DDF2E7',
    warning: '#855600',
    warningSurface: '#FFF0CF',
    critical: '#A92535',
    criticalSurface: '#FBE4E7',
    disabled: '#D8E1E6',
    disabledText: '#657985',
    shadow: '#071821',
  },
  space: t.space,
  radius: {
    control: 8,
    panel: 10,
  },
  type: {
    caption: 12,
    small: 14,
    body: 16,
    heading: 22,
    title: 28,
    folio: 26,
  },
  touch: {
    minimum: t.density.touch.control,
    prominent: 64,
  },
  breakpoint: {
    compact: 800,
  },
} as const;

export type OperatorTone = 'neutral' | 'info' | 'success' | 'warning' | 'critical';
