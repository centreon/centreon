import { buildListingEndpoint } from '@centreon/ui';

export const resourcesEndpoint = '/monitoring/resources';

interface BuildResourcesEndpointProps {
  limit: number;
  sortBy: string;
  states: Array<string>;
  statuses: Array<string>;
  type: string;
}

export const buildResourcesEndpoint = ({
  type,
  statuses,
  states,
  sortBy,
  limit
}: BuildResourcesEndpointProps): string => {
  const formattedStatuses = statuses.map((state) => state.toLocaleUpperCase());

  return buildListingEndpoint({
    baseEndpoint: resourcesEndpoint,
    customQueryParameters: [
      { name: 'types', value: [type] },
      { name: 'statuses', value: formattedStatuses },
      { name: 'states', value: states }
    ],
    parameters: {
      limit,
      page: 1,
      sort: {
        [sortBy]: 'ASC'
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
        status: 'ASC'
      }
    }
  });
};
