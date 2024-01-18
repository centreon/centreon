import { buildListingEndpoint } from '@centreon/ui';

import { BuildListEndpoint } from './models';

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

export const deleteTokenEndpoint = (tokenName: string): string =>
  `${baseTokenEndpoint}/${tokenName}`;
