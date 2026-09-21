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
    "disabledText": "#536777",
    "frost": "#0F5C6E",
    "frostStrong": "#0D4A57"
  },
  "colorDark": {
    "canvas": "#0B161D",
    "surface": "#101F28",
    "subtle": "#16262F",
    "border": "#2A3F49",
    "inputBorder": "#5C7E8C",
    "text": "#EEF3F5",
    "muted": "#9BB0BB",
    "primary": "#3F8FD1",
    "primaryHover": "#5AA3DD",
    "confirmHover": "#2F8A5C",
    "onPrimary": "#06141C",
    "selected": "#16303F",
    "focus": "#3F8FD1",
    "disabledSurface": "#182931",
    "disabledText": "#6C8089",
    "frost": "#5FD3E6",
    "frostStrong": "#8DE3F0"
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
  "signalDark": {
    "neutral": {
      "text": "#9BB0BB",
      "surface": "#182931",
      "border": "#3C5560"
    },
    "info": {
      "text": "#6FB6EA",
      "surface": "#132738",
      "border": "#3F6B8C"
    },
    "success": {
      "text": "#6BCF9A",
      "surface": "#0F2A1E",
      "border": "#3A7358"
    },
    "warning": {
      "text": "#F0C05A",
      "surface": "#2C2308",
      "border": "#8A6C22"
    },
    "critical": {
      "text": "#F0919B",
      "surface": "#301419",
      "border": "#8A4650"
    },
    "reserved": {
      "text": "#F0C05A",
      "surface": "#2C2308",
      "border": "#8A6C22"
    },
    "temporary": {
      "text": "#F0A874",
      "surface": "#2E1D0F",
      "border": "#8A5C34"
    },
    "blocked": {
      "text": "#F0919B",
      "surface": "#301419",
      "border": "#8A4650"
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
    "control": 2,
    "panel": 0
  },
  "rule": {
    "hairline": 1,
    "strong": 2,
    "heavy": 3
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
  "fontFamily": {
    "sans": "\"Segoe UI\", Roboto, Helvetica, Arial, sans-serif",
    "condensed": "\"Roboto Condensed\", \"Arial Narrow\", \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif"
  },
  "manifest": {
    "eyebrow": {
      "fontSize": "11px",
      "letterSpacing": "0.08em",
      "lineHeight": "1.4",
      "fontWeight": "700"
    },
    "panelTitle": {
      "fontSize": "15px",
      "letterSpacing": "0.02em",
      "lineHeight": "1.2",
      "fontWeight": "700"
    },
    "masthead": {
      "fontSize": "28px",
      "letterSpacing": "0.01em",
      "lineHeight": "1.1",
      "fontWeight": "700"
    }
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
