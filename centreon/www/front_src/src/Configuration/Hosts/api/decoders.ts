import { buildListingDecoder } from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';

import {
  iconDecoder,
  namedEntityDecoder
} from '../../ResourceForm/api/decoders';
import { getDetailDecoder } from '../../ResourceForm/blocks';
import {
  blocks,
  type HostDetail,
  type PlatformContext
} from '../Form/sections';
import type { FormPoller, HostListItem } from '../models';

// The detail endpoint answers with objects where the create takes ids, so the
// poller arrives named and the autocomplete can render it without a lookup.
export const getHostDecoder = (context: PlatformContext) =>
  getDetailDecoder<HostDetail>({ blocks, context, name: 'Host' });

const hostsDecoder = JsonDecoder.object<HostListItem>(
  {
    ...namedEntityDecoder,
    address: JsonDecoder.string,
    alias: JsonDecoder.optional(JsonDecoder.nullable(JsonDecoder.string)),
    icon: JsonDecoder.optional(JsonDecoder.nullable(iconDecoder)),
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

export const formPollersListDecoder = buildListingDecoder({
  apiFormat: 'JSON-LD',
  entityDecoder: JsonDecoder.object<FormPoller>(
    { ...namedEntityDecoder, isDefault: JsonDecoder.boolean },
    'Poller',
    { isDefault: 'is_default' }
  ),
  entityDecoderName: 'Poller',
  listingDecoderName: 'Pollers List'
});
