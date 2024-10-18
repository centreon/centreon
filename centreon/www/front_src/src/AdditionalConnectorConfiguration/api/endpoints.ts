import { buildListingEndpoint } from '@centreon/ui';

export const additionalConnectorsEndpoint =
  '/configuration/additional-connector-configurations';

export const getAdditionalConnectorEndpoint = (id): string =>
  `/configuration/additional-connector-configurations/${id}`;

export const pollersEndpoint = '/configuration/monitoring-servers';

export const getPollersForConnectorTypeEndpoint = ({
  type = 'vmware_v6'
}): string =>
  `/configuration/additional-connector-configurations/pollers/${type}`;

export const getPollersEndpoint = (parameters): string =>
  buildListingEndpoint({
    baseEndpoint: pollersEndpoint,
    parameters
  });
