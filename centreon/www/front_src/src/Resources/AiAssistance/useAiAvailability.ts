import { useFetchQuery } from '@centreon/ui';

import { aiAvailabilityDecoder } from './api/decoders';
import { aiAvailabilityEndpoint } from './api/endpoints';
import { AiAvailability } from './models';

export interface AiAvailabilityState {
  // the budget is reached: the features stay visible but disabled
  isBudgetExhausted: boolean;
  // an AI provider is enabled and within budget: the features are usable
  isAvailable: boolean;
  // a provider is enabled, within budget or not: the features are shown
  // (otherwise they are hidden entirely)
  isConfigured: boolean;
}

const useAiAvailability = (): AiAvailabilityState => {
  const { data } = useFetchQuery<AiAvailability>({
    decoder: aiAvailabilityDecoder,
    getEndpoint: () => aiAvailabilityEndpoint,
    getQueryKey: () => ['aiAvailability'],
    httpCodesBypassErrorSnackbar: [401, 403, 404],
    queryOptions: {
      // re-check when the user comes back to the tab, so a provider being
      // enabled/disabled or its budget running out is reflected without a
      // full reload; the short stale time avoids refetching on quick switches
      refetchOnWindowFocus: true,
      staleTime: 60_000
    }
  });

  const isBudgetExhausted = data?.reason === 'budget_exhausted';

  return {
    isAvailable: data?.isAvailable ?? false,
    isBudgetExhausted,
    isConfigured: (data?.isAvailable ?? false) || isBudgetExhausted
  };
};

export default useAiAvailability;
