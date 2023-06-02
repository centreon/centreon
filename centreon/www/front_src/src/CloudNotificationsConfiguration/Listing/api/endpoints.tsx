import { buildListingEndpoint } from '@centreon/ui';
import type { ListingParameters } from '@centreon/ui';

import { baseEndpoint } from '../../../api/endpoint';

export const notificationListingEndpoint = `${baseEndpoint}/notifications`;

const buildNotificationsEndpoint = (parameters: ListingParameters): string => {
  return buildListingEndpoint({
    baseEndpoint: notificationListingEndpoint,
    parameters
  });
};

export { buildNotificationsEndpoint };
