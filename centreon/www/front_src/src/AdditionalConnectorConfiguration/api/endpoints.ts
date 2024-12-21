import { buildListingEndpoint } from '@centreon/ui';

export const additionalConnectorsEndpoint =
  '/configuration/additional-connector-configurations';

export const getAdditionalConnectorEndpoint = (id): string =>
  `/configuration/additional-connector-configurations/${id}`;

export const pollersEndpoint = '/configuration/monitoring-servers';

export const getPollersEndpoint = (parameters): string =>
  buildListingEndpoint({
    baseEndpoint: pollersEndpoint,
    parameters
  });
