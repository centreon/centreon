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
