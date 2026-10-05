import { buildListingDecoder } from '@centreon/ui';

import { mergeAll } from 'ramda';
import { JsonDecoder } from 'ts.data.json';

import {
  getAvailableSections,
  type HostDetail,
  type PlatformContext
} from '../Form/sections';
import type { HostListItem, Icon } from '../models';
import { namedEntityDecoder } from './namedEntityDecoders';

const iconDecoder = JsonDecoder.object<Icon>(
  {
    ...namedEntityDecoder,
    url: JsonDecoder.string
  },
  'Icon'
);

// The detail endpoint answers with objects where the create takes ids, so the
// poller arrives named and the autocomplete can render it without a lookup.
export const getHostDecoder = (context: PlatformContext) => {
  const availableSections = getAvailableSections(context).map(
    ({ section }) => section
  );

  return JsonDecoder.object<HostDetail>(
    mergeAll(
      availableSections.map((section) => section.detailDecoders)
    ) as JsonDecoder.DecoderObject<HostDetail>,
    'Host',
    mergeAll(availableSections.map((section) => section.detailKeyMap ?? {}))
  );
};

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
