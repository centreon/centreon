import { buildListingDecoder } from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';

import { AiProvider, AiProviderListItem, AiProviderType } from '../models';

const typeDecoder = JsonDecoder.enumeration<AiProviderType>(
  AiProviderType,
  'AiProviderType'
);

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
    type: typeDecoder,
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
    type: typeDecoder
  },
  'AiProvider',
  {
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

export interface ConnectionTestResult {
  message?: string | null;
  model?: string | null;
  success: boolean;
}

export const connectionTestResultDecoder =
  JsonDecoder.object<ConnectionTestResult>(
    {
      message: JsonDecoder.optional(JsonDecoder.nullable(JsonDecoder.string)),
      model: JsonDecoder.optional(JsonDecoder.nullable(JsonDecoder.string)),
      success: JsonDecoder.boolean
    },
    'Connection test result'
  );

export interface DiscoveredModels {
  models: Array<string>;
}

export const discoveredModelsDecoder = JsonDecoder.object<DiscoveredModels>(
  { models: JsonDecoder.array(JsonDecoder.string, 'Models') },
  'Discovered models'
);

export interface ModelPrices {
  defaultModelInput?: number | null;
  defaultModelOutput?: number | null;
  fastModelInput?: number | null;
  fastModelOutput?: number | null;
}

const optionalPrice = JsonDecoder.optional(
  JsonDecoder.nullable(JsonDecoder.number)
);

export const modelPricesDecoder = JsonDecoder.object<ModelPrices>(
  {
    defaultModelInput: optionalPrice,
    defaultModelOutput: optionalPrice,
    fastModelInput: optionalPrice,
    fastModelOutput: optionalPrice
  },
  'Model prices',
  {
    defaultModelInput: 'default_model_input',
    defaultModelOutput: 'default_model_output',
    fastModelInput: 'fast_model_input',
    fastModelOutput: 'fast_model_output'
  }
);
