import { equals } from 'ramda';

import { Status } from '../../../models';

export const getStatusNamesPerResourceType = (
  resourceType: string
): Array<Status> => {
  if (equals(resourceType, 'host')) {
    return ['down', 'unreachable', 'up', 'pending'];
  }

  return ['critical', 'warning', 'unknown', 'ok', 'pending'];
};
