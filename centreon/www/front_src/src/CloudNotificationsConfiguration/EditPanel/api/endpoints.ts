import { isNil } from 'ramda';

import { baseEndpoint } from '../../../api/endpoint';

// export const endpoint = `${baseEndpoint}/notifications`;

export const notificationListingEndpoint = `http://localhost:3000/api/latest/notifications`;

interface Props {
  id?: number;
}
export const notificationtEndpoint = ({ id }: Props): string => {
  if (isNil(id)) {
    return notificationListingEndpoint;
  }

  return `${notificationListingEndpoint}/${id}`;
};

export const hostsGroupsEndpoint = `${baseEndpoint}/configuration/hosts/groups`;
export const serviceGroupsEndpoint = `${baseEndpoint}/configuration/services/groups`;
// export const businessViewsEndpoint = `${baseEndpoint}/bam/configuration/business-views`;
export const usersEndpoint = `${baseEndpoint}/configuration/users`;
