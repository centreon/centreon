import { buildListingDecoder } from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';

import { AiProvider, AiProviderListItem } from '../models';

const listOptionalNumber = JsonDecoder.optional(
  JsonDecoder.nullable(JsonDecoder.number)
);

const aiProviderListItemDecoder = JsonDecoder.object<AiProviderListItem>(
  {
    currency: JsonDecoder.optional(JsonDecoder.nullable(JsonDecoder.string)),
    defaultModel: JsonDecoder.string,
    description: JsonDecoder.optional(JsonDecoder.nullable(JsonDecoder.string)),
    endpointUrl: JsonDecoder.string,
    fastModel: JsonDecoder.optional(JsonDecoder.nullable(JsonDecoder.string)),
    id: JsonDecoder.number,
    isActivated: JsonDecoder.boolean,
    limitDaily: listOptionalNumber,
    limitMonthly: listOptionalNumber,
    name: JsonDecoder.string,
    type: JsonDecoder.string,
    usedDaily: listOptionalNumber,
    usedMonthly: listOptionalNumber
  },
  'AiProvider',
  {
    defaultModel: 'default_model',
    endpointUrl: 'endpoint_url',
    fastModel: 'fast_model',
    isActivated: 'is_activated',
    limitDaily: 'limit_daily',
    limitMonthly: 'limit_monthly',
    usedDaily: 'used_daily',
    usedMonthly: 'used_monthly'
  }
);

export const aiProvidersListDecoder = buildListingDecoder({
  apiFormat: 'JSON-LD',
  entityDecoder: aiProviderListItemDecoder,
  entityDecoderName: 'AiProvider',
  listingDecoderName: 'AiProviders List'
});

const optionalNumber = JsonDecoder.optional(
  JsonDecoder.nullable(JsonDecoder.number)
);

export const aiProviderDecoder = JsonDecoder.object<AiProvider>(
  {
    apiToken: JsonDecoder.optional(JsonDecoder.string),
    currency: JsonDecoder.optional(JsonDecoder.nullable(JsonDecoder.string)),
    defaultModel: JsonDecoder.string,
    description: JsonDecoder.optional(JsonDecoder.nullable(JsonDecoder.string)),
    endpointUrl: JsonDecoder.string,
    fastModel: JsonDecoder.optional(JsonDecoder.nullable(JsonDecoder.string)),
    isActivated: JsonDecoder.boolean,
    limitDaily: optionalNumber,
    limitMonthly: optionalNumber,
    name: JsonDecoder.string,
    priceDefaultInput: optionalNumber,
    priceDefaultOutput: optionalNumber,
    priceFastInput: optionalNumber,
    priceFastOutput: optionalNumber,
    type: JsonDecoder.string
  },
  'AiProvider',
  {
    apiToken: 'api_token',
    defaultModel: 'default_model',
    endpointUrl: 'endpoint_url',
    fastModel: 'fast_model',
    isActivated: 'is_activated',
    limitDaily: 'limit_daily',
    limitMonthly: 'limit_monthly',
    priceDefaultInput: 'price_default_input',
    priceDefaultOutput: 'price_default_output',
    priceFastInput: 'price_fast_input',
    priceFastOutput: 'price_fast_output'
  }
);
