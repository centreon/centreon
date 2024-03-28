import { equals, flatten, includes, pluck } from 'ramda';

import { buildListingEndpoint } from '@centreon/ui';

import { Resource } from '../../../models';

export const serviceStatusesEndpoint = '/monitoring/services/status';
export const hostStatusesEndpoint = '/monitoring/hosts/status';
export const resourcesEndpoint = '/monitoring/resources';

interface BuildResourcesEndpointProps {
  resources: Array<Resource>;
  type: 'host' | 'service';
}

const resourceTypesCustomParameters = [
  'host-group',
  'host-category',
  'service-group',
  'service-category'
];

const hostTypesCustomParameters = ['host-group', 'host-category'];

const resourceTypesSearchParameters = ['host', 'service'];

const categories = ['host-category', 'service-category'];

const resourcesSearchMapping = {
  host: 'parent_name',
  service: 'name'
};

export const buildResourcesEndpoint = ({
  type,
  resources
}: BuildResourcesEndpointProps): string => {
  const baseEndpoint = equals(type, 'host')
    ? hostStatusesEndpoint
    : serviceStatusesEndpoint;

  const resourcesToApplyToCustomParameters = resources.filter(
    ({ resourceType }) =>
      equals(type, 'host')
        ? includes(resourceType, hostTypesCustomParameters)
        : includes(resourceType, resourceTypesCustomParameters)
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
