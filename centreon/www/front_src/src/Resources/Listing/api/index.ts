import { CancelToken } from 'axios';

import { getData } from '@centreon/ui';

import { ResourceListing } from '../../models';

import { buildResourcesEndpoint, ListResourcesProps } from './endpoint';

const listResources =
  (cancelToken: CancelToken) =>
  (parameters: ListResourcesProps): Promise<ResourceListing> =>
<<<<<<< HEAD
    getData<ResourceListing>(cancelToken)({
      endpoint: buildResourcesEndpoint(parameters),
    });
=======
    getData<ResourceListing>(cancelToken)(buildResourcesEndpoint(parameters));
>>>>>>> centreon/dev-21.10.x

export { listResources };
