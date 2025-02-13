import { equals, flatten } from 'ramda';

import { buildListingEndpoint } from '@centreon/ui';

import {
  WidgetResourceType,
  hostGroupParameter
} from '../../../../AddEditWidget/models';
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
      const resourceTypeName = equals(
        resource.resourceType,
        WidgetResourceType.hostGroup
      )
        ? hostGroupParameter
        : resource.resourceType;
      return {
        ...resource,
        resourceType: `${resourceTypeName.replace('-', '_')}.name`
      };
    }

    return { ...resource, resourceType: 'name' };
  });

  const searchConditions = formattedResources.map(
    ({ resourceType, resources: resourcesToApply }) => {
      return resourcesToApply.map((resource) => ({
        field: resourceType,
        values: {
          $rg: `^${resource.name}$`.replace('/', '\\/')
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
