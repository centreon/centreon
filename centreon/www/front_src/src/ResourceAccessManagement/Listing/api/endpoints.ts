import { buildListingEndpoint, ListingParameters } from '@centreon/ui';

import { baseEndpoint } from '../../../api/endpoint';

export const resourceAccessRulesEndpoint = `${baseEndpoint}/administration/resource-access-rules`;

export const buildResourceAccessRulesEndpoint = (
  parameters: ListingParameters
): string =>
  buildListingEndpoint({
    baseEndpoint: resourceAccessRulesEndpoint,
    parameters
  });
