import type { ClapiAction } from '../helpers/CentreonApi';
import {
  applyConfigurationActions,
  hostActions,
  type SubmitResult,
  serviceActions
} from './monitoring';

/**
 * Monitoring fixture for the Resources-status listing spec, mirroring the
 * Cypress `Resources-status/01-listing` setup: one passive host carrying four
 * passive services, three of them pushed to CRITICAL and one kept OK so the
 * default "Unhandled alerts" filter has something to hide.
 */

export const resourcesHostName = 'host1';

export const criticalServiceNames = [
  'service_critical_1',
  'service_critical_2',
  'service_critical_3'
];

export const okServiceName = 'service_test_ok';

const serviceNames = [...criticalServiceNames, okServiceName];

/** CLAPI actions that create the host, its services and apply the config. */
export const resourcesProvisioningActions: Array<ClapiAction> = [
  ...hostActions({ name: resourcesHostName, template: 'generic-host' }),
  ...serviceNames.flatMap((name) =>
    serviceActions({ host: resourcesHostName, name, template: 'Ping-LAN' })
  ),
  ...applyConfigurationActions()
];

/** Remove the host (and its services) and re-apply the config — idempotent teardown. */
export const resourcesTearDownActions: Array<ClapiAction> = [
  { action: 'DEL', object: 'HOST', values: resourcesHostName },
  ...applyConfigurationActions()
];

/** Passive check results setting three services CRITICAL and one OK. */
export const resourcesSubmitResults: Array<SubmitResult> = [
  ...criticalServiceNames.map((service) => ({
    host: resourcesHostName,
    output: 'CRITICAL - submitted by Playwright',
    service,
    status: 'critical' as const
  })),
  {
    host: resourcesHostName,
    output: 'OK - submitted by Playwright',
    service: okServiceName,
    status: 'ok' as const
  }
];
