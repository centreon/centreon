import { buildListingEndpoint } from '@centreon/ui';

export const getAgentConfigurationsEndpoint =
  '/configuration/agent_configurations';

export const getPollersEndpoint = (parameters): string =>
  buildListingEndpoint({
    baseEndpoint: '/configuration/monitoring-servers',
    parameters
  });
