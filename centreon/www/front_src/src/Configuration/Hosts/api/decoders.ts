import { buildListingDecoder } from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';

import type { HostDetail, HostListItem, Icon, NamedEntity } from '../models';

const namedEntityDecoder = {
  id: JsonDecoder.number,
  name: JsonDecoder.string
};

const iconDecoder = JsonDecoder.object<Icon>(
  {
    ...namedEntityDecoder,
    url: JsonDecoder.string
  },
  'Icon'
);

// The detail endpoint answers with objects where the create takes ids, so the
// poller arrives named and the autocomplete can render it without a lookup.
export const hostDecoder = JsonDecoder.object<HostDetail>(
  {
    address: JsonDecoder.string,
    categories: JsonDecoder.array(
      JsonDecoder.object(namedEntityDecoder, 'Category'),
      'Categories'
    ),
    childHosts: JsonDecoder.array(
      JsonDecoder.object(namedEntityDecoder, 'Child host'),
      'Child hosts'
    ),
    groups: JsonDecoder.array(
      JsonDecoder.object(namedEntityDecoder, 'Group'),
      'Groups'
    ),
    name: JsonDecoder.string,
    parentHosts: JsonDecoder.array(
      JsonDecoder.object(namedEntityDecoder, 'Parent host'),
      'Parent hosts'
    ),
    poller: JsonDecoder.object(namedEntityDecoder, 'Poller')
  },
  'Host',
  {
    childHosts: 'child_hosts',
    parentHosts: 'parent_hosts'
  }
);

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

// The selectors answer in Hydra, which the autocomplete cannot read unmapped.
export const namedEntitiesListDecoder = buildListingDecoder({
  apiFormat: 'JSON-LD',
  entityDecoder: JsonDecoder.object<NamedEntity>(namedEntityDecoder, 'Entity'),
  entityDecoderName: 'Entity',
  listingDecoderName: 'Entity List'
});
