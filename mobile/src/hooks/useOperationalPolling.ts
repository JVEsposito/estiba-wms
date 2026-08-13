import { useEffect, useRef } from 'react';
import { AppState } from 'react-native';

import { OPERATIONAL_POLL_INTERVAL_MS } from '../config/polling';

type OperationalPollingOptions = {
  enabled?: boolean;
  intervalMs?: number;
  jitterRatio?: number;
  maxBackoffMs?: number;
  onError?: (reason: unknown) => void;
  onResume?: () => Promise<unknown> | unknown;
};

function nextDelay(
  intervalMs: number,
  failures: number,
  jitterRatio: number,
  maxBackoffMs: number,
) {
  const backoff = Math.min(maxBackoffMs, intervalMs * (2 ** Math.min(failures, 4)));
  const jitter = backoff * jitterRatio * ((Math.random() * 2) - 1);
  return Math.max(250, Math.round(backoff + jitter));
}

/**
 * Refresco operacional sin superposición, detenido en segundo plano y con
 * desfase aleatorio para que las PDA no consulten al servidor al mismo tiempo.
 */
export function useOperationalPolling(
  task: () => Promise<unknown> | unknown,
  options: OperationalPollingOptions = {},
) {
  const taskRef = useRef(task);
  const onErrorRef = useRef(options.onError);
  const onResumeRef = useRef(options.onResume);
  taskRef.current = task;
  onErrorRef.current = options.onError;
  onResumeRef.current = options.onResume;

  const enabled = options.enabled ?? true;
  const intervalMs = Math.max(1_000, options.intervalMs ?? OPERATIONAL_POLL_INTERVAL_MS);
  const jitterRatio = Math.min(0.45, Math.max(0, options.jitterRatio ?? 0.15));
  const maxBackoffMs = Math.max(intervalMs, options.maxBackoffMs ?? 5 * 60_000);

  useEffect(() => {
    if (!enabled) return undefined;

    let stopped = false;
    let running = false;
    let failures = 0;
    let timer: ReturnType<typeof setTimeout> | null = null;

    const clearScheduled = () => {
      if (timer === null) return;
      clearTimeout(timer);
      timer = null;
    };

    const schedule = (delay?: number, resume = false) => {
      clearScheduled();
      if (stopped || AppState.currentState !== 'active') return;
      timer = setTimeout(() => {
        timer = null;
        void execute(resume);
      }, delay ?? nextDelay(intervalMs, failures, jitterRatio, maxBackoffMs));
    };

    const execute = async (resume = false) => {
      if (stopped || running || AppState.currentState !== 'active') return;
      running = true;
      try {
        if (resume && onResumeRef.current) await onResumeRef.current();
        else await taskRef.current();
        failures = 0;
      } catch (reason) {
        failures += 1;
        onErrorRef.current?.(reason);
      } finally {
        running = false;
        schedule();
      }
    };

    const subscription = AppState.addEventListener('change', (state) => {
      if (state !== 'active') {
        clearScheduled();
        return;
      }
      schedule(350 + Math.round(Math.random() * 500), true);
    });

    schedule();

    return () => {
      stopped = true;
      clearScheduled();
      subscription.remove();
    };
  }, [enabled, intervalMs, jitterRatio, maxBackoffMs]);
}
