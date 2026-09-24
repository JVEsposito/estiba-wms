/**
 * Confirmación física antes de retirar un pallet: el camarero digita los
 * últimos 4 dígitos que lee en la etiqueta y su PIN operacional. Replica las
 * reglas de ServicioConfirmacionInicioTarea y ServicioPinOperacional; el
 * servidor vuelve a validar y conserva la evidencia.
 */

export const FOLIO_CONFIRMATION_LENGTH = 4;
export const OPERATOR_PIN_LENGTH = 4;

export function expectedFolioDigits(folioNumber: string): string {
  const digits = folioNumber.replace(/\D/g, '');
  const base = digits !== '' ? digits : folioNumber.trim().toUpperCase();
  return base.slice(-FOLIO_CONFIRMATION_LENGTH);
}

/** Cantidad de dígitos que debe digitar el camarero (menos de 4 en folios cortos). */
export function folioConfirmationLength(folioNumber: string): number {
  return expectedFolioDigits(folioNumber).length;
}

/** Divide el folio para destacar la parte que se debe leer en la etiqueta. */
export function splitFolioForConfirmation(folioNumber: string): { lead: string; tail: string } {
  const expected = expectedFolioDigits(folioNumber);
  const index = folioNumber.toUpperCase().lastIndexOf(expected);
  if (expected === '' || index < 0) return { lead: folioNumber, tail: '' };
  return { lead: folioNumber.slice(0, index), tail: folioNumber.slice(index) };
}

/** Acepta los últimos dígitos o el folio completo leído por un escáner. */
export function matchesFolioConfirmation(folioNumber: string, entered: string): boolean {
  const value = entered.trim().toUpperCase();
  if (value === '') return false;
  return value === folioNumber.trim().toUpperCase() || value === expectedFolioDigits(folioNumber);
}

/** Devuelve un mensaje cuando el PIN no es aceptable, o null si puede enviarse. */
export function operatorPinProblem(pin: string): string | null {
  if (!/^\d{4}$/.test(pin)) return 'El PIN debe tener exactamente 4 dígitos.';
  const digits = [...pin].map(Number);
  const steps = new Set([0, 1, 2].map((index) => digits[index + 1] - digits[index]));
  const [step] = [...steps];
  if (steps.size === 1 && [-1, 0, 1].includes(step)) {
    return 'Elige un PIN menos predecible: no repitas ni sigas dígitos consecutivos.';
  }
  return null;
}
