import { buildListingEndpoint } from '@centreon/ui';

import { BuildListEndpoint, DeleteTokenEndpoint } from './models';

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
    customQueryParameters,
    parameters
  });

export const deleteTokenEndpoint = ({
  tokenName,
  userId
}: DeleteTokenEndpoint): string =>
  `${baseTokenEndpoint}/${tokenName}/users/${userId}`;
