import {
  type BuildListingEndpointParameters,
  buildListingEndpoint
} from '@centreon/ui';

// The listing is served by API Platform at `./api`. Single-host operations
// (get, patch, delete) exist only under the default `./api/latest`.
export const hostsBaseEndpoint = './api';

export const hostsListEndpoint = '/configuration/hosts';

export const hostTemplatesEndpoint = '/configuration/host_templates';
export const hostGroupsEndpoint = '/configuration/host_groups';

// Single-host operations live on the legacy `./api/latest` prefix, not on the
// API Platform one the listing uses: DELETE and PATCH are Core routes, and only
// allowlisted API Platform operations are aliased across.
export const getHostEndpoint = (params?: { id: number | string }): string =>
  `/configuration/hosts/${params?.id}`;

export const getDeployServicesEndpoint = ({
  id
}: {
  id: number | string;
}): string => `/configuration/hosts/${id}/services/deploy`;

// No bulk endpoint exists for hosts yet — HostGroups has all four. The actions
// are built against these paths so wiring them later is one edit here.
export const bulkDeleteHostsEndpoint = '/configuration/hosts/_delete';
export const bulkDuplicateHostsEndpoint = '/configuration/hosts/_duplicate';

type SearchParameter = {
  conditions?: Array<{ values?: { $lk?: string } }>;
};

// The connected autocomplete hands us its own search/page state; API Platform
// selectors take `name[lk]` rather than the `search` payload of the legacy API.
const getSelectorEndpoint =
  (baseEndpoint: string) =>
  ({ search, page }: { search?: SearchParameter; page?: number }): string => {
    const customQueryParameters = search
      ? [
          {
            name: 'name[lk]',
            value: search.conditions?.[0]?.values?.$lk?.slice(1, -1) ?? ''
          }
        ]
      : [];

    return buildListingEndpoint({
      apiFormat: 'JSON-LD',
      baseEndpoint,
      customQueryParameters,
      parameters: {
        limit: 10,
        page
      } as BuildListingEndpointParameters['parameters']
    });
  };

export const getHostTemplatesEndpoint = getSelectorEndpoint(
  hostTemplatesEndpoint
);
export const getHostGroupsEndpoint = getSelectorEndpoint(hostGroupsEndpoint);
