import { isNil } from 'ramda';

import { baseEndpoint } from '../../../../../api';

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
