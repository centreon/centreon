import { buildListingEndpoint } from '@centreon/ui';

import { BuildListEndpoint } from './models';

export const listTokensEndpoint = `/administration/tokens`;
export const createTokenEndpoint = `/administration/tokens`;
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
