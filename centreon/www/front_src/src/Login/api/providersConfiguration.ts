import { usePrefetchQuery } from '@centreon/ui';

import { ProviderConfiguration } from '../models';
import { providersConfigurationDecoder } from './decoder';
import { providersConfigurationEndpoint } from './endpoint';

export const providersConfigurationQueryKey = ['providerConfiguration'];

/**
 * Warms the providers configuration cache so that the login page can decide
 * right away whether it must redirect to a forced provider, instead of
 * displaying the login form while waiting for that request.
 */
export const usePrefetchProvidersConfiguration = (): void =>
  usePrefetchQuery<Array<ProviderConfiguration>>({
    decoder: providersConfigurationDecoder,
    getEndpoint: () => providersConfigurationEndpoint,
    getQueryKey: () => providersConfigurationQueryKey
  });
