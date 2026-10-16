import { usePrefetchProvidersConfiguration } from '../Login/api/providersConfiguration';

/**
 * Renders nothing. Mounted outside public pages so that the providers
 * configuration is already cached when the login page mounts.
 */
const PrefetchProvidersConfiguration = (): null => {
  usePrefetchProvidersConfiguration();

  return null;
};

export default PrefetchProvidersConfiguration;
