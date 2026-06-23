import {
  type APIRequestContext,
  type APIResponse,
  request
} from '@playwright/test';

import type { Credentials } from '../fixtures/credentials';
import type { DashboardSeed } from '../fixtures/dashboards';
import {
  type SubmitResult,
  submitResultStatusCode
} from '../fixtures/monitoring';
import type { NotificationBody } from '../fixtures/notifications';

export interface ClapiAction {
  action: string;
  object: string;
  values: string;
}

export interface MonitoredService {
  id: number;
  name: string;
  status: string;
  acknowledged: boolean;
  parentId: number | null;
}

const sleep = (ms: number): Promise<void> =>
  new Promise((resolve) => setTimeout(resolve, ms));

/**
 * Thin wrapper around the Centreon HTTP APIs used to set up E2E test state,
 * replacing the Cypress custom commands (`executeActionViaClapi`,
 * `insertDashboard`, ...). It owns a single `APIRequestContext` so that the
 * session cookie obtained at login is reused for subsequent calls.
 */
export class CentreonApi {
  private readonly context: APIRequestContext;
  private readonly base: string;
  private v1Token?: string;

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

  /**
   * Run a list of CLAPI actions (ACL/contact provisioning) with a v1 token.
   *
   * `tolerate` lists HTTP status codes that must NOT fail the call — typically
   * `409` for an ADD that already exists, or `404` for a DEL of something
   * already gone. Any other non-2xx status still throws, so a real failure
   * (bad payload, 500, auth) surfaces here instead of cascading into an obscure
   * downstream error. Tolerated statuses are logged, not silenced.
   */
  async runClapiActions(
    authToken: string,
    actions: Array<ClapiAction>,
    { tolerate = [] }: { tolerate?: Array<number> } = {}
  ): Promise<void> {
    for (const action of actions) {
      const response = await this.context.post(
        `${this.base}/api/index.php?action=action&object=centreon_clapi`,
        {
          data: action,
          headers: { 'centreon-auth-token': authToken }
        }
      );
      if (!response.ok()) {
        if (tolerate.includes(response.status())) {
          // eslint-disable-next-line no-console
          console.warn(
            `[provision] CLAPI ${action.action} ${action.object} -> ${response.status()} ${response.statusText()} (tolerated)`
          );
          continue;
        }
        throw new Error(
          `CLAPI ${action.action} ${action.object} failed: ${response.status()} ${response.statusText()}`
        );
      }
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

  // --- Monitoring provisioning ---------------------------------------------

  /**
   * Open a full session for the given user: a v2 session (kept as a cookie for
   * the configuration/monitoring v2 APIs) and a v1 token (stored for the legacy
   * CLAPI/submit endpoints). Used by the `adminApi` fixture.
   */
  async authenticate(credentials: Credentials): Promise<void> {
    await this.login(credentials);
    this.v1Token = await this.authenticateV1(credentials);
  }

  private requireV1Token(): string {
    if (!this.v1Token) {
      throw new Error(
        'No v1 token: call authenticate() before CLAPI/submit operations'
      );
    }
    return this.v1Token;
  }

  /**
   * Replay a list of CLAPI actions with the stored v1 token. Pass
   * `{ tolerate: [409] }` for idempotent ADD provisioning (or `[404, 409]` for a
   * best-effort teardown) so only "already exists"/"already gone" are ignored.
   */
  async provision(
    actions: Array<ClapiAction>,
    options: { tolerate?: Array<number> } = {}
  ): Promise<void> {
    await this.runClapiActions(this.requireV1Token(), actions, options);
  }

  /**
   * Push passive check results, mirroring `cy.submitResults`. The output is
   * dated 15 seconds in the past so the engine accepts it as a fresh result.
   */
  async submitResults(results: Array<SubmitResult>): Promise<void> {
    const updatetime = (Math.floor(Date.now() / 1000) - 15).toString();
    for (const {
      host,
      service = null,
      status,
      output,
      perfdata = ''
    } of results) {
      CentreonApi.ok(
        await this.context.post(
          `${this.base}/api/index.php?action=submit&object=centreon_submit_results`,
          {
            data: {
              results: [
                {
                  host,
                  output,
                  perfdata,
                  service,
                  status: submitResultStatusCode(status),
                  updatetime
                }
              ]
            },
            headers: { 'centreon-auth-token': this.requireV1Token() }
          }
        ),
        `submit result for ${host}/${service ?? ''}`
      );
    }
  }

  /** Service resources currently known to the monitoring engine. */
  private async monitoredServices(): Promise<Array<MonitoredService>> {
    const types = encodeURIComponent('["service"]');
    const response = CentreonApi.ok(
      await this.context.get(
        `${this.base}/api/latest/monitoring/resources?types=${types}&limit=100`
      ),
      'list monitored resources'
    );
    const { result } = (await response.json()) as {
      result: Array<{
        id: number;
        name: string;
        is_acknowledged?: boolean;
        status?: { name?: string } | null;
        parent?: { id: number } | null;
      }>;
    };
    return result.map(({ id, name, is_acknowledged, status, parent }) => ({
      acknowledged: is_acknowledged ?? false,
      id,
      name,
      parentId: parent?.id ?? null,
      status: status?.name ?? 'PENDING'
    }));
  }

  /** Whether every requested service is already known to the engine. */
  async areServicesMonitored(services: Array<string>): Promise<boolean> {
    const monitored = (await this.monitoredServices()).map(({ name }) => name);
    return services.every((name) => monitored.includes(name));
  }

  /**
   * Guarantee a service is in a CRITICAL HARD state before acting on it.
   *
   * Passive results can be dropped by the engine, so the result is re-submitted
   * on each poll until the status sticks. Active checks are disabled on these
   * services, so once CRITICAL the state is stable.
   */
  async ensureServiceCritical(
    host: string,
    service: string,
    { timeoutMs = 60_000, intervalMs = 3_000 } = {}
  ): Promise<void> {
    const deadline = Date.now() + timeoutMs;
    let status = 'PENDING';
    while (Date.now() < deadline) {
      await this.submitResults([
        {
          host,
          output: 'CRITICAL set by Playwright',
          service,
          status: 'critical'
        }
      ]);
      await sleep(intervalMs);
      status =
        (await this.monitoredServices()).find((entry) => entry.name === service)
          ?.status ?? 'PENDING';
      if (status === 'CRITICAL') {
        return;
      }
    }
    throw new Error(
      `service "${service}" not CRITICAL after ${timeoutMs}ms (last: ${status})`
    );
  }

  /** Resolve a monitored service to the ids needed by the acknowledge API. */
  async getServiceResource(name: string): Promise<MonitoredService> {
    const service = (await this.monitoredServices()).find(
      (entry) => entry.name === name
    );
    if (!service || service.parentId === null) {
      throw new Error(`monitored service "${name}" not found`);
    }
    return service;
  }

  /** Acknowledge a service resource (sticky, persistent, no notification). */
  async acknowledgeService(
    name: string,
    comment = 'ack by Playwright'
  ): Promise<void> {
    const { id, parentId } = await this.getServiceResource(name);
    CentreonApi.ok(
      await this.context.post(
        `${this.base}/api/latest/monitoring/resources/acknowledge`,
        {
          data: {
            acknowledgement: {
              comment,
              is_notify_contacts: false,
              is_persistent_comment: true,
              is_sticky: true,
              with_services: false
            },
            resources: [{ id, parent: { id: parentId }, type: 'service' }]
          }
        }
      ),
      `acknowledge service "${name}"`
    );
  }

  /** Remove the acknowledgement from a service resource. */
  async disacknowledgeService(name: string): Promise<void> {
    const { id, parentId } = await this.getServiceResource(name);
    CentreonApi.ok(
      await this.context.delete(
        `${this.base}/api/latest/monitoring/resources/acknowledgements`,
        {
          data: {
            disacknowledgement: { with_services: false },
            resources: [{ id, parent: { id: parentId }, type: 'service' }]
          }
        }
      ),
      `disacknowledge service "${name}"`
    );
  }

  /** Poll until a service's acknowledged flag matches the expected value. */
  async waitForServiceAcknowledged(
    name: string,
    expected = true,
    { timeoutMs = 30_000, intervalMs = 2_000 } = {}
  ): Promise<void> {
    const deadline = Date.now() + timeoutMs;
    while (Date.now() < deadline) {
      const service = (await this.monitoredServices()).find(
        (entry) => entry.name === name
      );
      if (service && service.acknowledged === expected) {
        return;
      }
      await sleep(intervalMs);
    }
    throw new Error(
      `service "${name}" acknowledged !== ${expected} after ${timeoutMs}ms`
    );
  }

  /**
   * Poll the monitoring resources endpoint until every requested service is
   * known to the engine, replacing the Cypress DB-polling `checkServicesAreMonitored`.
   */
  async waitForServicesMonitored(
    services: Array<string>,
    { timeoutMs = 120_000, intervalMs = 5_000 } = {}
  ): Promise<void> {
    const deadline = Date.now() + timeoutMs;
    let monitored: Array<string> = [];
    while (Date.now() < deadline) {
      monitored = (await this.monitoredServices()).map(({ name }) => name);
      if (services.every((name) => monitored.includes(name))) {
        return;
      }
      await sleep(intervalMs);
    }
    const missing = services.filter((name) => !monitored.includes(name));
    throw new Error(
      `services not monitored after ${timeoutMs}ms: ${missing.join(', ')}`
    );
  }

  // --- Cloud notifications --------------------------------------------------

  /** Resolve a host group id by name (needed to bind a notification rule). */
  async findHostGroupId(name: string): Promise<number> {
    const search = encodeURIComponent(JSON.stringify({ name }));
    const response = CentreonApi.ok(
      await this.context.get(
        `${this.base}/api/latest/configuration/hosts/groups?search=${search}`
      ),
      `find host group "${name}"`
    );
    const { result } = (await response.json()) as {
      result: Array<{ id: number; name: string }>;
    };
    const group = result.find((entry) => entry.name === name) ?? result[0];
    if (!group) {
      throw new Error(`host group "${name}" not found`);
    }
    return group.id;
  }

  async createNotification(body: NotificationBody): Promise<void> {
    CentreonApi.ok(
      await this.context.post(
        `${this.base}/api/latest/configuration/notifications`,
        { data: body }
      ),
      `create notification "${body.name}"`
    );
  }

  /** Remove every notification rule (test cleanup), via the listing + delete API. */
  async deleteAllNotifications(): Promise<void> {
    const response = CentreonApi.ok(
      await this.context.get(
        `${this.base}/api/latest/configuration/notifications?limit=100`
      ),
      'list notifications'
    );
    const { result } = (await response.json()) as {
      result: Array<{ id: number }>;
    };
    for (const { id } of result) {
      CentreonApi.ok(
        await this.context.delete(
          `${this.base}/api/latest/configuration/notifications/${id}`
        ),
        `delete notification ${id}`
      );
    }
  }

  // --- Authentication (API) tokens -----------------------------------------

  /** Resolve a configuration user id by its alias. */
  async findUserId(alias: string): Promise<number> {
    const search = encodeURIComponent(JSON.stringify({ alias }));
    const response = CentreonApi.ok(
      await this.context.get(
        `${this.base}/api/latest/configuration/users?search=${search}`
      ),
      `find user "${alias}"`
    );
    const { result } = (await response.json()) as {
      result: Array<{ id: number; alias: string }>;
    };
    const user = result.find((entry) => entry.alias === alias) ?? result[0];
    if (!user) {
      throw new Error(`user "${alias}" not found`);
    }
    return user.id;
  }

  /** Create an API token bound to a user, expiring in `durationDays`. */
  async createToken({
    name,
    userId,
    durationDays = 30,
    type = 'api'
  }: {
    name: string;
    userId: number;
    durationDays?: number;
    type?: string;
  }): Promise<void> {
    // The API rejects the milliseconds that `toISOString()` adds (422).
    const expiration = new Date(Date.now() + durationDays * 24 * 60 * 60 * 1000)
      .toISOString()
      .replace(/\.\d{3}Z$/, 'Z');
    CentreonApi.ok(
      await this.context.post(`${this.base}/api/latest/administration/tokens`, {
        data: {
          expiration_date: expiration,
          name,
          type,
          user_id: userId
        }
      }),
      `create token "${name}"`
    );
  }

  /** Remove every API-type token (leaves the platform's poller/cma tokens). */
  async deleteAllApiTokens(): Promise<void> {
    const response = CentreonApi.ok(
      await this.context.get(
        `${this.base}/api/latest/administration/tokens?limit=100`
      ),
      'list tokens'
    );
    const { result } = (await response.json()) as {
      result: Array<{
        name: string;
        type: string;
        user?: { id: number } | null;
        creator: { id: number };
      }>;
    };
    for (const token of result) {
      if (token.type !== 'api') {
        continue;
      }
      const userId = token.user?.id ?? token.creator.id;
      CentreonApi.ok(
        await this.context.delete(
          `${this.base}/api/latest/administration/tokens/${encodeURIComponent(
            token.name
          )}/users/${userId}`
        ),
        `delete token "${token.name}"`
      );
    }
  }
}
