import { buildListingEndpoint, useFetchQuery } from '@centreon/ui';
import { isEmpty } from 'ramda';
import { getResourceStatusDetailsEndpoint } from 'www/front_src/src/Dashboards/SingleInstancePage/Dashboard/Widgets/utils';

export const useSearchResources = (search) => {
  const { data } = useFetchQuery({
    getQueryKey: () => ['global-search', 'resources', search],
    getEndpoint: () =>
      buildListingEndpoint({
        baseEndpoint: '/monitoring/resources',
        parameters: {
          page: 1,
          limit: 5,
          search: {
            regex: {
              fields: ['name'],
              value: search
            }
          }
        }
      }),
    queryOptions: {
      enabled: !isEmpty(search),
      suspense: false
    }
  });

  return (
    data?.result.reduce(
      (acc, { links, name, uuid, id, parent, type }) => [
        ...acc,
        {
          label: `Configure ${name}`,
          url: links.uris.configuration.replace('/centreon', ''),
          type: 'configuration',
          resourceDetails: {
            id,
            uuid,
            name,
            type,
            parent,
            resourceId: id,
            resourcesDetailsEndpoint: links.endpoints.details
          }
        },
        {
          label: `View "${name}" on Resources Status`,
          resourceDetails: {
            id,
            uuid,
            name,
            type,
            parent,
            resourceId: id,
            resourcesDetailsEndpoint: links.endpoints.details
          },
          url: `/monitoring/resources?details=${encodeURIComponent(
            JSON.stringify({
              id,
              uuid,
              resourcesDetailsEndpoint: getResourceStatusDetailsEndpoint({
                parentId: parent?.id,
                id,
                resourceType: type
              }),
              selectedTimePeriodId: 'last_24_h',
              tab: 'details',
              tabParameters: {}
            })
          )}`,
          type: 'monitoring'
        }
      ],
      []
    ) || []
  );
};
