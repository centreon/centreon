import { buildListingEndpoint } from '@centreon/ui';

import { BuildListTokensEndpoint } from './models';

export const listTokensEndpoint = `/administration/tokens`;

export const buildListTokensEndpoint = ({
  parameters,
  customQueryParameters
}: BuildListTokensEndpoint): string =>
  buildListingEndpoint({
    baseEndpoint: listTokensEndpoint,
    customQueryParameters,
    parameters
  });
