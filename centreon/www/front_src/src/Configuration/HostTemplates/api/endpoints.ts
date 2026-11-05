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
