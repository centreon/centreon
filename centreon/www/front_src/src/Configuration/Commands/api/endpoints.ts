import { buildListingEndpoint } from '@centreon/ui';

export const commandsEndpoint = '/configuration/commands';

export const getAdditionalConnectorEndpoint = ({ id }): string =>
  `/configuration/additional-connector-configurations/${id}`;

export const pollersEndpoint = '/configuration/monitoring-servers';

export const getPollersEndpoint = (parameters): string =>
  buildListingEndpoint({
    baseEndpoint: pollersEndpoint,
    parameters
  });
