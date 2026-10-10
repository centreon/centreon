import { customFetch, type ResponseError } from '@centreon/ui';

import type { QueryClient } from '@tanstack/react-query';

import { ProviderConfiguration } from '../models';
import { providersConfigurationDecoder } from './decoder';
import { providersConfigurationEndpoint } from './endpoint';

export const providersConfigurationQueryKey = ['providerConfiguration'];

const fetchProvidersConfiguration = ({
  signal
}: {
  signal?: AbortSignal;
}): Promise<Array<ProviderConfiguration> | ResponseError> =>
  customFetch<Array<ProviderConfiguration>>({
    decoder: providersConfigurationDecoder,
    endpoint: providersConfigurationEndpoint,
    signal
  });

/**
 * Warms the providers configuration cache so that the login page can decide
 * right away whether it must redirect to a forced provider, instead of
 * displaying the login form while waiting for that request.
 */
export const prefetchProvidersConfiguration = (
  queryClient: QueryClient
): void => {
  queryClient.prefetchQuery({
    queryFn: fetchProvidersConfiguration,
    queryKey: providersConfigurationQueryKey
  });
};
