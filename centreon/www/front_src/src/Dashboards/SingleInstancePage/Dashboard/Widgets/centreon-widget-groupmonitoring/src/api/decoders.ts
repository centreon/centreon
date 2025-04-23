import { JsonDecoder } from 'ts.data.json';

import { buildListingDecoder } from '@centreon/ui';

import { Group, Host, Service } from '../models';

const serviceDecoder = JsonDecoder.object<Service>(
  {
    description: JsonDecoder.string,
    displayName: JsonDecoder.string,
    id: JsonDecoder.number,
    status: JsonDecoder.number
  },
  'Service',
  {
    displayName: 'display_name',
    status: 'state'
  }
);

const hostDecoder = JsonDecoder.object<Host>(
  {
    alias: JsonDecoder.string,
    displayName: JsonDecoder.string,
    id: JsonDecoder.number,
    name: JsonDecoder.string,
    services: JsonDecoder.array(serviceDecoder, 'Services'),
    status: JsonDecoder.number
  },
  'Host',
  {
    displayName: 'display_name',
    status: 'state'
  }
);

const groupDecoder = JsonDecoder.object<Group>(
  {
    hosts: JsonDecoder.array(hostDecoder, 'Hosts'),
    id: JsonDecoder.number,
    name: JsonDecoder.string
  },
  'Group'
);

export const groupsDecoder = buildListingDecoder({
  entityDecoder: groupDecoder,
  entityDecoderName: 'Groups',
  listingDecoderName: 'Listing groups'
});
