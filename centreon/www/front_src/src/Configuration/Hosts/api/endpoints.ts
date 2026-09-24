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
export const pollersEndpoint = '/configuration/pollers';

export const getHostEndpoint = (params?: { id: number | string }): string =>
  `/configuration/hosts/${params?.id}`;

export const getDeployServicesEndpoint = ({
  id
}: {
  id: number | string;
}): string => `/configuration/hosts/${id}/services/deploy`;

export const getDuplicateHostEndpoint = ({
  id
}: {
  id: number | string;
}): string => `/configuration/hosts/${id}/_duplicate`;

type SearchParameter = {
  conditions?: Array<{ values?: { $lk?: string; $ni?: Array<string> } }>;
};

// Selectors take `name[lk]`, not the `search` payload the autocomplete builds.
const getSelectorEndpoint =
  (baseEndpoint: string) =>
  ({ search, page }: { search?: SearchParameter; page?: number }): string => {
    // Once a value is selected the autocomplete prepends a `$ni` condition
    // excluding it, so the typed text is not necessarily the first one.
    const searchedValue = search?.conditions?.find(
      (condition) => condition?.values?.$lk
    )?.values?.$lk;

    const customQueryParameters = search
      ? [
          {
            name: 'name[lk]',
            // The autocomplete wraps the typed text in `%`.
            value: searchedValue?.slice(1, -1) ?? ''
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
export const getPollersEndpoint = getSelectorEndpoint(pollersEndpoint);
