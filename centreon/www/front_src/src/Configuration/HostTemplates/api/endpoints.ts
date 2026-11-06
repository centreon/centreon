import {
  type BuildListingEndpointParameters,
  buildListingEndpoint,
  type SearchParameter
} from '@centreon/ui';

// Served by API Platform, which is not aliased under the default `./api/latest`.
export const hostTemplatesBaseEndpoint = './api';

export const hostTemplatesListEndpoint = '/configuration/host_templates';

export const getHostTemplateEndpoint = ({
  id
}: {
  id: number | string;
}): string => `${hostTemplatesListEndpoint}/${id}`;

export const getDuplicateHostTemplateEndpoint = ({
  id
}: {
  id: number | string;
}): string => `${hostTemplatesListEndpoint}/${id}/_duplicate`;

// The form's selectors, granted by host template write access rather than by
// the rights on the listed resource.
export const hostTemplateFormCommandsEndpoint =
  '/configuration/host_templates/commands';
export const hostTemplateFormHostTemplatesEndpoint =
  '/configuration/host_templates/host_templates';
export const hostTemplateFormHostCategoriesEndpoint =
  '/configuration/host_templates/host_categories';
export const hostTemplateFormHostSeveritiesEndpoint =
  '/configuration/host_templates/host_severities';
export const hostTemplateFormServiceTemplatesEndpoint =
  '/configuration/host_templates/service_templates';

// One template's macros, its own and those it inherits.
export const getHostTemplateMacrosEndpoint = ({ id }: { id: number }): string =>
  `${hostTemplateFormHostTemplatesEndpoint}/${id}/macros`;

// Selectors take `name[lk]`, not the `search` payload the autocomplete builds.
// Given the edited template, the route leaves it and its descendants out.
export const getParentTemplatesEndpoint =
  (editedTemplateId: number | null) =>
  ({ search, page }: { search?: SearchParameter; page?: number }): string => {
    // Once a value is selected the autocomplete prepends a `$ni` condition
    // excluding it, so the typed text is not necessarily the first one.
    const searchedValue = search?.conditions
      ?.map((condition) => condition?.values?.$lk)
      .find((value): value is string => typeof value === 'string');

    return buildListingEndpoint({
      apiFormat: 'JSON-LD',
      baseEndpoint: hostTemplateFormHostTemplatesEndpoint,
      customQueryParameters: [
        ...(search
          ? [
              {
                name: 'name[lk]',
                // The autocomplete wraps the typed text in `%`.
                value: searchedValue?.slice(1, -1) ?? ''
              }
            ]
          : []),
        ...(editedTemplateId
          ? [{ name: 'exclude_template_id', value: String(editedTemplateId) }]
          : [])
      ],
      parameters: {
        limit: 10,
        page
      } as BuildListingEndpointParameters['parameters']
    });
  };
