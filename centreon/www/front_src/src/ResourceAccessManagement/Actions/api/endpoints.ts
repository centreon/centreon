import { baseEndpoint } from '../../../api/endpoint';

export const deleteSingleResourceAccessRuleEndpoint = (id: number): string =>
  `${baseEndpoint}/administration/resource-access/rules/${id}`;

export const deleteMultipleRulesEndpoint = `${baseEndpoint}/administration/resource-access/rules/_delete`;
