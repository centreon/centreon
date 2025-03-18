import { equals, flatten } from 'ramda';

import { useInfiniteScrollListing } from '@centreon/ui';

import { Resource } from '../../../../models';
import { tooltipPageAtom } from '../../StatusGridStandard/Tooltip/atoms';
import { ResourceStatus } from '../../StatusGridStandard/models';
import {
  baIndicatorsEndpoint,
  businessActivitiesEndpoint,
  getListingCustomQueryParameters,
  resourcesEndpoint
} from '../../api/endpoints';
import { getFormattedResources } from '../../../../../../front_src/src/Dashboards/SingleInstancePage/Dashboard/utils';

interface UseLoadResourcesProps {
  bypassRequest: boolean;
  isBAResourceType: boolean;
  isBVResourceType: boolean;
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
  bypassRequest,
  isBVResourceType,
  isBAResourceType
}: UseLoadResourcesProps): UseLoadResourcesState => {
  const getEndpoint = (): string => {
    if (isBVResourceType) {
      return businessActivitiesEndpoint;
    }
    if (isBAResourceType) {
      return baIndicatorsEndpoint;
    }

    return resourcesEndpoint;
  };

  const formattedResources = getFormattedResources({ array: resources });

  const resourcesToApplyToSearch  = formattedResources.map((resource) => {
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

  const resourcesSearchConditions = resourcesToApplyToSearch.map(
    ({ resourceType: type, resources: resourcesToApply }) => {
      return resourcesToApply.map((resource) => ({
        field: type,
        values: {
          $rg: `^${resource.name}$`.replace('/', '\\/')
        }
      }));
    }
  );

  const statusSearchConditions =
    isBVResourceType || isBAResourceType
      ? [
          {
            field: 'status',
            values: [status]
          }
        ]
      : [];

  const searchConditions = [
    ...resourcesSearchConditions,
    ...statusSearchConditions
  ];

  const { elementRef, elements, isLoading, total } =
    useInfiniteScrollListing<ResourceStatus>({
      customQueryParameters: getListingCustomQueryParameters({
        resources,
        statuses: [status],
        types: [resourceType]
      }),
      enabled: !bypassRequest,
      endpoint: getEndpoint(),
      limit: 10,
      pageAtom: tooltipPageAtom,
      parameters: {
        search: {
          conditions: flatten(searchConditions)
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
