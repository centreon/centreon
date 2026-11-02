import { buildListingDecoder } from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';

import type {
  Icon,
  NamedEntity,
  ServiceHost,
  ServiceListItem
} from '../models';

const namedEntityDecoder = {
  id: JsonDecoder.number,
  name: JsonDecoder.string
};

const iconDecoder = JsonDecoder.object<Icon>(
  { ...namedEntityDecoder, url: JsonDecoder.string },
  'Icon'
);

const optionalIconDecoder = JsonDecoder.optional(
  JsonDecoder.nullable(iconDecoder)
);

const optionalIntervalDecoder = JsonDecoder.optional(
  JsonDecoder.nullable(JsonDecoder.number)
);

// Unset fields are left out of the response, not sent as null.
const serviceDecoder = JsonDecoder.object<ServiceListItem>(
  {
    ...namedEntityDecoder,
    host: JsonDecoder.object<ServiceHost>(
      { ...namedEntityDecoder, icon: optionalIconDecoder },
      'Host'
    ),
    icon: optionalIconDecoder,
    isActivated: JsonDecoder.boolean,
    normalCheckInterval: optionalIntervalDecoder,
    retryCheckInterval: optionalIntervalDecoder,
    template: JsonDecoder.optional(
      JsonDecoder.nullable(
        JsonDecoder.object<NamedEntity>(namedEntityDecoder, 'Template')
      )
    )
  },
  'Service',
  {
    isActivated: 'activated',
    normalCheckInterval: 'normal_check_interval',
    retryCheckInterval: 'retry_check_interval'
  }
);

export const servicesListDecoder = buildListingDecoder({
  apiFormat: 'JSON-LD',
  entityDecoder: serviceDecoder,
  entityDecoderName: 'Service',
  listingDecoderName: 'Services List'
});

// The selectors answer in Hydra, which the autocomplete cannot read unmapped.
export const namedEntitiesListDecoder = buildListingDecoder({
  apiFormat: 'JSON-LD',
  entityDecoder: JsonDecoder.object<NamedEntity>(namedEntityDecoder, 'Entity'),
  entityDecoderName: 'Entity',
  listingDecoderName: 'Entity List'
});
