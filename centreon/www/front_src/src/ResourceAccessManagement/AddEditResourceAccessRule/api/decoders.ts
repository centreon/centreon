import { JsonDecoder } from 'ts.data.json';

import { buildListingDecoder } from '@centreon/ui';

import { Contact, Resource } from '../../models';

const contactGroupDecoder = JsonDecoder.object<Contact>(
  {
    id: JsonDecoder.number,
    name: JsonDecoder.string
  },
  'Contact group'
);

const contactDecoder = JsonDecoder.object<Contact>(
  {
    id: JsonDecoder.number,
    name: JsonDecoder.string
  },
  'Contact'
);

const resourceDecoder = JsonDecoder.object<Resource>(
  {
    type: JsonDecoder.string,
    resources: JsonDecoder.array()
  }
)
