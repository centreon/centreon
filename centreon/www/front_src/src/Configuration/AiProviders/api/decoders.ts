import { buildListingDecoder } from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';

import { AiProvider, AiProviderListItem } from '../models';

const aiProviderListItemDecoder = JsonDecoder.object<AiProviderListItem>(
  {
    defaultModel: JsonDecoder.string,
    description: JsonDecoder.optional(JsonDecoder.nullable(JsonDecoder.string)),
    endpointUrl: JsonDecoder.string,
    fastModel: JsonDecoder.optional(JsonDecoder.nullable(JsonDecoder.string)),
    id: JsonDecoder.number,
    isActivated: JsonDecoder.boolean,
    name: JsonDecoder.string,
    type: JsonDecoder.string
  },
  'AiProvider',
  {
    defaultModel: 'default_model',
    endpointUrl: 'endpoint_url',
    fastModel: 'fast_model',
    isActivated: 'is_activated'
  }
);

export const aiProvidersListDecoder = buildListingDecoder({
  apiFormat: 'JSON-LD',
  entityDecoder: aiProviderListItemDecoder,
  entityDecoderName: 'AiProvider',
  listingDecoderName: 'AiProviders List'
});

export const aiProviderDecoder = JsonDecoder.object<AiProvider>(
  {
    apiToken: JsonDecoder.optional(JsonDecoder.string),
    defaultModel: JsonDecoder.string,
    description: JsonDecoder.optional(JsonDecoder.nullable(JsonDecoder.string)),
    endpointUrl: JsonDecoder.string,
    fastModel: JsonDecoder.optional(JsonDecoder.nullable(JsonDecoder.string)),
    isActivated: JsonDecoder.boolean,
    name: JsonDecoder.string,
    type: JsonDecoder.string
  },
  'AiProvider',
  {
    apiToken: 'api_token',
    defaultModel: 'default_model',
    endpointUrl: 'endpoint_url',
    fastModel: 'fast_model',
    isActivated: 'is_activated'
  }
);
