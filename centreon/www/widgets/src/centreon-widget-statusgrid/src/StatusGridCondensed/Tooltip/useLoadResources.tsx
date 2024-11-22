import { equals, flatten } from 'ramda';

import { useInfiniteScrollListing } from '@centreon/ui';

import { Resource } from '../../../../models';
import { tooltipPageAtom } from '../../StatusGridStandard/Tooltip/atoms';
import { ResourceStatus } from '../../StatusGridStandard/models';
import {
  getListingCustomQueryParameters,
  resourcesEndpoint
} from '../../api/endpoints';

interface UseLoadResourcesProps {
  bypassRequest: boolean;
  resourceType: string;
  resources: Array<Resource>;
  status: string;
}

interface UseLoadResourcesState {
  elementRef;
  elements: Array<ResourceStatus>;
  isLoading: boolean;
  total?: number;
}

export const useLoadResources = ({
  resources,
  resourceType,
  status,
  bypassRequest
}: UseLoadResourcesProps): UseLoadResourcesState => {
  const formattedResources = resources.map((resource) => {
    if (!equals(resourceType, resource.resourceType)) {
      return {
        ...resource,
        resourceType: equals(resource.resourceType, 'host')
          ? 'parent_name'
          : `${resource.resourceType.replace('-', '_')}.name`
      };
    }

    return { ...resource, resourceType: 'name' };
  });

  const resourcesSearchConditions = formattedResources.map(
    ({ resourceType: type, resources: resourcesToApply }) => {
      return resourcesToApply.map((resource) => ({
        field: type,
        values: {
          $rg: `^${resource.name}$`
        }
      }));
    }
  );

  const { elementRef, elements, isLoading, total } =
    useInfiniteScrollListing<ResourceStatus>({
      customQueryParameters: getListingCustomQueryParameters({
        resources,
        statuses: [status],
        types: [resourceType]
      }),
      enabled: !bypassRequest,
      endpoint: resourcesEndpoint,
      limit: 10,
      pageAtom: tooltipPageAtom,
      parameters: {
        search: {
          conditions: flatten(resourcesSearchConditions)
        },
        sort: { status: 'DESC' }
      },
      queryKeyName: `statusgrid_condensed_${status}_${JSON.stringify(resources)}_${resourceType}`,
      suspense: false
    });

  return {
    elementRef,
    elements,
    isLoading,
    total
  };
};
