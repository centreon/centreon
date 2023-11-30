import { buildListingEndpoint } from '@centreon/ui';

import { BuildListTokensEndpoint } from './models';

const baseEndpoint = './api/latest';

export const listTokensEndpoint = `${baseEndpoint}/administration/tokens`;
export const createTokenEndpoint = `/administration/tokens`;

export const buildListTokensEndpoint = ({
  parameters,
  customQueryParameters
}: BuildListTokensEndpoint): string =>
  buildListingEndpoint({
    baseEndpoint: listTokensEndpoint,
    customQueryParameters,
    parameters
  });
