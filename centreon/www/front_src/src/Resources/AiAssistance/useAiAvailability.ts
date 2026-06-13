import { useFetchQuery } from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';

import { aiAvailabilityEndpoint } from './endpoints';

type AiUnavailableReason = 'no_provider' | 'budget_exhausted';

interface AiAvailability {
  isAvailable: boolean;
  reason?: AiUnavailableReason | null;
}

const aiAvailabilityDecoder = JsonDecoder.object<AiAvailability>(
  {
    isAvailable: JsonDecoder.boolean,
    reason: JsonDecoder.optional(
      JsonDecoder.nullable(
        JsonDecoder.enumeration<AiUnavailableReason>(
          { budget_exhausted: 'budget_exhausted', no_provider: 'no_provider' },
          'AiUnavailableReason'
        )
      )
    )
  },
  'AiAvailability',
  {
    isAvailable: 'is_available'
  }
);

export interface AiAvailabilityState {
  // the budget is reached: the features stay visible but disabled
  isBudgetExhausted: boolean;
  // an AI provider is enabled and within budget: the features are usable
  isAvailable: boolean;
  // no provider is enabled: the features are hidden entirely
  isConfigured: boolean;
}

// AI features react to the platform state: hidden when no provider is enabled,
// disabled when the enabled provider has reached its spend limit
const useAiAvailability = (): AiAvailabilityState => {
  const { data } = useFetchQuery<AiAvailability>({
    decoder: aiAvailabilityDecoder,
    getEndpoint: () => aiAvailabilityEndpoint,
    getQueryKey: () => ['aiAvailability'],
    httpCodesBypassErrorSnackbar: [401, 403, 404]
  });

  const isBudgetExhausted = data?.reason === 'budget_exhausted';

  return {
    isAvailable: data?.isAvailable ?? false,
    isBudgetExhausted,
    isConfigured: (data?.isAvailable ?? false) || isBudgetExhausted
  };
};

export default useAiAvailability;
