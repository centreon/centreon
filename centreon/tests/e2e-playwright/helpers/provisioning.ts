import { adminUser } from '../fixtures/credentials';
import {
  criticalServiceNames,
  okServiceName,
  resourcesProvisioningActions,
  resourcesSubmitResults,
  resourcesTearDownActions
} from '../fixtures/resources';
import { CentreonApi } from './CentreonApi';

/**
 * Ensure the Resources-status monitoring fixture (one passive host with three
 * CRITICAL services and one OK service) is loaded by the engine.
 *
 * Shared by the resources-status listing and acknowledgement specs so they seed
 * the monitoring data **once**: provisioning is skipped when the services are
 * already monitored, which makes the second spec start instantly instead of
 * paying the config-apply + engine-reload cost again.
 */
export const ensureResourcesMonitored = async (
  baseURL: string
): Promise<void> => {
  const api = await CentreonApi.create(baseURL);
  try {
    await api.authenticate(adminUser);
    const services = [...criticalServiceNames, okServiceName];

    if (!(await api.areServicesMonitored(services))) {
      // Recreate from scratch (tolerating leftovers) and wait for the engine to
      // load the services before pushing results — passive results sent before
      // the service exists are dropped by the engine.
      try {
        // Best-effort cleanup: tolerate "already gone" (404/409); log anything
        // else rather than abort the whole setup on a stale teardown.
        await api.provision(resourcesTearDownActions, { tolerate: [404, 409] });
      } catch (error) {
        // eslint-disable-next-line no-console
        console.warn(
          `[provision] resources teardown failed: ${(error as Error).message}`
        );
      }
      // Tolerate a partially-present fixture (409) but surface real ADD failures.
      await api.provision(resourcesProvisioningActions, { tolerate: [409] });
      await api.waitForServicesMonitored(services, { timeoutMs: 200_000 });
    }

    // Refresh the statuses (idempotent) now the services are loaded.
    await api.submitResults(resourcesSubmitResults);
  } finally {
    await api.dispose();
  }
};
