import { JsonDecoder } from 'ts.data.json';

import { buildListingDecoder } from '@centreon/ui';

import { ResourceAccessRuleType } from '../../models';

const resourceAccessesDecoder = JsonDecoder.object<ResourceAccessRuleType>(
  {
    description: JsonDecoder.string,
    id: JsonDecoder.number,
    isEnabled: JsonDecoder.boolean,
    name: JsonDecoder.string
  },
  'Resource Accesses Rules',
  {
    description: 'description',
    id: 'id',
    isEnabled: 'is_enabled',
    name: 'name'
  }
);

export const listingDecoder = buildListingDecoder<ResourceAccessRuleType>({
  entityDecoder: resourceAccessesDecoder,
  entityDecoderName: 'Resource Access',
  listingDecoderName: 'ResourceAccessesListing'
});
