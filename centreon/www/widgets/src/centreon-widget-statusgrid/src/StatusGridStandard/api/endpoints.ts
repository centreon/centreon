import { equals, flatten, includes, pluck } from 'ramda';

import { buildListingEndpoint } from '@centreon/ui';

import { Resource } from '../../../../models';
import { formatStatus } from '../../../../utils';

export const resourcesEndpoint = '/monitoring/resources';

interface BuildResourcesEndpointProps {
  limit: number;
  resources: Array<Resource>;
  sortBy: string;
  states: Array<string>;
  statuses: Array<string>;
  type: string;
}

const resourceTypesCustomParameters = [
  'host-group',
  'host-category',
  'service-group',
  'service-category'
];
const resourceTypesSearchParameters = ['host', 'service'];

const categories = ['host-category', 'service-category'];

const resourcesSearchMapping = {
  host: 'parent_name',
  service: 'name'
};

export const buildResourcesEndpoint = ({
  type,
  statuses,
  states,
  sortBy,
  limit,
  resources
}: BuildResourcesEndpointProps): string => {
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

  const sortOrder = equals(sortBy, 'status_severity_code') ? 'DESC' : 'ASC';

  return buildListingEndpoint({
    baseEndpoint: resourcesEndpoint,
    customQueryParameters: [
      { name: 'types', value: [type] },
      { name: 'statuses', value: formattedStatuses },
      { name: 'states', value: states },
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
      limit,
      page: 1,
      search: {
        conditions: flatten(searchConditions)
      },
      sort: {
        [sortBy]: sortOrder
      }
    }
  });
};

interface BuildServicesEndpointProps {
  page?: number;
  parentName: string;
}

export const buildServicesEndpoint = ({
  parentName,
  page
}: BuildServicesEndpointProps): string => {
  return buildListingEndpoint({
    baseEndpoint: resourcesEndpoint,
    parameters: {
      limit: 10,
      page,
      search: {
        conditions: [
          {
            field: 'parent_name',
            values: {
              $rg: `^${parentName}$`
            }
          }
        ]
      },
      sort: {
        status: 'DESC'
      }
    }
  });
};
