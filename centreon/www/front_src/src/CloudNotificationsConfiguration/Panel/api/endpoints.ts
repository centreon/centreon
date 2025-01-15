import { isNil } from 'ramda';

import { baseEndpoint } from '../../../api/endpoint';

export const notificationListingEndpoint = `${baseEndpoint}/configuration/notifications`;

interface Props {
  id?: number | null;
}
export const notificationEndpoint = ({ id }: Props): string => {
  if (isNil(id)) {
    return notificationListingEndpoint;
  }

  return `${notificationListingEndpoint}/${id}`;
};

export const hostsGroupsEndpoint = `${baseEndpoint}/configuration/hosts/groups`;
export const serviceGroupsEndpoint = `${baseEndpoint}/configuration/services/groups`;
export const businessViewsEndpoint = `${baseEndpoint}/bam/configuration/business-views`;
export const usersEndpoint = `${baseEndpoint}/configuration/users`;
export const availableTimePeriodsEndpoint = `${baseEndpoint}/configuration/timeperiods`;
