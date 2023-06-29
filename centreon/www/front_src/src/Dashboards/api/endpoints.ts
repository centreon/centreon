import { NamedEntity } from './models';

export const baseEndpoint = './api/latest';

export const dashboardsEndpoint = `${baseEndpoint}/configuration/dashboards`;

export const getDashboardEndpoint = (id?: NamedEntity['id']): string =>
  `${dashboardsEndpoint}/${id}`;

export const getDashboardAccessRightsEndpoint = (
  id?: NamedEntity['id']
): string => `${baseEndpoint}/configuration/dashboards/${id}/access_rights`;
