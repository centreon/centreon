import { buildListingEndpoint } from '@centreon/ui';
import type { ListingParameters } from '@centreon/ui';

import { baseEndpoint } from '../../../api/endpoint';

export const resourceAccessRulesEndpoint = `${baseEndpoint}/administration/resource-access/rules`;

const buildResourceAccessRulesEndpoint = (
  parameters: ListingParameters
): string =>
  buildListingEndpoint({
    baseEndpoint: resourceAccessRulesEndpoint,
    parameters
  });

export { buildResourceAccessRulesEndpoint };
