import { always, cond, equals, flatten, includes, pluck, T } from 'ramda';

import { buildListingEndpoint } from '@centreon/ui';

import { DisplayType } from '../Listing/models';
import { NamedEntity, Resource } from '../../../models';
import { formatStatus } from '../../../utils';

export const resourcesEndpoint = '/monitoring/resources';
export const viewByHostEndpoint = '/monitoring/resources/hosts';

interface BuildResourcesEndpointProps {
  displayResources: 'all' | 'withTicket' | 'withoutTicket';
  hostSeverities: Array<NamedEntity>;
  isDownHostHidden: boolean;
  isUnreachableHostHidden: boolean;
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

  const getDisplayResources = (): Array<{ name: string; value: boolean }> => {
    if (equals(displayResources, 'all')) {
      return [];
    }

    return [
      {
        name: 'with_ticket_opened',
        value: !!equals(displayResources, 'withTicket')
      }
    ];
  };

  return buildListingEndpoint({
    baseEndpoint,
    customQueryParameters: [
      ...(provider ? [{ name: 'ticket_provider_id', value: provider.id }] : []),
      ...getDisplayResources(),
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
        conditions: [
          ...flatten(searchConditions),
          ...(isDownHostHidden
            ? [{ field: 'parent_status', value: [{ $neq: 'down' }] }]
            : []),
          ...(isUnreachableHostHidden
            ? [{ field: 'parent_status', value: [{ $neq: 'unreachable' }] }]
            : [])
        ]
      },
      sort
    }
  });
};
