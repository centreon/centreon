import { buildListingEndpoint, ListingParameters } from '@centreon/ui';

const baseEndpoint = './api/latest';

const listTokensEndpoint = `${baseEndpoint}/administration/tokens`;
export const createTokenEndpoint = `${baseEndpoint}/administration/tokens`;

export const buildListTokensEndpoint = (
  parameters: ListingParameters
): string =>
  buildListingEndpoint({
    baseEndpoint: listTokensEndpoint,
    parameters
  });
