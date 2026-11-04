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

// One template's macros, its own and those it inherits.
export const getHostTemplateMacrosEndpoint = ({ id }: { id: number }): string =>
  `${hostTemplateFormHostTemplatesEndpoint}/${id}/macros`;
