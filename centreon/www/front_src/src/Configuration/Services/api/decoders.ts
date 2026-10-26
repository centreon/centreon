import { buildListingDecoder } from '@centreon/ui';

import { mergeAll } from 'ramda';
import { JsonDecoder } from 'ts.data.json';

import {
  getAvailableSections,
  type PlatformContext,
  type ServiceDetail
} from '../Form/sections';
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

// What the form opens on: each available section decodes its own fields, so a
// section left out on this platform is not expected in the response.
export const getServiceDecoder = (context: PlatformContext) => {
  const availableSections = getAvailableSections(context).map(
    ({ section }) => section
  );

  return JsonDecoder.object<ServiceDetail>(
    mergeAll(
      availableSections.map((section) => section.detailDecoders)
    ) as JsonDecoder.DecoderObject<ServiceDetail>,
    'Service',
    mergeAll(availableSections.map((section) => section.detailKeyMap ?? {}))
  );
};
