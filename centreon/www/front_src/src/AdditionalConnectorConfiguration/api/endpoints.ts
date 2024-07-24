import { buildListingEndpoint } from '@centreon/ui';

export const additionalConnectorsEndpoint =
  'http://localhost:3000/api/latest/configuration/additional-connectors';

export const getAdditionalConnectorEndpoint = (id): string =>
  `http://localhost:3000/api/latest/configuration/additional-connectors/${id}`;

export const pollersEndpoint = '/configuration/monitoring-servers';

export const getPollersForConnectorTypeEndpoint = ({
  type = 'vmware_v6'
}): string =>
  `http://localhost:3000/api/latest/configuration/additional-connectors/pollers/${type}`;

export const getPollersEndpoint = (parameters): string =>
  buildListingEndpoint({
    baseEndpoint: pollersEndpoint,
    parameters
  });
