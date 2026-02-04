import { buildListingDecoder } from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';

import { HostGroupItem, HostGroupListItem } from '../models';

const namedEntityDecoder = {
  id: JsonDecoder.number,
  name: JsonDecoder.string
};

const iconDecoder = {
  ...namedEntityDecoder,
  url: JsonDecoder.string
};

const hostGroupsDecoder = JsonDecoder.object<HostGroupListItem>(
  {
    ...namedEntityDecoder,
    alias: JsonDecoder.nullable(JsonDecoder.string),
    disabledHostsCount: JsonDecoder.number,
    enabledHostsCount: JsonDecoder.number,
    icon: JsonDecoder.nullable(JsonDecoder.object(iconDecoder, 'Icon')),
    isActivated: JsonDecoder.boolean
  },
  'Host group',
  {
    disabledHostsCount: 'disabled_hosts_count',
    enabledHostsCount: 'enabled_hosts_count',
    isActivated: 'is_activated'
  }
);

export const hostGroupsListDecoder = buildListingDecoder({
  entityDecoder: hostGroupsDecoder,
  entityDecoderName: 'Host group',
  listingDecoderName: 'Host group List'
});

export const hostGroupDecoder = JsonDecoder.object<HostGroupItem>(
  {
    ...namedEntityDecoder,
    alias: JsonDecoder.nullable(JsonDecoder.string),
    comment: JsonDecoder.nullable(JsonDecoder.string),
    geoCoords: JsonDecoder.nullable(JsonDecoder.string),
    hosts: JsonDecoder.array(
      JsonDecoder.object(namedEntityDecoder, 'Host'),
      'Hosts'
    ),
    icon: JsonDecoder.optional(
      JsonDecoder.nullable(JsonDecoder.object(iconDecoder, 'Icon'))
    ),
    isActivated: JsonDecoder.boolean,
    resourceAccessRules: JsonDecoder.optional(
      JsonDecoder.array(
        JsonDecoder.object(namedEntityDecoder, 'Access Rule'),
        'Access Rules'
      )
    )
  },
  'Host group',
  {
    geoCoords: 'geo_coords',
    isActivated: 'is_activated',
    resourceAccessRules: 'resource_access_rules'
  }
);
