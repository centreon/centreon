import { useFetchQuery } from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';

import { aiAvailabilityEndpoint } from './endpoints';

interface AiAvailability {
  isAvailable: boolean;
}

const aiAvailabilityDecoder = JsonDecoder.object<AiAvailability>(
  {
    isAvailable: JsonDecoder.boolean
  },
  'AiAvailability',
  {
    isAvailable: 'is_available'
  }
);

// AI features are hidden until the platform confirms an AI provider is enabled
const useAiAvailability = (): boolean => {
  const { data } = useFetchQuery<AiAvailability>({
    decoder: aiAvailabilityDecoder,
    getEndpoint: () => aiAvailabilityEndpoint,
    getQueryKey: () => ['aiAvailability'],
    httpCodesBypassErrorSnackbar: [401, 403, 404]
  });

  return data?.isAvailable ?? false;
};

export default useAiAvailability;
