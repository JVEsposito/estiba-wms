import {
  OperationalFrontierProposal,
  OperationalFrontierResult,
  OperationalSnapshot,
  OperationalTask,
  OperationalTaskAssignment,
} from '../domain/operationalTasks';
import { ApiError } from './apiError';
import { fetchWithTimeout } from './httpClient';

export const TABLET_PLANNER_VERSION = 'rolling-1';

export class OperationalTasksApi {
  constructor(private readonly baseUrl: string) {}

  async list(token: string, assignment: OperationalTaskAssignment) {
    const params = new URLSearchParams({
      asignacion: assignment,
      per_page: '50',
    });
    return (await this.request<{ data: OperationalTask[] }>(
      `/api/tareas-movimiento?${params.toString()}`,
      token,
    )).data;
  }

  async snapshot(token: string, planId: string) {
    return (await this.request<{ data: OperationalSnapshot }>(
      `/api/planes-operacionales/${encodeURIComponent(planId)}/snapshot`,
      token,
    )).data;
  }

  async take(token: string, taskId: string) {
    return (await this.request<{ data: OperationalTask }>(
      `/api/tareas-movimiento/${encodeURIComponent(taskId)}/asumir`,
      token,
      { method: 'POST' },
    )).data;
  }

  async materializeFrontier(
    token: string,
    planId: string,
    snapshotVersion: string,
    proposals: OperationalFrontierProposal[],
  ) {
    return (await this.request<{ data: OperationalFrontierResult }>(
      `/api/planes-operacionales/${encodeURIComponent(planId)}/frontera`,
      token,
      {
        method: 'POST',
        body: JSON.stringify({
          snapshot_version: snapshotVersion,
          planner_version: TABLET_PLANNER_VERSION,
          propuestas: proposals,
        }),
      },
    )).data;
  }

  async start(token: string, taskId: string) {
    return (await this.request<{ data: OperationalTask }>(
      `/api/tareas-movimiento/${encodeURIComponent(taskId)}/iniciar`,
      token,
      { method: 'POST' },
    )).data;
  }

  async completeDirectPrefrio(token: string, taskId: string) {
    return (await this.request<{ data: OperationalTask }>(
      `/api/tareas-movimiento/${encodeURIComponent(taskId)}/completar-prefrio-directo`,
      token,
      { method: 'POST' },
    )).data;
  }

  async renew(token: string, taskId: string) {
    return (await this.request<{ data: OperationalTask }>(
      `/api/tareas-movimiento/${encodeURIComponent(taskId)}/renovar`,
      token,
      { method: 'POST' },
    )).data;
  }

  async release(token: string, taskId: string) {
    return (await this.request<{ data: OperationalTask }>(
      `/api/tareas-movimiento/${encodeURIComponent(taskId)}/liberar`,
      token,
      { method: 'POST' },
    )).data;
  }

  private async request<T>(path: string, token: string, init: RequestInit = {}): Promise<T> {
    const headers = new Headers(init.headers);
    headers.set('Accept', 'application/json');
    headers.set('Authorization', `Bearer ${token}`);
    if (init.body) headers.set('Content-Type', 'application/json');

    let response: Response;
    try {
      response = await fetchWithTimeout(`${this.baseUrl}${path}`, { ...init, headers });
    } catch {
      throw new ApiError(
        `No fue posible conectar con ${this.baseUrl}. Revisa la IP, Laravel y el firewall.`,
        0,
      );
    }

    const data = response.status === 204
      ? null
      : await response.json().catch(() => ({}));

    if (!response.ok) {
      throw new ApiError(validationMessage(data), response.status, data);
    }

    return data as T;
  }
}

function validationMessage(data: unknown) {
  if (!data || typeof data !== 'object') return 'La operación no pudo completarse.';

  const response = data as { message?: string; errors?: Record<string, string[]> };
  const firstValidationMessage = response.errors
    ? Object.values(response.errors).flat()[0]
    : null;

  return firstValidationMessage ?? response.message ?? 'La operación no pudo completarse.';
}
