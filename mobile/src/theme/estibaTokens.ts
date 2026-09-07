// Generado desde design/estiba.tokens.json. Ejecutar npm run design:tokens.
export const estibaTokens = {
  "color": {
    "navy": "#183442",
    "onNavy": "#FFFFFF",
    "canvas": "#F3F5F7",
    "surface": "#FFFFFF",
    "subtle": "#F7F9FA",
    "border": "#CCD6DE",
    "inputBorder": "#798B99",
    "text": "#182B3A",
    "muted": "#536777",
    "primary": "#145DA0",
    "primaryHover": "#0D477D",
    "confirmHover": "#124E31",
    "onPrimary": "#FFFFFF",
    "selected": "#EAF2F9",
    "focus": "#145DA0",
    "disabledSurface": "#E8EDF1",
    "disabledText": "#536777"
  },
  "signal": {
    "neutral": {
      "text": "#536777",
      "surface": "#F1F4F6",
      "border": "#A4B2BD"
    },
    "info": {
      "text": "#145DA0",
      "surface": "#EAF2F9",
      "border": "#6799C1"
    },
    "success": {
      "text": "#19633F",
      "surface": "#EDF6F0",
      "border": "#77A68A"
    },
    "warning": {
      "text": "#805200",
      "surface": "#FFF5DE",
      "border": "#BE923D"
    },
    "critical": {
      "text": "#AD2332",
      "surface": "#FDEFF0",
      "border": "#CC7881"
    },
    "reserved": {
      "text": "#805200",
      "surface": "#FFF5DE",
      "border": "#BE923D"
    },
    "temporary": {
      "text": "#914012",
      "surface": "#FFF1E7",
      "border": "#C38E6E"
    },
    "blocked": {
      "text": "#AD2332",
      "surface": "#FDEFF0",
      "border": "#CC7881"
    }
  },
  "space": {
    "1": 4,
    "2": 8,
    "3": 12,
    "4": 16,
    "6": 24,
    "8": 32,
    "12": 48
  },
  "radius": {
    "control": 4,
    "panel": 6
  },
  "fontSize": {
    "caption": 12,
    "small": 14,
    "body": 16,
    "heading": 20,
    "title": 28,
    "display": 36
  },
  "fontWeight": {
    "regular": 400,
    "medium": 500,
    "strong": 600,
    "bold": 700
  },
  "lineHeight": {
    "body": 1.5,
    "heading": 1.2
  },
  "density": {
    "comfortable": {
      "control": 44,
      "row": 44,
      "cellPadding": 12
    },
    "compact": {
      "control": 36,
      "row": 36,
      "cellPadding": 8
    },
    "touch": {
      "control": 56,
      "row": 56,
      "cellPadding": 16
    }
  }
} as const;

export type EstibaTone = keyof typeof estibaTokens.signal;
