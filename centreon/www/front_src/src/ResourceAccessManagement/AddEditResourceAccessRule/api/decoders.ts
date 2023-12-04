import { JsonDecoder } from 'ts.data.json';

import { buildListingDecoder } from '@centreon/ui';

import { ContactGroup } from '../../../models';

const contactGroupDecoder = JsonDecoder.object<ContactGroup>(
  {
    id: JsonDecoder.number,
    name: JsonDecoder.string
  },
  'Contact group'
);

export const contactGroupsDecoder = buildListingDecoder({
  entityDecoder: contactGroupDecoder,
  entityDecoderName: 'Listing Contact Groups',
  listingDecoderName: 'Contact groups'
});
