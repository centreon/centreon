import {
  type BuildListingEndpointParameters,
  buildListingEndpoint
} from '@centreon/ui';

// Hosts are served by API Platform at `./api`, listing and single-host
// operations alike. Only the form's selectors below stay on the default base.
export const hostsBaseEndpoint = './api';

export const hostsListEndpoint = '/configuration/hosts';
export const hostFormHostsEndpoint = hostsListEndpoint;

export const hostTemplatesEndpoint = '/configuration/host_templates';
export const hostGroupsEndpoint = '/configuration/host_groups';
export const pollersEndpoint = '/configuration/pollers';

// The form's own selectors, which the listing filters above must not use: they
// are granted by host write access rather than by the poller and host group
// ACLs, and the poller one offers only active pollers, as the legacy form did.
export const hostFormPollersEndpoint = '/configuration/hosts/pollers';
export const hostFormHostGroupsEndpoint = '/configuration/hosts/host_groups';
export const hostFormHostCategoriesEndpoint =
  '/configuration/hosts/host_categories';
export const hostFormContactsEndpoint = '/configuration/hosts/contacts';
export const hostFormContactGroupsEndpoint =
  '/configuration/hosts/contact_groups';
export const hostFormTimePeriodsEndpoint = '/configuration/hosts/timeperiods';
export const hostFormHostSeveritiesEndpoint =
  '/configuration/hosts/host_severities';
export const hostFormMediasEndpoint = '/configuration/hosts/medias';

// No host-scoped timezone selector exists: this one is open to any
// authenticated user, so it has no ACL to get wrong.
export const timezonesEndpoint = '/configuration/timezones';

// No host-scoped command selector exists: this one is granted by the command
// ACLs rather than by host write access.
export const commandsEndpoint = '/configuration/commands';

export const getHostEndpoint = ({ id }: { id: number | string }): string =>
  `/configuration/hosts/${id}`;

export const getDeployServicesEndpoint = ({
  id
}: {
  id: number | string;
}): string => `/configuration/hosts/${id}/services/deploy`;

// On-premise only: the route does not exist on cloud.
export const resolveAddressEndpoint = '/configuration/hosts/_resolve';

export const getResolveAddressEndpoint = ({
  hostname
}: {
  hostname: string;
}): string =>
  `${resolveAddressEndpoint}?hostname=${encodeURIComponent(hostname)}`;

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

// There is no filter on the default poller, so it is looked for in one page
// wide enough for any realistic number of pollers.
export const getFormPollersEndpoint = (): string =>
  buildListingEndpoint({
    apiFormat: 'JSON-LD',
    baseEndpoint: hostFormPollersEndpoint,
    parameters: {
      limit: 100,
      page: 1
    } as BuildListingEndpointParameters['parameters']
  });
