import type { FullConfig } from '@playwright/test';

import { adminUser } from './fixtures/credentials';
import { dashboardCreatorAclActions } from './fixtures/dashboards';
import { CentreonApi } from './helpers/CentreonApi';
import {
  applyAcl,
  enableDashboardFeature,
  enableNotificationFeature,
  ensureStack
} from './helpers/docker';

/**
 * One-time setup shared by the dashboard specs, replacing the per-spec Cypress
 * `before()` hooks (enable feature flag → provision ACL user → recompute ACLs).
 *
 * Set SKIP_GLOBAL_SETUP=1 to bypass it when the platform is already provisioned
 * (e.g. iterating on a single spec against a warm stack).
 */
async function globalSetup(config: FullConfig): Promise<void> {
  if (process.env.SKIP_GLOBAL_SETUP) {
    return;
  }

  const base =
    process.env.CENTREON_BASE_URL ??
    config.projects[0]?.use?.baseURL ??
    'http://localhost:4000/centreon';

  // 1. Make sure the web stack is up (start it if needed) before provisioning.
  await ensureStack({ services: ['web'] });

  // 2. Enable the feature flags the specs rely on in the running platform.
  enableDashboardFeature();
  enableNotificationFeature();

  // 3. Provision the dashboard-creator contact + ACL group via CLAPI.
  const api = await CentreonApi.create(base);
  try {
    const authToken = await api.authenticateV1(adminUser);
    // Tolerate reruns against an already-provisioned stack (the ADD actions
    // return 409 when the contact/ACL objects already exist); any other failure
    // (bad payload, 500, auth) still aborts setup loudly instead of letting the
    // dashboard specs fail later with an obscure error.
    await api.runClapiActions(authToken, dashboardCreatorAclActions, {
      tolerate: [409]
    });
  } finally {
    await api.dispose();
  }

  // 4. Recompute ACLs so the new group takes effect.
  applyAcl();
}

export default globalSetup;
