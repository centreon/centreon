import { buildListingEndpoint } from '@centreon/ui';

import { BuildListEndpoint, Meta } from './models';

export const baseTokenEndpoint = '/administration/tokens';

export const listTokensEndpoint = baseTokenEndpoint;
export const createTokenEndpoint = baseTokenEndpoint;
export const listConfiguredUser = '/configuration/users';

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

export const getEndpointConfiguredUser = (dataConfiguredUser): string => {
  return buildListEndpoint({
    endpoint: listConfiguredUser,
    parameters: { ...dataConfiguredUser, limit: 10 }
  });
};

export const getEndpointCreatorsToken = (dataCreatorsToken): string => {
  return buildListEndpoint({
    endpoint: listTokensEndpoint,
    parameters: { ...dataCreatorsToken, limit: 10 }
  });
};

export const deleteSingleTokenEndpoint = ({
  tokenName,
  userId
}: Meta): string => `${baseTokenEndpoint}/${tokenName}/users/${userId}`;

export const deleteMultipleTokensEndpoint = (): string =>
  `${baseTokenEndpoint}/_delete`;
