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
 * Decoded against the API Platform resource at `./api/configuration/hosts`
 * (`HostResource`, `GetCollection` → `HostCollectionOutput`): a Hydra envelope
 * whose items expose `activated`, `poller` and `templates`.
 *
 * `icon` is declared optional because `HostCollectionOutput` does not carry it
 * yet — it arrives with MON-208571. Decoding stays valid either way, so the
 * column can be added without touching this file.
 *
 * `optional` + `nullable` on `alias` covers both an explicit null and an
 * omitted key, since API Platform skips null values.
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
    poller: JsonDecoder.object(namedEntityDecoder, 'Poller'),
    templates: JsonDecoder.array(
      JsonDecoder.object(namedEntityDecoder, 'Template'),
      'Templates'
    )
  },
  'Host',
  {
    isActivated: 'activated'
  }
);

export const hostsListDecoder = buildListingDecoder({
  apiFormat: 'JSON-LD',
  entityDecoder: hostsDecoder,
  entityDecoderName: 'Host',
  listingDecoderName: 'Hosts List'
});
