import { JsonDecoder } from 'ts.data.json';

import { buildListingDecoder } from '@centreon/ui';
import { HostGroupItem, HostGroupListItem } from '../models';

const namedEntityDecoder = {
  id: JsonDecoder.number,
  name: JsonDecoder.string
};

const hostGroupsDecoder = JsonDecoder.object<HostGroupListItem>(
  {
    ...namedEntityDecoder,
    alias: JsonDecoder.nullable(JsonDecoder.string),
    enabledHostsCount: JsonDecoder.number,
    disabledHostsCount: JsonDecoder.number,
    isActivated: JsonDecoder.boolean
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

// Form
export const hostGroupDecoder = JsonDecoder.object<HostGroupItem>(
  {
    ...namedEntityDecoder,
    alias: JsonDecoder.nullable(JsonDecoder.string),
    geoCoords: JsonDecoder.optional(JsonDecoder.string), // to be changed
    comment: JsonDecoder.nullable(JsonDecoder.string),
    isActivated: JsonDecoder.boolean,
    hosts: JsonDecoder.optional(
      JsonDecoder.array(JsonDecoder.object(namedEntityDecoder, 'Host'), 'Hosts')
    ), // to be changed
    resourceAccessRules: JsonDecoder.optional(
      JsonDecoder.array(
        JsonDecoder.object(namedEntityDecoder, 'Access Rule'),
        'Access Rules'
      )
    )
  },
  'Host group',
  {
    resourceAccessRules: 'resource_access_rules',
    isActivated: 'is_activated',
    geoCoords: 'geo_coords'
  }
);
