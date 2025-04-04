import { JsonDecoder } from 'ts.data.json';

import { buildListingDecoder } from '@centreon/ui';
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
    enabledHostsCount: JsonDecoder.number,
    disabledHostsCount: JsonDecoder.number,
    isActivated: JsonDecoder.boolean,
    icon: JsonDecoder.nullable(JsonDecoder.object(iconDecoder, 'Icon'))
  },
  'Host group',
  {
    enabledHostsCount: 'enabled_hosts_count',
    disabledHostsCount: 'disabled_hosts_count',
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
    geoCoords: JsonDecoder.nullable(JsonDecoder.string),
    comment: JsonDecoder.nullable(JsonDecoder.string),
    isActivated: JsonDecoder.boolean,
    hosts: JsonDecoder.array(
      JsonDecoder.object(namedEntityDecoder, 'Host'),
      'Hosts'
    ),
    resourceAccessRules: JsonDecoder.optional(
      JsonDecoder.array(
        JsonDecoder.object(namedEntityDecoder, 'Access Rule'),
        'Access Rules'
      )
    ),
    icon: JsonDecoder.optional(
      JsonDecoder.nullable(JsonDecoder.object(iconDecoder, 'Icon'))
    )
  },
  'Host group',
  {
    resourceAccessRules: 'resource_access_rules',
    isActivated: 'is_activated',
    geoCoords: 'geo_coords'
  }
);
