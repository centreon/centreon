import { JsonDecoder } from 'ts.data.json';

import { buildListingDecoder } from '@centreon/ui';
import { HostGroupListItem } from '../models';

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

export const hostGroupsDecoderListDecoder = buildListingDecoder({
  entityDecoder: hostGroupsDecoder,
  entityDecoderName: 'Host group',
  listingDecoderName: 'Host group List'
});
