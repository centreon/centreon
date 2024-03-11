import { baseEndpoint } from '../../../api/endpoint';

export const deleteSingleResourceAccessRuleEndpoint = (id: number): string =>
  `${baseEndpoint}/administration/resource-access/rules/${id}`;
