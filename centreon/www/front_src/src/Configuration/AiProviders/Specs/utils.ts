// The first provider is enabled, metered and close to its monthly cap; the
// second one is disabled and has no pricing.
export const getListingResponse = () => ({
  '@context': '/centreon/api/latest/contexts/ListAiProviderResource',
  '@id': '/centreon/api/latest/administration/ai-providers',
  '@type': 'Collection',
  member: [
    {
      '@id': '/centreon/api/latest/list_ai_provider_resources/1',
      '@type': 'ListAiProviderResource',
      currency: 'USD',
      default_model: 'claude-sonnet-4-6',
      description: 'Main provider',
      endpoint_url: 'https://api.anthropic.com',
      fast_model: 'claude-haiku-4-5',
      id: 1,
      is_activated: true,
      limit_daily: null,
      limit_monthly: 50,
      name: 'Anthropic production',
      type: 'anthropic',
      used_daily: 1.2,
      used_monthly: 41
    },
    {
      '@id': '/centreon/api/latest/list_ai_provider_resources/2',
      '@type': 'ListAiProviderResource',
      default_model: 'mistral-small-latest',
      description: null,
      endpoint_url: 'https://api.mistral.ai',
      fast_model: null,
      id: 2,
      is_activated: false,
      name: 'Mistral fallback',
      type: 'mistral'
    }
  ],
  totalItems: 2
});

export const getAiProviderResponse = () => ({
  '@context': '/centreon/api/latest/contexts/AiProviderResource',
  '@id': '/centreon/api/latest/administration/ai-providers/1',
  '@type': 'AiProviderResource',
  default_model: 'claude-sonnet-4-6',
  description: 'Main provider',
  endpoint_url: 'https://api.anthropic.com',
  fast_model: null,
  id: 1,
  is_activated: true,
  name: 'Anthropic production',
  type: 'anthropic'
});

export const discoveredModelsResponse = {
  models: ['claude-haiku-5-5', 'claude-opus-5-5', 'claude-sonnet-5-5']
};

export const getProvidersCountResponse = (total: number) => ({
  ...getListingResponse(),
  member: getListingResponse().member.slice(0, Math.min(total, 1)),
  totalItems: total
});

export const connectionTestResponse = {
  message: null,
  model: 'claude-sonnet-5-5',
  success: true
};

export const priceListUnavailableMessage =
  'The price list is unavailable, enter the prices by hand';

export const priceListUnavailableResponse = {
  code: 502,
  message: priceListUnavailableMessage
};
