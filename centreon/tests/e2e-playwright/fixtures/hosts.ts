import type { ClapiAction } from '../helpers/CentreonApi';

/**
 * Host configuration object builders, expressed as CLAPI action lists, used to
 * seed and tear down the prerequisites of the legacy Hosts configuration page
 * (`main.php?p=60101`).
 *
 * The shared `fixtures/monitoring.ts` already exposes a `hostActions` creator,
 * but it toggles active/passive check flags (it is meant for monitored hosts
 * pushed to the engine). The Hosts *configuration* feature only needs a plain
 * configuration host that shows up in the listing, plus a way to delete it for
 * idempotent reruns — hence these dedicated, self-contained builders.
 */

export interface ConfigHostSeed {
  name: string;
  alias?: string;
  address?: string;
  template?: string;
  poller?: string;
  hostGroup?: string;
}

/** Common prefix so every object this suite creates is easy to spot and clean. */
export const hostNamePrefix = 'pw-';

/** CLAPI `ADD HOST` action seeding a bare configuration host. */
export const configHostActions = ({
  name,
  alias = name,
  address = '127.0.0.1',
  template = 'generic-host',
  poller = 'Central',
  hostGroup = ''
}: ConfigHostSeed): Array<ClapiAction> => [
  {
    action: 'ADD',
    object: 'HOST',
    values: `${name};${alias};${address};${template};${poller};${hostGroup}`
  }
];

/** CLAPI `DEL HOST` action used for best-effort cleanup. */
export const configHostDeleteActions = (name: string): Array<ClapiAction> => [
  { action: 'DEL', object: 'HOST', values: name }
];
