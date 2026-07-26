import { NativeModule, requireOptionalNativeModule } from 'expo';

export type PrinterSocketResult = {
  status: 'connected' | 'sent' | 'failed' | 'indeterminate';
  bytesSent: number;
  message: string;
};

declare class EstibaPrinterSocketModule extends NativeModule {
  testConnectionAsync(host: string, port: number, timeoutMs: number): Promise<PrinterSocketResult>;
  sendAsync(host: string, port: number, payload: string, timeoutMs: number): Promise<PrinterSocketResult>;
}

const nativeModule = requireOptionalNativeModule<EstibaPrinterSocketModule>('EstibaPrinterSocket');

export function isDirectPrinterAvailable(): boolean {
  return nativeModule !== null;
}

export async function testPrinterConnection(
  host: string,
  port: number,
  timeoutMs = 5000,
): Promise<PrinterSocketResult> {
  if (!nativeModule) {
    throw new Error('La impresión IP requiere instalar una APK que incluya el módulo nativo.');
  }

  return nativeModule.testConnectionAsync(host, port, timeoutMs);
}

export async function sendToPrinter(
  host: string,
  port: number,
  payload: string,
  timeoutMs = 10000,
): Promise<PrinterSocketResult> {
  if (!nativeModule) {
    throw new Error('La impresión IP requiere instalar una APK que incluya el módulo nativo.');
  }

  return nativeModule.sendAsync(host, port, payload, timeoutMs);
}
