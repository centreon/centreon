import {
  type BuildListingEndpointParameters,
  buildListingEndpoint
} from '@centreon/ui';

export const additionalConnectorsEndpoint =
  '/configuration/additional-connector-configurations';

export const getAdditionalConnectorEndpoint = ({
  id
}: {
  id: number | string;
}): string => `/configuration/additional-connector-configurations/${id}`;

export const pollersEndpoint = '/configuration/monitoring-servers';

export const getPollersEndpoint = (
  parameters: BuildListingEndpointParameters['parameters']
): string =>
  buildListingEndpoint({
    baseEndpoint: pollersEndpoint,
    parameters
  });
