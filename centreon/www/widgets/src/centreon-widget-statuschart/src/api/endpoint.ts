import { equals, flatten } from 'ramda';

import { buildListingEndpoint } from '@centreon/ui';

import { Resource } from '../../../models';

export const serviceStatusesEndpoint = '/monitoring/services/status';
export const hostStatusesEndpoint = '/monitoring/hosts/status';
export const resourcesEndpoint = '/monitoring/resources';

interface BuildResourcesEndpointProps {
  resources: Array<Resource>;
  type: 'host' | 'service';
}

export const buildResourcesEndpoint = ({
  type,
  resources
}: BuildResourcesEndpointProps): string => {
  const baseEndpoint = equals(type, 'host')
    ? hostStatusesEndpoint
    : serviceStatusesEndpoint;

  const formattedResources = resources.map((resource) => {
    if (!equals(type, resource.resourceType)) {
      return {
        ...resource,
        resourceType: `${resource.resourceType.replace('-', '_')}.name`
      };
    }

    return { ...resource, resourceType: 'name' };
  });

  const searchConditions = formattedResources.map(
    ({ resourceType, resources: resourcesToApply }) => {
      return resourcesToApply.map((resource) => ({
        field: resourceType,
        values: {
          $rg: `^${resource.name}$`
        }
      }));
    }
  );

  return buildListingEndpoint({
    baseEndpoint,
    parameters: {
      search: {
        conditions: flatten(searchConditions)
      }
    }
  });
};
