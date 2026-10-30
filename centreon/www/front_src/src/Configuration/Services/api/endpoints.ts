import {
  type BuildListingEndpointParameters,
  buildListingEndpoint,
  type SearchParameter
} from '@centreon/ui';

// Served by API Platform at `./api`, like the hosts. The Core API keeps its own
// `/configuration/services` under `./api/latest`.
export const servicesBaseEndpoint = './api';

export const servicesListEndpoint = '/configuration/services';

export const serviceTemplatesEndpoint = '/configuration/service_templates';

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
