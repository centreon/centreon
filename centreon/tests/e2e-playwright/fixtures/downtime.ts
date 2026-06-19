import type { ClapiAction } from '../helpers/CentreonApi';
import {
  applyConfigurationActions,
  hostActions,
  type SubmitResult,
  serviceActions
} from './monitoring';

/**
 * Monitoring fixture for the Downtime spec, mirroring the Cypress
 * `Resources-status/03-downtime` setup: one passive host carrying two passive
 * services that get pushed to a known state so they show up in the listing and
 * can be put in downtime.
 *
 * Names are `pw-` prefixed and self-contained so the spec can provision and tear
 * down its own data without colliding with the resources-status listing spec.
 */

export const downtimeHostName = 'pw-downtime-host';

export const downtimeServiceNames = [
  'pw-downtime-service-1',
  'pw-downtime-service-2'
];

/** CLAPI actions that create the host, its services and apply the config. */
export const downtimeProvisioningActions: Array<ClapiAction> = [
  ...hostActions({ name: downtimeHostName, template: 'generic-host' }),
  ...downtimeServiceNames.flatMap((name) =>
    serviceActions({ host: downtimeHostName, name, template: 'Ping-LAN' })
  ),
  ...applyConfigurationActions()
];

/** Remove the host (and its services) and re-apply the config — idempotent teardown. */
export const downtimeTearDownActions: Array<ClapiAction> = [
  { action: 'DEL', object: 'HOST', values: downtimeHostName },
  ...applyConfigurationActions()
];

/** Passive check results pushing both services to a stable OK state. */
export const downtimeSubmitResults: Array<SubmitResult> =
  downtimeServiceNames.map((service) => ({
    host: downtimeHostName,
    output: 'OK - submitted by Playwright',
    service,
    status: 'ok' as const
  }));
