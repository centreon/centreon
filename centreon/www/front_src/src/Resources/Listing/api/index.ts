import { CancelToken } from 'axios';

import { getData } from '@centreon/ui';

import { ResourceListing } from '../../models';

import { ListResourcesProps, buildResourcesEndpoint } from './endpoint';

const listResources =
  (cancelToken: CancelToken) =>
  (parameters: ListResourcesProps): Promise<ResourceListing> =>
    getData<ResourceListing>(cancelToken)({
      endpoint: buildResourcesEndpoint(parameters)
    });

export { listResources };
