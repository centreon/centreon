import { isEmpty } from 'ramda';

import { Resource } from './models';

export const areResourcesFullfilled = (
  resourcesDataset: Array<Resource>
): boolean =>
  !isEmpty(resourcesDataset) &&
  resourcesDataset?.every(
    ({ resourceType, resources }) =>
      !isEmpty(resourceType) && !isEmpty(resources)
  );
