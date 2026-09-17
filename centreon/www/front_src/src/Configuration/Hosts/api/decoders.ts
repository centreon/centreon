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
 * The listing endpoint is API Platform with `skip_null_values`: a field with no
 * value is absent from the payload rather than null, hence `optional` on alias
 * and icon.
 *
 * `activated` is mapped to `isActivated` because the shared status column reads
 * `row.isActivated`.
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
