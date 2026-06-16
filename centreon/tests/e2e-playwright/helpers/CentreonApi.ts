import {
  type APIRequestContext,
  type APIResponse,
  request
} from '@playwright/test';

import type { Credentials } from '../fixtures/credentials';
import type { DashboardSeed } from '../fixtures/dashboards';

export interface ClapiAction {
  action: string;
  object: string;
  values: string;
}

/**
 * Thin wrapper around the Centreon HTTP APIs used to set up E2E test state,
 * replacing the Cypress custom commands (`executeActionViaClapi`,
 * `insertDashboard`, ...). It owns a single `APIRequestContext` so that the
 * session cookie obtained at login is reused for subsequent calls.
 */
export class CentreonApi {
  private readonly context: APIRequestContext;
  private readonly base: string;

  private constructor(context: APIRequestContext, base: string) {
    this.context = context;
    this.base = base;
  }

  /** Create an API client bound to the platform base URL (e.g. .../centreon). */
  static async create(base: string): Promise<CentreonApi> {
    const context = await request.newContext({ ignoreHTTPSErrors: true });
    return new CentreonApi(context, base.replace(/\/$/, ''));
  }

  async dispose(): Promise<void> {
    await this.context.dispose();
  }

  private static ok(response: APIResponse, label: string): APIResponse {
    if (!response.ok()) {
      throw new Error(
        `${label} failed: ${response.status()} ${response.statusText()}`
      );
    }
    return response;
  }

  /** Authenticate against the legacy v1 API and return the auth token. */
  async authenticateV1(credentials: Credentials): Promise<string> {
    const response = CentreonApi.ok(
      await this.context.post(
        `${this.base}/api/index.php?action=authenticate`,
        {
          form: {
            password: credentials.password,
            username: credentials.login
          }
        }
      ),
      'API v1 authenticate'
    );
    const { authToken } = (await response.json()) as { authToken: string };
    return authToken;
  }

  /** Run a list of CLAPI actions (ACL/contact provisioning) with a v1 token. */
  async runClapiActions(
    authToken: string,
    actions: Array<ClapiAction>
  ): Promise<void> {
    for (const action of actions) {
      CentreonApi.ok(
        await this.context.post(
          `${this.base}/api/index.php?action=action&object=centreon_clapi`,
          {
            data: action,
            headers: { 'centreon-auth-token': authToken }
          }
        ),
        `CLAPI ${action.action} ${action.object}`
      );
    }
  }

  /** Open a v2 session (local provider); the cookie is kept by the context. */
  async login(credentials: Credentials): Promise<void> {
    CentreonApi.ok(
      await this.context.post(
        `${this.base}/authentication/providers/configurations/local`,
        { data: { login: credentials.login, password: credentials.password } }
      ),
      'API v2 local login'
    );
  }

  async createDashboard(dashboard: DashboardSeed): Promise<number> {
    const response = CentreonApi.ok(
      await this.context.post(
        `${this.base}/api/latest/configuration/dashboards`,
        { data: dashboard }
      ),
      `create dashboard "${dashboard.name}"`
    );
    const { id } = (await response.json()) as { id: number };
    return id;
  }

  async createDashboards(dashboards: Array<DashboardSeed>): Promise<void> {
    for (const dashboard of dashboards) {
      await this.createDashboard(dashboard);
    }
  }

  /** Remove every dashboard the current session can see (test cleanup). */
  async deleteAllDashboards(): Promise<void> {
    const response = CentreonApi.ok(
      await this.context.get(
        `${this.base}/api/latest/configuration/dashboards?limit=100`
      ),
      'list dashboards'
    );
    const { result } = (await response.json()) as {
      result: Array<{ id: number }>;
    };
    for (const { id } of result) {
      CentreonApi.ok(
        await this.context.delete(
          `${this.base}/api/latest/configuration/dashboards/${id}`
        ),
        `delete dashboard ${id}`
      );
    }
  }
}
