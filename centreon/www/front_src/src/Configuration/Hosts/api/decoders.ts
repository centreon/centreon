import { buildListingDecoder } from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';

import type { HostListItem } from '../models';

const namedEntityDecoder = {
  id: JsonDecoder.number,
  name: JsonDecoder.string
};

const iconDecoder = {
  ...namedEntityDecoder,
  url: JsonDecoder.string
};

/**
 * Decoded against the CURRENT listing endpoint.
 *
 * `/api/latest/configuration/hosts` is still served by the legacy
 * `FindHostsController` (`FindHosts/FindHostsRoute.yaml`, condition
 * `version >= 23.10`), which shadows the API Platform resource. It returns the
 * `{ result, meta }` envelope, `monitoring_server` and `is_activated`.
 *
 * The API Platform resource at `/api/configuration/hosts` returns a Hydra
 * envelope with `poller`, `activated` and — once MON-208571 lands — `icon`.
 * When the legacy route is retired, switch `apiFormat` to 'JSON-LD' and rename
 * those two fields; everything else is already aligned.
 *
 * `optional` + `nullable` on alias and icon covers both: legacy nulls today,
 * omitted keys (skip_null_values) after the switch.
 */
const hostsDecoder = JsonDecoder.object<HostListItem>(
  {
    ...namedEntityDecoder,
    address: JsonDecoder.string,
    alias: JsonDecoder.optional(JsonDecoder.nullable(JsonDecoder.string)),
    icon: JsonDecoder.optional(
      JsonDecoder.nullable(JsonDecoder.object(iconDecoder, 'Icon'))
    ),
    isActivated: JsonDecoder.boolean,
    poller: JsonDecoder.object(namedEntityDecoder, 'Monitoring server'),
    templates: JsonDecoder.array(
      JsonDecoder.object(namedEntityDecoder, 'Template'),
      'Templates'
    )
  },
  'Host',
  {
    isActivated: 'is_activated',
    poller: 'monitoring_server'
  }
);

export const hostsListDecoder = buildListingDecoder({
  entityDecoder: hostsDecoder,
  entityDecoderName: 'Host',
  listingDecoderName: 'Hosts List'
});
