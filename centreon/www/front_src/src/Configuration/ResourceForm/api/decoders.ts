import { buildListingDecoder } from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';

import type { Icon, NamedEntity } from '../models';

// The fields of every named entity the resource APIs return.
export const namedEntityDecoder = {
  id: JsonDecoder.number,
  name: JsonDecoder.string
};

// The selectors answer in Hydra, which the autocomplete cannot read unmapped.
export const namedEntitiesListDecoder = buildListingDecoder({
  apiFormat: 'JSON-LD',
  entityDecoder: JsonDecoder.object<NamedEntity>(namedEntityDecoder, 'Entity'),
  entityDecoderName: 'Entity',
  listingDecoderName: 'Entity List'
});

export const iconDecoder = JsonDecoder.object<Icon>(
  {
    ...namedEntityDecoder,
    url: JsonDecoder.string
  },
  'Icon'
);

// The media selector answers with each image's url, which the picker previews.
export const iconsListDecoder = buildListingDecoder({
  apiFormat: 'JSON-LD',
  entityDecoder: iconDecoder,
  entityDecoderName: 'Icon',
  listingDecoderName: 'Icon List'
});

// A single selector's value: the detail endpoints leave unset values out
// rather than sending null.
export const optionalNamedEntityDecoder = (name: string) =>
  JsonDecoder.optional(
    JsonDecoder.nullable(JsonDecoder.object(namedEntityDecoder, name))
  ).map((value) => value ?? null);

// A multiple selector's value, always sent.
export const namedEntitiesDecoder = (name: string, listName: string) =>
  JsonDecoder.array(JsonDecoder.object(namedEntityDecoder, name), listName);
