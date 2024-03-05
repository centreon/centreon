import { flatten, includes, pluck } from 'ramda';

import { buildListingEndpoint } from '@centreon/ui';

import { Resource } from '../../../../models';
import { formatStatus } from '../../../../utils';

export const getStatusesEndpoint = (resourceType: 'host' | 'service'): string =>
  `monitoring/${resourceType}s/status`;

const resourcesSearchMapping = {
  host: 'parent_name',
  service: 'name'
};
const resourceTypesCustomParameters = [
  'host-group',
  'host-category',
  'service-group',
  'service-category'
];
const resourceTypesSearchParameters = ['host', 'service'];
const categories = ['host-category', 'service-category'];

interface BuildStatusesEndpointProps {
  resources: Array<Resource>;
  statuses: Array<string>;
  type: 'host' | 'service';
}

export const buildStatusesEndpoint = ({
  resources,
  type,
  statuses
}: BuildStatusesEndpointProps): string => {
  const formattedStatuses = formatStatus(statuses);

  const resourcesToApplyToCustomParameters = resources.filter(
    ({ resourceType }) => includes(resourceType, resourceTypesCustomParameters)
  );
  const resourcesToApplyToSearchParameters = resources.filter(
    ({ resourceType }) => includes(resourceType, resourceTypesSearchParameters)
  );

  const searchConditions = resourcesToApplyToSearchParameters.map(
    ({ resourceType, resources: resourcesToApply }) => {
      return resourcesToApply.map((resource) => ({
        field: resourcesSearchMapping[resourceType],
        values: {
          $rg: `^${resource.name}$`
        }
      }));
    }
  );

  return buildListingEndpoint({
    baseEndpoint: getStatusesEndpoint(type),
    customQueryParameters: [
      { name: 'types', value: [type] },
      { name: 'statuses', value: formattedStatuses },
      ...resourcesToApplyToCustomParameters.map(
        ({ resourceType, resources: resourcesToApply }) => ({
          name: includes(resourceType, categories)
            ? `${resourceType.replace('-', '_')}_names`
            : `${resourceType.replace('-', '')}_names`,
          value: pluck('name', resourcesToApply)
        })
      )
    ],
    parameters: {
      search: {
        conditions: flatten(searchConditions)
      }
    }
  });
};
