import { T, always, cond, equals, pluck } from 'ramda';

import { buildListingEndpoint } from '@centreon/ui';

import type { NamedEntity, Resource } from '../../../models';
import {
  formatStatus,
  getResourcesSearchQueryParameters
} from '../../../utils';
import type { DisplayType } from '../Listing/models';

export const resourcesEndpoint = '/monitoring/resources';
export const viewByHostEndpoint = '/monitoring/resources/hosts';

interface BuildResourcesEndpointProps {
  displayResources?: 'withTicket' | 'withoutTicket';
  hostSeverities: Array<NamedEntity>;
  isDownHostHidden?: boolean;
  isUnreachableHostHidden?: boolean;
  limit: number;
  page: number;
  provider?: { id: number; name: string };
  resources: Array<Resource>;
  serviceSeverities: Array<NamedEntity>;
  sort;
  states: Array<string>;
  statusTypes: Array<'soft' | 'hard'>;
  statuses: Array<string>;
  type: DisplayType;
}

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
  page,
  statusTypes,
  hostSeverities,
  serviceSeverities,
  isDownHostHidden,
  isUnreachableHostHidden,
  displayResources,
  provider
}: BuildResourcesEndpointProps): string => {
  const baseEndpoint = equals(type, 'host')
    ? viewByHostEndpoint
    : resourcesEndpoint;

  const formattedType = getFormattedType(type);
  const formattedStatuses = formatStatus(statuses);

  const { resourcesSearchConditions, resourcesCustomParameters } =
    getResourcesSearchQueryParameters(resources);

  return buildListingEndpoint({
    baseEndpoint,
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
      { name: 'types', value: formattedType },
      { name: 'statuses', value: formattedStatuses },
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
    parameters: {
      limit,
      page,
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
      },
      sort
    }
  });
};

export const closeTicketEndpoint =
  './modules/centreon-open-tickets/views/rules/closeTicket/action.php';
