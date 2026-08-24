export const DEFAULT_API_TIMEOUT_MS = 15_000;

export class ApiRequestTimeoutError extends Error {
  constructor(public readonly timeoutMs: number) {
    super(`La solicitud superó el tiempo máximo de ${Math.round(timeoutMs / 1_000)} segundos.`);
    this.name = 'ApiRequestTimeoutError';
  }
}

export async function fetchWithTimeout(
  input: string,
  init: RequestInit = {},
  timeoutMs = DEFAULT_API_TIMEOUT_MS,
): Promise<Response> {
  const controller = new AbortController();
  const upstreamSignal = init.signal;
  const abortFromUpstream = () => controller.abort();

  if (upstreamSignal?.aborted) {
    controller.abort();
  } else {
    upstreamSignal?.addEventListener('abort', abortFromUpstream, { once: true });
  }

  let expired = false;
  const timer = setTimeout(() => {
    expired = true;
    controller.abort();
  }, timeoutMs);

  try {
    return await fetch(input, {
      ...init,
      signal: controller.signal,
    });
  } catch (reason) {
    if (expired) throw new ApiRequestTimeoutError(timeoutMs);
    throw reason;
  } finally {
    clearTimeout(timer);
    upstreamSignal?.removeEventListener('abort', abortFromUpstream);
  }
}
