import {
  buildListingDecoder,
  type InputProps,
  type SelectEntry
} from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';
import { number, object, string } from 'yup';

import { servicesBaseEndpoint } from '../../api/endpoints';
import type { NamedEntity } from '../../models';

// What the single selectors of General information, Check command and
// Monitoring engine share: they came from one section (MON-213119).

type ConnectedAutocomplete = NonNullable<InputProps['connectedAutocomplete']>;

const namedEntityDecoder = JsonDecoder.object<NamedEntity>(
  { id: JsonDecoder.number, name: JsonDecoder.string },
  'Entity'
);

// The selectors answer in Hydra, which the autocomplete cannot read unmapped.
const selectorDecoder = buildListingDecoder({
  apiFormat: 'JSON-LD',
  entityDecoder: namedEntityDecoder,
  entityDecoderName: 'Entity',
  listingDecoderName: 'Entity List'
});

export const buildSelector = ({
  endpoint,
  queryKey
}: {
  endpoint: string;
  queryKey: string;
}): ConnectedAutocomplete => ({
  additionalConditionParameters: [],
  baseEndpoint: servicesBaseEndpoint,
  customQueryParameters: [],
  decoder: selectorDecoder,
  endpoint,
  getOptionLabel: (option) => (option as SelectEntry)?.name,
  queryKey,
  useNewAPIFormat: true
});

export const optionalNamedEntityDecoder = (name: string) =>
  JsonDecoder.optional(
    JsonDecoder.nullable(
      JsonDecoder.object<NamedEntity>(
        { id: JsonDecoder.number, name: JsonDecoder.string },
        name
      )
    )
  ).map((value) => value ?? null);

export const namedEntitySchema = object({
  id: number(),
  name: string()
}).nullable();
