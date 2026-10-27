// Host templates are served by API Platform at `./api`, like hosts. The form
// reads the same selectors as the host form, under `/configuration/hosts`.
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
