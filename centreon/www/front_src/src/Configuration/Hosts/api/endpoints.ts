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

// Creating a host — the second half of a client-side duplicate, if that is the
// route we end up taking.
export const createHostEndpoint = hostsListEndpoint;

// Neither bulk endpoint exists yet; HostGroups has all four.
//
// Duplicate is pending a backend decision, and the two answers plug in at
// different places:
//
//   - a real `POST /configuration/hosts/_duplicate` taking
//     `{ ids, nb_duplicates }` — nothing to do but keep this constant, which is
//     already what `api.endpoints.duplicate` points at;
//   - no endpoint, so the front reads each selected host and posts a copy —
//     `getHostEndpoint` and `createHostEndpoint` above are what that needs, and
//     it replaces `ConfigurationBase`'s shared `useDuplicate` rather than
//     configuring it, since that hook assumes one request for the whole
//     selection.
//
// `isSingleDuplicate` is deliberately left unset either way, so the dialog keeps
// asking how many copies to make, as the legacy page does.
export const bulkDeleteHostsEndpoint = '/configuration/hosts/_delete';
export const bulkDuplicateHostsEndpoint = '/configuration/hosts/_duplicate';

type SearchParameter = {
  conditions?: Array<{ values?: { $lk?: string; $ni?: Array<string> } }>;
};

// The connected autocomplete hands us its own search/page state; API Platform
// selectors take `name[lk]` rather than the `search` payload of the legacy API.
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
            // The autocomplete wraps the typed text in `%`; this endpoint adds
            // its own wildcards.
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
