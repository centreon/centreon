import { equals } from 'ramda';

import { buildListingEndpoint } from '@centreon/ui';

import { DisplayType } from '../Listing/models';
import { Resource } from '../../../models';
import {
  formatStatus,
  getResourcesSearchQueryParameters
} from '../../../utils';

export const resourcesEndpoint = '/monitoring/resources';
export const viewByHostEndpoint = '/monitoring/resources/hosts';

interface BuildResourcesEndpointProps {
  limit: number;
  page: number;
  resources: Array<Resource>;
  sort;
  states: Array<string>;
  statuses: Array<string>;
  type: DisplayType;
}

export const buildResourcesEndpoint = ({
  type,
  statuses,
  states,
  sort,
  limit,
  resources,
  page
}: BuildResourcesEndpointProps): string => {
  const baseEndpoint = equals(type, 'host')
    ? viewByHostEndpoint
    : resourcesEndpoint;

  const formattedType = equals(type, 'all') ? ['host', 'service'] : [type];
  const formattedStatuses = formatStatus(statuses);

  const { resourcesSearchConditions, resourcesCustomParameters } =
    getResourcesSearchQueryParameters(resources);

  return buildListingEndpoint({
    baseEndpoint,
    customQueryParameters: [
      { name: 'types', value: formattedType },
      { name: 'statuses', value: formattedStatuses },
      { name: 'states', value: states },
      ...resourcesCustomParameters
    ],
    parameters: {
      limit,
      page,
      search: {
        conditions: resourcesSearchConditions
      },
      sort
    }
  });
};
