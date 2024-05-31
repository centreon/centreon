import { always, cond, equals, flatten, includes, pluck, T } from 'ramda';

import { buildListingEndpoint } from '@centreon/ui';

import { DisplayType } from '../Listing/models';
import { Resource } from '../../../models';
import { formatStatus } from '../../../utils';

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

const resourceTypesCustomParameters = [
  'host-group',
  'host-category',
  'service-group',
  'service-category'
];
const resourceTypesSearchParameters = ['host', 'service', 'meta-service'];

const categories = ['host-category', 'service-category'];

const resourcesSearchMapping = {
  host: 'parent_name',
  'meta-service': 'name',
  service: 'name'
};

const getFormattedType = cond([
  [equals('all'), always(['host', 'service', 'metaservice'])],
  [equals('service'), always(['service', 'metaservice'])],
  [T, (type) => [type]]
]);

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

  const formattedType = getFormattedType(type);
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
    baseEndpoint,
    customQueryParameters: [
      { name: 'types', value: formattedType },
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
      page,
      search: {
        conditions: flatten(searchConditions)
      },
      sort
    }
  });
};
