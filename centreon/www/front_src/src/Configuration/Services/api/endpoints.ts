import {
  type BuildListingEndpointParameters,
  buildListingEndpoint,
  type SearchParameter
} from '@centreon/ui';

// Served by API Platform at `./api`, like the hosts. The Core API keeps its own
// `/configuration/services` under `./api/latest`.
export const servicesBaseEndpoint = './api';

export const servicesListEndpoint = '/configuration/services';

export const serviceTemplatesEndpoint =
  '/configuration/services/service_templates';

// The form's host selector, granted by the service write right rather than by
// the host one.
export const serviceFormHostsEndpoint = '/configuration/services/hosts';

// The form's check period selector, granted by the service write right too.
export const serviceFormTimePeriodsEndpoint =
  '/configuration/services/timeperiods';

// The form's check command selector, granted by the service write right.
export const serviceFormCommandsEndpoint = '/configuration/services/commands';

// Placeholder until the backend exposes it, on the model of the hosts'
// `/configuration/hosts/host_templates/{id}`: one service template's macros,
// its own and those it inherits.
export const getServiceTemplateMacrosEndpoint = ({
  id
}: {
  id: number;
}): string => `${serviceTemplatesEndpoint}/${id}`;

// The service groups the user may see, as the host groups of a host.
export const serviceFormServiceGroupsEndpoint =
  '/configuration/services/service_groups';

// On-premise only: a cloud platform has no SNMP trap.
export const serviceFormTrapsEndpoint = '/configuration/services/traps';

// The form's other selectors, each granted by the service write right too.
// The graph templates exist on-premise only.
export const serviceFormGraphTemplatesEndpoint =
  '/configuration/services/graph_templates';
export const serviceFormMediasEndpoint = '/configuration/services/medias';
export const serviceFormServiceCategoriesEndpoint =
  '/configuration/services/service_categories';
export const serviceFormServiceSeveritiesEndpoint =
  '/configuration/services/service_severities';

export const getServiceEndpoint = ({ id }: { id: number | string }): string =>
  `/configuration/services/${id}`;

export const getDuplicateServiceEndpoint = ({
  id
}: {
  id: number | string;
}): string => `/configuration/services/${id}/_duplicate`;

// Selectors take `name[lk]`, not the `search` payload the autocomplete builds.
export const getServiceTemplatesEndpoint = ({
  search,
  page
}: {
  search?: SearchParameter;
  page?: number;
}): string => {
  // Once a value is selected the autocomplete prepends a `$ni` condition
  // excluding it, so the typed text is not necessarily the first one.
  const searchedValue = search?.conditions
    ?.map((condition) => condition?.values?.$lk)
    .find((value): value is string => typeof value === 'string');

  return buildListingEndpoint({
    apiFormat: 'JSON-LD',
    baseEndpoint: serviceTemplatesEndpoint,
    customQueryParameters: search
      ? [
          {
            name: 'name[lk]',
            // The autocomplete wraps the typed text in `%`.
            value: searchedValue?.slice(1, -1) ?? ''
          }
        ]
      : [],
    parameters: {
      limit: 10,
      page
    } as BuildListingEndpointParameters['parameters']
  });
};
