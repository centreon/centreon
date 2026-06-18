import type { ClapiAction } from '../helpers/CentreonApi';

/**
 * Monitoring object builders, expressed as CLAPI action lists.
 *
 * They mirror the Cypress custom commands `cy.addHostGroup`, `cy.addHost` and
 * `cy.addService` (see `packages/js-config/cypress/e2e/commands/configuration.ts`):
 * a creation action (`ADD`) followed by the `SETPARAM` calls that toggle the
 * active/passive check flags. Returning plain `ClapiAction[]` lets the API
 * client replay them with a single v1 token.
 */

export interface HostSeed {
  name: string;
  template?: string;
  checkCommand?: string;
  hostGroup?: string;
  address?: string;
  activeCheckEnabled?: boolean;
  passiveCheckEnabled?: boolean;
}

export interface ServiceSeed {
  host: string;
  name: string;
  template?: string;
  maxCheckAttempts?: number;
  activeCheckEnabled?: boolean;
  passiveCheckEnabled?: boolean;
}

const boolFlag = (value: boolean): string => (value ? '1' : '0');

export const hostGroupActions = (
  name: string,
  alias = name
): Array<ClapiAction> => [
  { action: 'ADD', object: 'HG', values: `${name};${alias}` }
];

export const hostActions = ({
  name,
  template = 'generic-host',
  checkCommand = 'check_centreon_cpu',
  hostGroup = '',
  address = '127.0.0.1',
  activeCheckEnabled = false,
  passiveCheckEnabled = true
}: HostSeed): Array<ClapiAction> => [
  {
    action: 'ADD',
    object: 'HOST',
    values: `${name};${name};${address};${template};Central;${hostGroup}`
  },
  {
    action: 'SETPARAM',
    object: 'HOST',
    values: `${name};active_checks_enabled;${boolFlag(activeCheckEnabled)}`
  },
  {
    action: 'SETPARAM',
    object: 'HOST',
    values: `${name};passive_checks_enabled;${boolFlag(passiveCheckEnabled)}`
  },
  {
    action: 'SETPARAM',
    object: 'HOST',
    values: `${name};check_command;${checkCommand}`
  }
];

export const serviceActions = ({
  host,
  name,
  template = 'Ping-LAN',
  maxCheckAttempts = 1,
  activeCheckEnabled = false,
  passiveCheckEnabled = true
}: ServiceSeed): Array<ClapiAction> => [
  { action: 'ADD', object: 'SERVICE', values: `${host};${name};${template}` },
  {
    action: 'SETPARAM',
    object: 'SERVICE',
    values: `${host};${name};active_checks_enabled;${boolFlag(activeCheckEnabled)}`
  },
  {
    action: 'SETPARAM',
    object: 'SERVICE',
    values: `${host};${name};passive_checks_enabled;${boolFlag(passiveCheckEnabled)}`
  },
  {
    action: 'SETPARAM',
    object: 'SERVICE',
    values: `${host};${name};max_check_attempts;${maxCheckAttempts}`
  }
];

/** Apply the configuration to a poller (defaults to the built-in Central). */
export const applyConfigurationActions = (
  poller = 'Central'
): Array<ClapiAction> => [{ action: 'APPLYCFG', object: '', values: poller }];

export type ResourceStatus =
  | 'ok'
  | 'warning'
  | 'critical'
  | 'unknown'
  | 'up'
  | 'down'
  | 'unreachable';

export interface SubmitResult {
  host: string;
  service?: string | null;
  status: ResourceStatus;
  output: string;
  perfdata?: string;
}

const statusCode: Record<ResourceStatus, number> = {
  critical: 2,
  down: 1,
  ok: 0,
  unknown: 3,
  unreachable: 2,
  up: 0,
  warning: 1
};

export const submitResultStatusCode = (status: ResourceStatus): number =>
  statusCode[status];
