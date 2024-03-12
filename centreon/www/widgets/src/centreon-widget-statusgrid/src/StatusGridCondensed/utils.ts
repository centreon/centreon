import { equals } from 'ramda';

import { Status } from '../../../models';

export const getStatusNamesPerResourceType = (
  resourceType: string
): Array<Status> => {
  if (equals(resourceType, 'service')) {
    return ['critical', 'warning', 'unknown', 'ok', 'pending'];
  }

  return ['down', 'unreachable', 'up', 'pending'];
};
