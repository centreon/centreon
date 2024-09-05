import { buildListingEndpoint } from '@centreon/ui';

export const additionalConnectorsEndpoint =
  '/configuration/additional-connectors';

export const getAdditionalConnectorEndpoint = (id): string =>
  `/configuration/additional-connectors/${id}`;

export const pollersEndpoint = '/configuration/monitoring-servers';

export const getPollersForConnectorTypeEndpoint = ({
  type = 'vmware_v6'
}): string => `/configuration/additional-connectors/pollers/${type}`;

export const getPollersEndpoint = (parameters): string =>
  buildListingEndpoint({
    baseEndpoint: pollersEndpoint,
    parameters
  });
