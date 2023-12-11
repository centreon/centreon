import { buildListingEndpoint } from '@centreon/ui';

import { BuildListEndpoint } from './models';

export const listTokensEndpoint = `/administration/tokens`;

export const buildListTokensEndpoint = ({
  parameters,
  customQueryParameters,
  endpoint
}: BuildListEndpoint): string =>
  buildListingEndpoint({
    baseEndpoint: endpoint,
    customQueryParameters,
    parameters
  });
