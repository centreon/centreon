// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import { buildListingEndpoint } from '@centreon/ui';

import { always, cond, equals, pluck, T } from 'ramda';

import { baseEndpoint as apiBaseEndpoint } from '../../../../../../../api/endpoint';
import type { NamedEntity, Resource } from '../../../models';
import {
  formatStatus,
  getResourcesSearchQueryParameters
} from '../../../utils';
import type { DisplayType } from '../Listing/models';

export const resourcesEndpoint = '/monitoring/resources';
export const viewByHostEndpoint = '/monitoring/resources/hosts';

export const countResourcesEndpoint = '/monitoring/resources/count';
export const exportResourcesEndpoint = '/monitoring/resources/export';
export const maxExportedResources = 10000;

interface FiltersProps {
  displayResources?: 'withTicket' | 'withoutTicket';
  hostSeverities: Array<NamedEntity>;
  isDownHostHidden?: boolean;
  isUnreachableHostHidden?: boolean;
  provider?: { id: number; name: string };
  resources: Array<Resource>;
  serviceSeverities: Array<NamedEntity>;
  states: Array<string>;
  statusTypes: Array<'soft' | 'hard'>;
  statuses: Array<string>;
  type: DisplayType;
}

interface BuildResourcesEndpointProps extends FiltersProps {
  limit: number;
  page: number;
  sort;
}

const getFormattedType = cond([
  [equals('all'), always(['host', 'service', 'metaservice'])],
  [equals('service'), always(['service', 'metaservice'])],
  [T, (type) => [type]]
]);

// The widget filters as query parameters. The listing, the count and the CSV
// export all build their request from it, so that they select the same
// resources.
const getFiltersQueryParameters = ({
  type,
  statuses,
  states,
  resources,
  statusTypes,
  hostSeverities,
  serviceSeverities,
  isDownHostHidden,
  isUnreachableHostHidden,
  displayResources,
  provider
}: FiltersProps) => {
  const { resourcesSearchConditions, resourcesCustomParameters } =
    getResourcesSearchQueryParameters(resources);

  return {
    customQueryParameters: [
      ...(provider
        ? [
            { name: 'ticket_provider_id', value: provider.id },
            {
              name: 'only_with_opened_tickets',
              value: !!equals(displayResources, 'withTicket')
            }
          ]
        : []),
      { name: 'types', value: getFormattedType(type) },
      { name: 'statuses', value: formatStatus(statuses) },
      { name: 'status_types', value: statusTypes },
      ...(hostSeverities
        ? [
            {
              name: 'host_severity_names',
              value: pluck('name', hostSeverities)
            }
          ]
        : []),
      ...(serviceSeverities
        ? [
            {
              name: 'service_severity_names',
              value: pluck('name', serviceSeverities)
            }
          ]
        : []),
      { name: 'states', value: states },
      ...resourcesCustomParameters
    ],
    search: {
      conditions: [
        ...resourcesSearchConditions,
        ...(isDownHostHidden
          ? [{ field: 'parent_status', values: { $neq: 1 } }]
          : []),
        ...(isUnreachableHostHidden
          ? [{ field: 'parent_status', values: { $neq: 2 } }]
          : [])
      ]
    }
  };
};

export const buildResourcesEndpoint = ({
  sort,
  limit,
  page,
  ...filters
}: BuildResourcesEndpointProps): string => {
  const baseEndpoint = equals(filters.type, 'host')
    ? viewByHostEndpoint
    : resourcesEndpoint;

  const { customQueryParameters, search } = getFiltersQueryParameters(filters);

  return buildListingEndpoint({
    baseEndpoint,
    customQueryParameters,
    parameters: {
      limit,
      page,
      search,
      sort
    }
  });
};

interface Pagination {
  limit: number;
  page: number;
  sort;
}

interface BuildCountEndpointProps extends FiltersProps {
  pagination?: Pagination;
}

// Without `pagination`, counts all the resources matching the filters; with
// it, counts the resources of that page only.
export const buildCountEndpoint = ({
  pagination,
  ...filters
}: BuildCountEndpointProps): string => {
  const { customQueryParameters, search } = getFiltersQueryParameters(filters);

  return buildListingEndpoint({
    baseEndpoint: countResourcesEndpoint,
    customQueryParameters: [
      { name: 'all_pages', value: !pagination },
      ...customQueryParameters
    ],
    parameters: {
      ...pagination,
      search
    }
  });
};

interface BuildExportEndpointProps extends FiltersProps, Pagination {
  columns: Array<string>;
  isAllPages: boolean;
}

export const buildExportEndpoint = ({
  columns,
  isAllPages,
  limit,
  page,
  sort,
  ...filters
}: BuildExportEndpointProps): string => {
  const { customQueryParameters, search } = getFiltersQueryParameters(filters);

  const endpoint = buildListingEndpoint({
    baseEndpoint: `${apiBaseEndpoint}${exportResourcesEndpoint}`,
    customQueryParameters: [
      ...customQueryParameters,
      { name: 'all_pages', value: isAllPages },
      { name: 'max_lines', value: maxExportedResources }
    ],
    parameters: {
      limit,
      page,
      search,
      sort
    }
  });

  const columnsParameters = columns
    .map((column) => `columns[]=${column}`)
    .join('&');

  return `${endpoint}&${columnsParameters}&format=csv`;
};

export const closeTicketEndpoint =
  './modules/centreon-open-tickets/views/rules/closeTicket/action.php';
