import { buildListingEndpoint } from '@centreon/ui';

import { BuildListEndpoint, Parameters, TokenEndpoint } from './models';

export const baseTokenEndpoint = '/administration/tokens';

export const listTokensEndpoint = baseTokenEndpoint;
export const createTokenEndpoint = baseTokenEndpoint;
export const listUsers = '/configuration/users';

export const buildListEndpoint = ({
  parameters,
  customQueryParameters,
  endpoint
}: BuildListEndpoint): string =>
  buildListingEndpoint({
    baseEndpoint: endpoint,
    customQueryParameters: customQueryParameters ?? undefined,
    parameters
  });

export const getEndpointConfiguredUser = (
  dataConfiguredUser: Parameters
): string => {
  return buildListEndpoint({
    endpoint: listUsers,
    parameters: { ...dataConfiguredUser, limit: 10 }
  });
};

export const getEndpointCreatorsToken = (dataCreatorsToken): string => {
  return buildListEndpoint({
    endpoint: listTokensEndpoint,
    parameters: { ...dataCreatorsToken, limit: 10 }
  });
};

export const getTokenEndpoint = ({
  tokenName,
  userId
}: TokenEndpoint): string =>
  `${baseTokenEndpoint}/${tokenName}/users/${userId}`;
